# AestheticAI — Claude Code Context

> Load this file at the start of every Claude Code session to establish the full project context.

---

## Project Identity

**Name:** AestheticAI — Digital Consultation Concierge
**Type:** Multi-tenant HIPAA-compliant SaaS
**Domain:** Plastic surgery / aesthetic medicine lead pre-qualification

---

## Stack (Non-Negotiable)

| Layer | Technology | Version |
|-------|-----------|---------|
| Backend | Laravel | 13 |
| Language | PHP (strict_types=1 always) | 8.5 |
| Frontend | React + Inertia.js | 19 / v3 |
| Types | TypeScript (strict: true, no any) | 5+ |
| Styling | TailwindCSS + Shadcn/UI | 4 |
| Database | PostgreSQL with Row-Level Security | 16 |
| Queue | Laravel Horizon + Redis | — |
| Server | Laravel Octane | — |
| Storage | AWS S3 (KMS encrypted) | — |
| AI/Vision | AWS Rekognition + `laravel/ai` SDK | — |
| AI/Simulation | OpenAI `gpt-image-1` via `laravel/ai` | — |

---

## Architectural Rules (Never Violate)

### 1. Multi-Tenancy
- Every Eloquent model that contains clinic or patient data uses `HasTenantScope` trait
- Every database query is automatically scoped to `tenant_id`
- Never use `withoutGlobalScope('tenant')` without an explicit comment explaining why
- PostgreSQL RLS is enabled as a safety net in addition to app-layer scoping

### 2. HIPAA Compliance
- PHI columns (name, email, phone, dob) use `EncryptedString` / `EncryptedDate` casts
- `AuditLog::record($action, $subject)` called on every PHI access
- Photos accessed only via signed S3 URLs (15-min expiry) — never raw paths
- No PHI in log statements, error messages, or URL parameters
- Webhooks to external CRMs contain reference tokens only, never PHI

### 3. TypeScript
- `strict: true` in tsconfig — no exceptions
- No `any` — use `unknown` and narrow, or proper interface definitions
- All Inertia page props typed via `InferPageProps` or explicit interfaces

### 4. File Structure
```
app/
├── Http/Controllers/     # Thin — validate, delegate, respond
├── Services/             # ALL business logic lives here
├── Jobs/                 # Queued async work (AI, notifications, webhooks)
├── Models/               # Eloquent + traits, no business logic
├── Policies/             # Authorization — one per model
├── Resources/            # API response transformers (JsonResource)
└── Events/ + Listeners/  # Domain events

resources/js/
├── Pages/               # Inertia page components
├── Components/          # Reusable UI (ui/ forms/ charts/)
├── Layouts/             # Page shell layouts
├── Hooks/               # Custom React hooks
├── Types/               # TypeScript interfaces
└── Utils/               # Pure utility functions
```

### 5. Async First
- AI processing: always queued jobs, never synchronous
- Email: always queued
- Webhooks: always queued with retry logic
- Never make the user wait for AI

---

## Data Model Quick Reference

```sql
-- Core tables (all have tenant_id, created_at, updated_at, deleted_at)
tenants           -- Clinics (each is one tenant)
patients          -- PHI: encrypted email/phone
evaluations       -- Core entity: quiz + AI results + lead score
photos            -- S3 keys only, served via signed URLs
audit_log_entries -- Immutable, no soft deletes
webhooks          -- Per-tenant CRM integration config
```

---

## Key Services

```php
TenantContext::getId()                        // Get current tenant ID
TenantContext::set($tenantId)                // Set for testing
AuditLog::record($action, $model)            // Log PHI access
SecureFileService::getSignedUrl($key)        // Get photo URL
SecureFileService::storeEncrypted($file)     // Upload to S3
MagicLinkService::generate($patient)         // Create portal link
MagicLinkService::validate($token)           // Validate + single-use
LeadScoringService::calculate($eval)         // 0-100 score + priority
DispatchWebhook::dispatch($tenant, ...)      // Fire CRM webhook

// Procedure registry — single source of truth for all 26 procedures
ProcedureRegistry::isBodyProcedure($slug)   // Routes to body AI pipeline
ProcedureRegistry::isFaceProcedure($slug)   // Routes to facial AI pipeline
ProcedureRegistry::isHighRevenue($slug)     // Forces min PRIORITY_HIGH in lead scoring
ProcedureRegistry::allSlugs()              // All 26 registered procedure slugs
```

### Octane Safety
`TenantContext`, `AuditLog`, and `SecureFileService` are bound as `scoped()` in `AppServiceProvider` — they reset between requests. Never change to `singleton()` as it causes tenant bleed across requests in long-running Octane workers.

### Dashboard Controllers — Tenant Binding Pattern
Due to `TenantScope` + `SubstituteBindings` ordering, **never use implicit route model binding** in dashboard controllers. Always use `string $id` + manual `findOrFail()`:
```php
// ✅ Correct
public function show(Request $request, string $evaluationId): Response
{
    $evaluation = Evaluation::findOrFail($evaluationId);
}

// ❌ Wrong — TenantContext not set yet when binding resolves
public function show(Request $request, Evaluation $evaluation): Response
```

---

## Design System

**Patient-Facing (luxury aesthetic):**
- Background: `#0A0A0F` (near-black)
- Gold accent: `#C9A84C`
- Text primary: `#F5F0E8` (cream white)
- Mobile-first, full-screen steps, progressive disclosure

**Clinic Dashboard (professional medical):**
- Background: `#FAFAFA`
- Primary: `#1E3A5F` (navy)
- Accent: `#2D9CDB` (medical blue)
- Data-dense, sidebar layout, standard medical SaaS aesthetic

---

## Common Patterns to Follow

### Backend: Service Method Pattern
```php
public function doSomething(int $id): ResourceType
{
    // 1. Fetch (tenant scoped automatically)
    $model = Model::findOrFail($id);

    // 2. Authorize
    Gate::authorize('view', $model);

    // 3. Audit log if PHI
    AuditLog::record('model.viewed', $model);

    // 4. Business logic

    // 5. Return
    return new ModelResource($model);
}
```

### Frontend: Page Component Pattern
```typescript
interface Props {
  evaluation: Evaluation;
  // All types must be explicitly defined
}

export default function EvaluationShow({ evaluation }: Props) {
  // Use Inertia useForm for mutations
  // Use React Query for additional data fetching
  // Use Shadcn/UI components
  return (...);
}
```

---

## Testing Patterns

```php
// Always test tenant isolation
it('description of behavior', function () {
    $tenant = Tenant::factory()->create();
    TenantContext::set($tenant->id);

    // Test action...

    // Verify tenant isolation:
    TenantContext::set(Tenant::factory()->create()->id);
    expect(Model::count())->toBe(0);
});
```

---

## What NOT to Do

```php
// ❌ Untyped PHP
function doThing($id) { ... }

// ❌ PHI in logs
Log::info('Patient: ' . $patient->email);

// ❌ Raw S3 paths in responses
return ['url' => $photo->s3_key];

// ❌ Business logic in controller
public function store(Request $request) {
    // doing stuff directly here instead of delegating to service
}

// ❌ Missing tenant scope
Patient::all(); // Missing tenant isolation!
```

```typescript
// ❌ No TypeScript
const handleClick = (data) => { ... }

// ❌ Using any
const response: any = await fetch(...)

// ❌ PHI in console
console.log('Patient email:', patient.email)

// ❌ useEffect for data fetching
useEffect(() => { fetch('/api/data').then(setData) }, [])
// Use React Query instead
```
