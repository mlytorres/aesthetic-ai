# SKILLS.md — Reusable Capability Definitions

> This file defines atomic, reusable skills that AI coding agents can compose to implement features. Each skill is a self-contained capability with defined inputs, outputs, and implementation patterns. Use this file as a reference when prompting Claude Code or Cursor.

---

## Skill Index

| # | Skill | Category | Complexity |
|---|-------|----------|-----------|
| 1 | [TenantScoped Query](#1-tenantscoped-query) | Backend | Low |
| 2 | [PHI Audit Logger](#2-phi-audit-logger) | Security | Low |
| 3 | [Signed S3 URL Generator](#3-signed-s3-url-generator) | Storage | Low |
| 4 | [Magic Link Generator](#4-magic-link-generator) | Auth | Medium |
| 5 | [Webhook Dispatcher](#5-webhook-dispatcher) | Integration | Medium |
| 6 | [Dynamic Quiz Engine](#6-dynamic-quiz-engine) | Product | High |
| 7 | [AI Job Pipeline](#7-ai-job-pipeline) | AI/ML | High |
| 8 | [Lead Scoring Calculator](#8-lead-scoring-calculator) | Product | Medium |
| 9 | [Inertia Form Pattern](#9-inertia-form-pattern) | Frontend | Low |
| 10 | [Multi-Step Wizard](#10-multi-step-wizard) | Frontend | Medium |
| 11 | [Golden Ratio Analyzer](#11-golden-ratio-analyzer) | AI/ML | High |
| 12 | [Procedure Recommender](#12-procedure-recommender) | AI/ML | High |
| 13 | [Clinical Brief Generator](#13-clinical-brief-generator) | Product | Medium |
| 14 | [Embed Widget Loader](#14-embed-widget-loader) | Integration | Medium |
| 15 | [HIPAA Compliant Logger](#15-hipaa-compliant-logger) | Security | Low |

---

## 1. TenantScoped Query

**Category:** Backend | **Complexity:** Low

**Purpose:** Ensure every database query is automatically scoped to the current tenant. Prevents data leakage between clinics.

**Implementation:**
```php
// Trait: app/Models/Concerns/HasTenantScope.php
trait HasTenantScope
{
    protected static function bootHasTenantScope(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            if ($tenantId = TenantContext::getId()) {
                $builder->where(
                    $builder->getModel()->getTable() . '.tenant_id',
                    $tenantId
                );
            }
        });

        static::creating(function (Model $model) {
            if (empty($model->tenant_id)) {
                $model->tenant_id = TenantContext::getId()
                    ?? throw new TenantContextMissingException();
            }
        });
    }
}

// Usage in any Model:
class Patient extends Model
{
    use HasTenantScope, SoftDeletes;
    // tenant_id is automatically applied to ALL queries
}
```

**Testing Pattern:**
```php
it('never returns patients from another tenant', function () {
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();

    TenantContext::set($tenant1->id);
    Patient::factory()->create(['tenant_id' => $tenant1->id]);

    TenantContext::set($tenant2->id);
    expect(Patient::count())->toBe(0); // Must be 0, not 1
});
```

---

## 2. PHI Audit Logger

**Category:** Security | **Complexity:** Low

**Purpose:** Record every access to Protected Health Information with who, what, when, and from where.

**Implementation:**
```php
// Service: app/Services/AuditLog.php
final class AuditLog
{
    public static function record(
        string $action,          // e.g., 'patient.viewed', 'photo.downloaded'
        Model $subject,          // The PHI resource accessed
        ?string $reason = null   // Clinical reason for access
    ): void {
        AuditLogEntry::create([
            'tenant_id'    => TenantContext::getId(),
            'user_id'      => Auth::id(),
            'action'       => $action,
            'subject_type' => $subject::class,
            'subject_id'   => $subject->getKey(),
            'ip_address'   => request()->ip(),
            'user_agent'   => request()->userAgent(),
            'reason'       => $reason,
            'metadata'     => [
                'session_id' => session()->getId(),
                'route'      => request()->route()?->getName(),
            ],
        ]);
    }
}

// Usage in any Service:
public function getPatientProfile(int $patientId): Patient
{
    $patient = Patient::findOrFail($patientId);
    AuditLog::record('patient.profile.viewed', $patient);
    return $patient;
}
```

**Schema:**
```sql
CREATE TABLE audit_log_entries (
    id          BIGSERIAL PRIMARY KEY,
    tenant_id   UUID NOT NULL,
    user_id     BIGINT,
    action      VARCHAR(100) NOT NULL,  -- e.g., 'patient.photo.viewed'
    subject_type VARCHAR(100),
    subject_id  BIGINT,
    ip_address  INET,
    user_agent  TEXT,
    reason      TEXT,
    metadata    JSONB DEFAULT '{}',
    created_at  TIMESTAMPTZ DEFAULT NOW()
    -- No updated_at, deleted_at — audit logs are immutable
);
```

---

## 3. Signed S3 URL Generator

**Category:** Storage | **Complexity:** Low

**Purpose:** Generate time-limited, pre-signed URLs for patient photos. Never expose raw S3 paths.

**Implementation:**
```php
// Service: app/Services/SecureFileService.php
final class SecureFileService
{
    public function __construct(
        private readonly S3Client $s3,
        private readonly string $bucket,
    ) {}

    public function getSignedUrl(
        string $s3Key,
        int $expiryMinutes = 15,
        string $disposition = 'inline'
    ): string {
        $cmd = $this->s3->getCommand('GetObject', [
            'Bucket'                     => $this->bucket,
            'Key'                        => $s3Key,
            'ResponseContentDisposition' => "{$disposition}; filename=\"photo.jpg\"",
        ]);

        return (string) $this->s3->createPresignedRequest(
            $cmd,
            "+{$expiryMinutes} minutes"
        )->getUri();
    }

    public function storeEncrypted(
        UploadedFile $file,
        string $path
    ): string {
        // Server-side encryption with AWS KMS
        return Storage::disk('s3-hipaa')->putFile($path, $file, [
            'ServerSideEncryption' => 'aws:kms',
            'SSEKMSKeyId'          => config('aws.kms_key_id'),
        ]);
    }
}
```

**React Usage:**
```typescript
// Photos are fetched as signed URLs, never stored in state as raw paths
const { data: photoUrl } = useQuery({
  queryKey: ['photo', evaluationId, photoType],
  queryFn: () => api.get<string>(`/evaluations/${evaluationId}/photos/${photoType}/url`),
  staleTime: 10 * 60 * 1000, // Refresh before 15-min expiry
});
```

---

## 4. Magic Link Generator

**Category:** Auth | **Complexity:** Medium

**Purpose:** Send patients a one-time, time-limited link to access their evaluation portal. No password required.

**Implementation:**
```php
// Service: app/Services/MagicLinkService.php
final class MagicLinkService
{
    private const EXPIRY_HOURS = 72;
    private const TOKEN_BYTES  = 32;

    public function generate(Patient $patient): string
    {
        $token = bin2hex(random_bytes(self::TOKEN_BYTES));

        PatientMagicLink::create([
            'tenant_id'  => $patient->tenant_id,
            'patient_id' => $patient->id,
            'token_hash' => hash('sha256', $token), // Never store raw token
            'expires_at' => now()->addHours(self::EXPIRY_HOURS),
            'used_at'    => null,
        ]);

        return route('patient.portal', ['token' => $token]);
    }

    public function validate(string $token): Patient
    {
        $link = PatientMagicLink::where('token_hash', hash('sha256', $token))
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->firstOrFail();

        $link->update(['used_at' => now()]);

        return $link->patient;
    }
}
```

---

## 5. Webhook Dispatcher

**Category:** Integration | **Complexity:** Medium

**Purpose:** Send signed, idempotent webhook payloads to clinic CRMs when evaluation events occur.

**Implementation:**
```php
// Job: app/Jobs/DispatchWebhook.php
final class DispatchWebhook implements ShouldQueue
{
    use Queueable, InteractsWithQueue;

    public int $tries = 5;
    public array $backoff = [30, 120, 600, 3600, 14400]; // seconds

    public function __construct(
        private readonly Tenant $tenant,
        private readonly string $event,
        private readonly array $payload,
        private readonly string $idempotencyKey,
    ) {}

    public function handle(): void
    {
        $webhook = $this->tenant->webhook;
        if (!$webhook?->is_active) return;

        $body = json_encode([
            'event'            => $this->event,
            'idempotency_key'  => $this->idempotencyKey,
            'timestamp'        => now()->toIso8601String(),
            'data'             => $this->payload, // Never raw PHI
        ]);

        $signature = hash_hmac('sha256', $body, $webhook->secret);

        Http::withHeaders([
            'X-AestheticAI-Signature' => "sha256={$signature}",
            'X-AestheticAI-Event'     => $this->event,
            'Content-Type'            => 'application/json',
        ])->throw()->post($webhook->url, $body);
    }
}

// Payload contract — reference tokens, not raw PHI:
[
    'event'           => 'evaluation.completed',
    'idempotency_key' => 'eval_01HXYZ...',
    'data' => [
        'evaluation_token' => 'secure-reference-token', // Clinic uses this to fetch details
        'procedure_interest' => 'rhinoplasty',
        'lead_score'       => 87,
        'priority'         => 'high',
        'ready_for_call'   => true,
    ]
]
```

---

## 6. Dynamic Quiz Engine

**Category:** Product | **Complexity:** High

**Purpose:** Render a branching intake quiz that adapts questions based on procedure selection and previous answers.

**Schema:**
```typescript
// Types: resources/js/Types/quiz.ts
interface QuizDefinition {
  id: string;
  procedure: ProcedureSlug;
  steps: QuizStep[];
}

interface QuizStep {
  id: string;
  type: 'single_choice' | 'multi_choice' | 'scale' | 'text' | 'number' | 'photo';
  question: string;
  helpText?: string;
  required: boolean;
  conditions?: StepCondition[]; // Show this step only if conditions are met
  options?: QuizOption[];
  validation?: ValidationRule;
}

interface StepCondition {
  stepId: string;
  operator: 'equals' | 'contains' | 'greater_than' | 'less_than';
  value: string | number;
}
```

**Engine Hook:**
```typescript
// Hooks: resources/js/Hooks/useQuizEngine.ts
export function useQuizEngine(definition: QuizDefinition) {
  const [answers, setAnswers] = useState<Record<string, QuizAnswer>>({});
  const [currentStepIndex, setCurrentStepIndex] = useState(0);

  const visibleSteps = useMemo(() =>
    definition.steps.filter(step =>
      !step.conditions || step.conditions.every(cond =>
        evaluateCondition(cond, answers)
      )
    ), [definition.steps, answers]
  );

  const progress = (currentStepIndex / visibleSteps.length) * 100;

  return { visibleSteps, currentStepIndex, progress, answers, setAnswers, setCurrentStepIndex };
}
```

---

## 7. AI Job Pipeline

**Category:** AI/ML | **Complexity:** High

**Purpose:** Process patient photos through the vision analysis pipeline asynchronously.

**Pipeline:**
```php
// Orchestration via chained jobs
ProcessPhotoUpload::dispatch($evaluation)
    ->chain([
        new ValidatePhotoQuality($evaluation),
        new ExtractFacialLandmarks($evaluation),
        new CalculateProportions($evaluation),
        new GenerateProcedureRecommendations($evaluation),
        new NotifyClinicNewEvaluation($evaluation),
    ]);

// Example stage:
final class CalculateProportions implements ShouldQueue
{
    public function handle(VisionService $vision): void
    {
        $landmarks = $this->evaluation->analysis_data['landmarks'];

        $scores = $vision->calculateGoldenRatio($landmarks);

        $this->evaluation->update([
            'analysis_data->proportions' => $scores,
            'analysis_data->model_version' => config('ai.vision_model_version'),
            'status' => EvaluationStatus::ProportionsComplete,
        ]);
    }

    public function failed(Throwable $e): void
    {
        $this->evaluation->update(['status' => EvaluationStatus::AnalysisFailed]);
        Log::error('Proportion analysis failed', [
            'evaluation_id' => $this->evaluation->id,
            // NEVER log patient identifiable data here
        ]);
    }
}
```

---

## 8. Lead Scoring Calculator

**Category:** Product | **Complexity:** Medium

**Purpose:** Calculate a composite lead score (0–100) to prioritize coordinator outreach.

**Scoring Matrix:**
```php
final class LeadScoringService
{
    // Maximum possible points per category
    private const WEIGHTS = [
        'procedure_value'   => 30, // High-value surgeries score higher
        'engagement_depth'  => 20, // Completed all quiz steps vs. partial
        'photo_quality'     => 15, // Clear, usable photos submitted
        'geographic_fit'    => 10, // In clinic's service area
        'timeline_urgency'  => 15, // "Ready now" vs. "just researching"
        'previous_contact'  => 10, // Has had prior consultations
    ];

    // Procedure value tiers
    private const PROCEDURE_SCORES = [
        'facelift'       => 30,
        'rhinoplasty'    => 28,
        'bbl'            => 27,
        'breast_augment' => 26,
        'lipo_360'       => 24,
        'blepharoplasty' => 20,
        'fillers'        => 10,
        'botox'          => 8,
    ];

    public function calculate(Evaluation $evaluation): int
    {
        return min(100,
            $this->scoreProcedureValue($evaluation) +
            $this->scoreEngagement($evaluation) +
            $this->scorePhotoQuality($evaluation) +
            $this->scoreTimeline($evaluation) +
            $this->scorePreviousContact($evaluation)
        );
    }
}
```

**Priority Tiers:**
```
90–100 → 🔴 URGENT   — Call within 1 hour
70–89  → 🟠 HIGH     — Call within 4 hours
50–69  → 🟡 MEDIUM   — Call within 24 hours
< 50   → 🟢 STANDARD — Call within 48 hours
```

---

## 9. Inertia Form Pattern

**Category:** Frontend | **Complexity:** Low

**Purpose:** Standardized pattern for all data-submitting forms using Inertia's `useForm` hook.

**Template:**
```typescript
// resources/js/Components/forms/ExampleForm.tsx
import { useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

interface FormData {
  field_one: string;
  field_two: number;
}

export function ExampleForm() {
  const { data, setData, post, processing, errors, reset } = useForm<FormData>({
    field_one: '',
    field_two: 0,
  });

  function handleSubmit(e: FormEvent) {
    e.preventDefault();
    post(route('example.store'), {
      onSuccess: () => reset(),
      onError: () => { /* errors auto-populated */ },
    });
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      <div>
        <label htmlFor="field_one">Field One</label>
        <input
          id="field_one"
          value={data.field_one}
          onChange={e => setData('field_one', e.target.value)}
          className={errors.field_one ? 'border-red-500' : ''}
        />
        {errors.field_one && <p className="text-red-500 text-sm">{errors.field_one}</p>}
      </div>

      <button type="submit" disabled={processing}>
        {processing ? 'Saving...' : 'Save'}
      </button>
    </form>
  );
}
```

---

## 10. Multi-Step Wizard

**Category:** Frontend | **Complexity:** Medium

**Purpose:** Progressive multi-step UI for patient intake flow. Maintains state across steps, validates per-step.

```typescript
// Hooks: resources/js/Hooks/useWizard.ts
export function useWizard<T extends object>(
  steps: WizardStep<T>[],
  onComplete: (data: T) => Promise<void>
) {
  const [currentStep, setCurrentStep] = useState(0);
  const [formData, setFormData] = useState<Partial<T>>({});
  const [isSubmitting, setIsSubmitting] = useState(false);

  const step = steps[currentStep];
  const isFirst = currentStep === 0;
  const isLast = currentStep === steps.length - 1;
  const progress = ((currentStep) / steps.length) * 100;

  async function next(stepData: Partial<T>) {
    const merged = { ...formData, ...stepData };
    setFormData(merged);

    if (isLast) {
      setIsSubmitting(true);
      await onComplete(merged as T);
      setIsSubmitting(false);
    } else {
      setCurrentStep(s => s + 1);
    }
  }

  return { step, next, back: () => setCurrentStep(s => s - 1),
           isFirst, isLast, progress, isSubmitting, formData };
}
```

---

## 11. Golden Ratio Analyzer

**Category:** AI/ML | **Complexity:** High

**Purpose:** Calculate facial proportion scores based on neoclassical beauty canons.

**Key Ratios:**
```typescript
interface GoldenRatioAnalysis {
  overall_score: number;          // 0–100
  facial_thirds: ThirdsAnalysis;  // Forehead : Midface : Lower
  facial_fifths: FifthsAnalysis;  // Horizontal proportion
  phi_ratio: number;              // Actual vs. ideal 1.618
  symmetry_score: number;         // Left-right deviation percentage
  recommendations: string[];      // Plain-language findings
}

// Calculated from facial landmark coordinates:
function calculateFacialThirds(landmarks: FacialLandmarks): ThirdsAnalysis {
  const hairline  = landmarks.forehead_top.y;
  const browLine  = landmarks.brow_center.y;
  const noseTip   = landmarks.nose_tip.y;
  const chinPoint = landmarks.chin.y;

  const upperThird  = browLine - hairline;
  const middleThird = noseTip - browLine;
  const lowerThird  = chinPoint - noseTip;

  return {
    upper:  upperThird,
    middle: middleThird,
    lower:  lowerThird,
    balance_score: calculateBalance(upperThird, middleThird, lowerThird),
  };
}
```

---

## 12. Procedure Recommender

**Category:** AI/ML | **Complexity:** High

**Purpose:** Suggest procedures based on quiz answers, photo analysis, and patient demographics.

**Decision Matrix:**
```php
final class ProcedureRecommendationEngine
{
    public function recommend(Evaluation $evaluation): array
    {
        $signals = $this->gatherSignals($evaluation);

        return collect(Procedure::all())
            ->map(fn($p) => [
                'procedure'   => $p,
                'match_score' => $this->scoreMatch($p, $signals),
                'reasoning'   => $this->generateReasoning($p, $signals),
            ])
            ->sortByDesc('match_score')
            ->take(3)
            ->values()
            ->toArray();
    }

    private function gatherSignals(Evaluation $evaluation): array
    {
        return [
            'concern_areas'    => $evaluation->quiz_answers['concern_areas'] ?? [],
            'age_range'        => $evaluation->quiz_answers['age_range'],
            'skin_laxity'      => $evaluation->analysis_data['skin_laxity_score'],
            'bmi_estimate'     => $evaluation->quiz_answers['bmi'],
            'budget_range'     => $evaluation->quiz_answers['budget'],
            'recovery_window'  => $evaluation->quiz_answers['recovery_tolerance'],
            'previous_surgery' => $evaluation->quiz_answers['prior_procedures'],
        ];
    }
}
```

---

## 13. Clinical Brief Generator

**Category:** Product | **Complexity:** Medium

**Purpose:** Auto-generate a pre-consultation summary for the surgeon before they meet the patient.

**Output Template:**
```
PATIENT PRE-CONSULTATION BRIEF
Generated: {date} | Evaluation: {id}

CHIEF CONCERN:
Patient presents with interest in {procedure}. Primary concern areas: {concerns}.

CANDIDACY OVERVIEW:
• Lead Score: {score}/100 ({priority} priority)
• AI Candidacy: {recommendation}
• Photo Quality: {quality_rating}

PROPORTION ANALYSIS:
• Overall Score: {score}/100
• Key Finding: {key_finding}
• Surgeon Note: {ai_note}

HEALTH SCREENING FLAGS:
• BMI: {bmi_category} ({flag_or_clear})
• Prior Surgeries: {prior_list}
• Contraindications: {none_or_list}

PATIENT READINESS:
• Timeline: {timeline}
• Budget Range: {budget}
• Decision Stage: {stage}

RECOMMENDED CONSULTATION FOCUS:
1. {recommendation_1}
2. {recommendation_2}
3. {recommendation_3}
```

---

## 14. Embed Widget Loader

**Category:** Integration | **Complexity:** Medium

**Purpose:** JavaScript snippet clinics embed on their website to launch the intake flow.

```html
<!-- Clinic adds to their site: -->
<script
  src="https://cdn.aestheticai.com/widget.js"
  data-clinic-id="clinic_abc123"
  data-procedure="rhinoplasty"
  data-theme="luxury-dark"
  async
></script>
<div id="aestheticai-widget"></div>
```

```javascript
// widget.js — zero dependencies, ~8KB gzipped
(function(window, document) {
  const AestheticAI = {
    init(config) {
      const iframe = document.createElement('iframe');
      iframe.src = `https://app.aestheticai.com/embed/${config.clinicId}` +
                   `?procedure=${config.procedure}&theme=${config.theme}`;
      iframe.style.cssText = 'width:100%;height:700px;border:none;border-radius:12px';
      iframe.allow = 'camera'; // For photo capture

      document.getElementById('aestheticai-widget').appendChild(iframe);

      // Listen for completion events from iframe
      window.addEventListener('message', (event) => {
        if (event.origin !== 'https://app.aestheticai.com') return;
        if (event.data.type === 'EVALUATION_COMPLETE') {
          config.onComplete?.(event.data.evaluationToken);
        }
      });
    }
  };

  // Auto-init from script tag attributes
  const script = document.currentScript;
  AestheticAI.init({
    clinicId:  script.dataset.clinicId,
    procedure: script.dataset.procedure,
    theme:     script.dataset.theme ?? 'luxury-dark',
  });
})(window, document);
```

---

## 15. HIPAA Compliant Logger

**Category:** Security | **Complexity:** Low

**Purpose:** Structured logging that automatically scrubs PHI from log entries.

```php
// app/Logging/HipaaCompliantProcessor.php
final class HipaaCompliantProcessor implements ProcessorInterface
{
    private const PHI_FIELDS = [
        'name', 'first_name', 'last_name', 'email', 'phone',
        'dob', 'date_of_birth', 'address', 'ssn', 'medical_record'
    ];

    public function __invoke(array $record): array
    {
        $record['context'] = $this->scrubPhi($record['context']);
        $record['extra']   = $this->scrubPhi($record['extra']);
        return $record;
    }

    private function scrubPhi(array $data): array
    {
        foreach ($data as $key => $value) {
            if (in_array(strtolower($key), self::PHI_FIELDS)) {
                $data[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $data[$key] = $this->scrubPhi($value);
            }
        }
        return $data;
    }
}
```

---

## Composing Skills

Skills are designed to work together. Common compositions:

### New Evaluation Created
```
TenantScoped Query → Create evaluation record
PHI Audit Logger   → Log creation event
AI Job Pipeline    → Dispatch photo analysis chain
Lead Scoring       → Calculate initial score
Webhook Dispatcher → Notify clinic CRM
```

### Patient Accesses Portal
```
Magic Link Generator → Validate token
PHI Audit Logger     → Log access event
Signed S3 URL        → Generate photo URLs
HIPAA Logger         → Structured access log
```

### Surgeon Views Brief
```
TenantScoped Query   → Fetch evaluation
PHI Audit Logger     → Log PHI access (clinical reason required)
Clinical Brief Gen   → Render formatted brief
Signed S3 URL        → Generate photo URLs (15-min expiry)
```
