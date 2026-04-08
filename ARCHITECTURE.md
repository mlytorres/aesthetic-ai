# ARCHITECTURE.md — System Design

> AestheticAI is a multi-tenant SaaS platform. This document defines the system boundaries, data flow, tenant isolation model, and key architectural decisions.

---

## System Overview

```
┌─────────────────────────────────────────────────────────────────────┐
│                        Patient-Facing Layer                          │
│                                                                       │
│   Clinic Website ──embed──► AestheticAI Widget (iFrame/JS SDK)      │
│                                    │                                  │
│                           Intake Wizard Flow                          │
│                    Quiz → Photo Capture → Submission                  │
└─────────────────────────────────┬───────────────────────────────────┘
                                  │ HTTPS (TLS 1.3)
┌─────────────────────────────────▼───────────────────────────────────┐
│                         Application Layer                             │
│                                                                       │
│   Laravel 12 (PHP 8.3)           React 18 + Inertia.js              │
│   ├── API Routes                 ├── Patient Portal                  │
│   ├── Inertia Routes             ├── Clinic Dashboard                │
│   ├── Webhook Receivers          └── Embed Widget                    │
│   └── Queue Workers                                                   │
└───────────┬──────────────────────────────────────────┬──────────────┘
            │                                          │
┌───────────▼──────────┐                 ┌────────────▼──────────────┐
│    Data Layer        │                 │    AI Processing Layer     │
│                      │                 │                            │
│  PostgreSQL (RLS)    │                 │  Laravel Horizon (Redis)        │
│  ├── tenants         │                 │  ├── ValidatePhotoQualityJob    │
│  ├── patients        │                 │  ├── ExtractFacialLandmarksJob  │
│  ├── evaluations     │                 │  ├── CalculateProportionsJob    │
│  ├── audit_logs      │                 │  ├── GenerateRecommendationsJob │
│  └── webhooks        │                 │  └── NotifyClinicNewEvalJob     │
│                      │                 │                            │
│  S3 (KMS encrypted)  │                 │  AWS Rekognition           │
│  └── patient-photos/ │                 │  Custom ML Models          │
└──────────────────────┘                 └────────────────────────────┘
            │
┌───────────▼──────────────────────────────────────────────────────────┐
│                    External Integration Layer                          │
│                                                                        │
│  Nextech CRM ◄─── Webhooks ───► PatientNow ◄─── Webhooks ──► Others │
│  Google Calendar ◄── Booking Sync                                     │
│  SendGrid ◄── Transactional Email                                     │
│  Twilio ◄── SMS Notifications                                         │
└────────────────────────────────────────────────────────────────────────┘
```

---

## Multi-Tenancy Model

### Tenant Isolation Strategy: Shared Database, Separate Schemas via RLS

Each clinic (tenant) shares the same PostgreSQL database but data is isolated at multiple levels:

**Level 1 — Application Layer (Eloquent Global Scope)**
```php
// Automatically applied to every query via HasTenantScope trait
WHERE table.tenant_id = 'resolved-tenant-uuid'
```

**Level 2 — Database Layer (PostgreSQL Row-Level Security)**
```sql
-- Set once per connection from application
SET LOCAL app.current_tenant_id = 'resolved-tenant-uuid';

-- RLS Policy on patients table
CREATE POLICY tenant_isolation ON patients
    USING (tenant_id = current_setting('app.current_tenant_id')::uuid);
```

**Level 3 — Network Layer (Subdomain Routing)**
```
clinic-a.aestheticai.com → TenantResolver → tenant_id: uuid-a
clinic-b.aestheticai.com → TenantResolver → tenant_id: uuid-b
```

**Tenant Resolution Order:**
1. Subdomain: `{slug}.aestheticai.com`
2. Custom domain: Clinic's own domain via CNAME
3. Embed token: Widget JWT contains `tenant_id` claim
4. Magic link token: Patient link contains encrypted `tenant_id`

---

## Data Model Overview

### Core Entities

```
tenants
├── id (uuid)
├── slug                    -- subdomain identifier
├── name                    -- clinic display name
├── plan_id                 -- subscription tier
├── webhook_url             -- CRM webhook target
├── webhook_secret          -- HMAC signing key
└── settings (jsonb)        -- UI customization

patients
├── id
├── tenant_id               -- REQUIRED on all patient tables
├── external_crm_id         -- ID in clinic's existing CRM
├── email (encrypted)       -- PHI: AES-256 encrypted at rest
├── phone (encrypted)       -- PHI: AES-256 encrypted at rest
├── name_hash               -- For deduplication without exposing name
└── created_via             -- 'widget' | 'import' | 'api'

evaluations
├── id (uuid)
├── tenant_id
├── patient_id
├── procedure_of_interest   -- e.g., 'rhinoplasty'
├── status                  -- enum: draft|submitted|analyzing|complete|failed
├── quiz_answers (jsonb)    -- All intake form responses
├── analysis_data (jsonb)   -- AI vision results
├── lead_score              -- 0-100 composite score
├── priority                -- urgent|high|medium|standard
├── secure_token            -- Used in magic links and webhook references
└── completed_at

photos
├── id
├── tenant_id
├── evaluation_id
├── type                    -- front|left_profile|right_profile|additional
├── s3_key                  -- Encrypted path in S3
├── quality_score           -- 0-100 from validation job
├── analysis_status         -- pending|complete|failed
└── taken_at

audit_log_entries           -- Append-only, never deleted
├── id
├── tenant_id
├── user_id                 -- null for patient actions
├── action                  -- e.g., 'evaluation.photo.viewed'
├── subject_type + id       -- Polymorphic reference
├── ip_address
└── created_at              -- No updated_at (immutable)
```

---

## Request Lifecycle

### Patient Submitting Evaluation

```
1. Patient opens embed widget on clinic website
   └── Widget loads: GET /embed/{clinic_slug}
       └── TenantResolver identifies tenant from slug

2. Patient completes wizard steps
   └── State managed client-side (no partial saves with PHI)
   └── Each step validates locally before advancing

3. Photo capture step
   └── MediaStream API accesses device camera
   └── Client-side quality check (blur, angle, lighting)
   └── Photo compressed client-side, then uploaded:
       POST /api/evaluations/{token}/photos
       └── SecureFileService stores to S3 with KMS encryption
       └── AuditLog::record('photo.uploaded', $photo)

4. Final submission
   POST /api/evaluations
   └── EvaluationService::create($data)
   └── AI pipeline dispatched to queue
   └── Returns: { token, portal_url }

5. Queue processes asynchronously (no user waiting):
   ValidatePhoto → ExtractLandmarks → AnalyzeProportions
   → GenerateRecommendations → CalculateLeadScore
   → GenerateClinicalBrief → NotifyClinic

6. Clinic notification
   └── Email: "New evaluation ready — [Lead Score: 87, High Priority]"
   └── Webhook: POST to clinic CRM with evaluation_token
   └── Dashboard: Real-time update via Laravel Echo + Pusher
```

### Coordinator Viewing Lead

```
1. Coordinator receives "New Evaluation" email
   └── Email contains Magic Link (secure, one-time)

2. Magic Link validation
   GET /portal/clinic/{token}
   └── MagicLinkService::validate($token)
   └── Session established for coordinator
   └── AuditLog::record('coordinator.portal.accessed', $evaluation)

3. Dashboard loads evaluation
   GET /api/evaluations/{id}
   └── Tenant scope verified
   └── PHI access logged
   └── Photos returned as signed S3 URLs (15-min expiry)

4. Coordinator marks as contacted
   PATCH /api/evaluations/{id}/status
   └── Status: analyzing → contacted
   └── Webhook fired to CRM
```

---

## Security Architecture

### Encryption

| Data Type | Encryption Method |
|-----------|------------------|
| PHI columns (name, email, phone) | AES-256-GCM at application layer |
| Patient photos in S3 | AES-256 with AWS KMS managed keys |
| Database at rest | AWS RDS encryption (AES-256) |
| Data in transit | TLS 1.3 minimum |
| Magic link tokens | SHA-256 hashed before storage |
| Webhook payloads | HMAC-SHA256 signature |

### Authentication & Authorization

```
┌─── Auth Surfaces ─────────────────────────────────────────┐
│                                                            │
│  Patient Portal    → Magic Link (no password)             │
│  Clinic Dashboard  → Email + Password + TOTP (optional)   │
│  Admin Panel       → SSO + Hardware MFA required          │
│  API Access        → Sanctum tokens with tenant scope     │
│  Embed Widget      → Signed JWT (tenant + procedure)      │
│                                                            │
└────────────────────────────────────────────────────────────┘
```

**Authorization Roles per Tenant:**
```
owner        → Full access, billing, settings, all patient data
admin        → All clinical data, user management
coordinator  → Patient list, evaluations, booking
surgeon      → Clinical briefs, analysis data, no PII contact info
viewer       → Read-only dashboard metrics, no PHI
```

---

## Infrastructure

### AWS Architecture (HIPAA Eligible)

```
Route 53 (DNS)
    ↓
CloudFront (CDN + WAF)
    ↓
Application Load Balancer
    ↓
ECS Fargate (Laravel + Octane)
    ↓
┌─────────────────────────────────────┐
│  RDS PostgreSQL (Multi-AZ)         │
│  ElastiCache Redis (Horizon queues) │
│  S3 (patient photos, KMS encrypted) │
│  AWS Rekognition (vision API)       │
│  SES (transactional email)          │
│  KMS (key management)               │
└─────────────────────────────────────┘
```

**HIPAA-Eligible Services Used:** RDS, S3 with KMS, ElastiCache, ECS, SES, Rekognition, CloudTrail, CloudWatch

---

## Architecture Decision Records (ADRs)

### ADR-001: Shared Database with RLS (not separate DB per tenant)

**Decision:** Use a single PostgreSQL database with Row-Level Security + application-layer scoping.

**Rationale:**
- Simpler operations (no per-tenant migrations, backups)
- RLS provides database-enforced isolation as a safety net
- Expected tenant count (hundreds, not thousands) makes shared DB appropriate
- Easier cross-tenant analytics for internal reporting

**Trade-offs:**
- Noisy neighbor risk (mitigated by query optimization and connection pooling)
- A bug that removes tenant scoping could expose cross-tenant data (mitigated by RLS as safety net)

---

### ADR-002: Queued AI Processing (no sync API calls)

**Decision:** All AI vision processing runs in background queues. User never waits for AI.

**Rationale:**
- Photo analysis can take 3–15 seconds — unacceptable for synchronous UX
- Queue allows retry on failure without user impact
- Decouples frontend availability from AI service availability
- Enables processing optimization (batching, priority queues)

---

### ADR-003: No PHI in Webhooks

**Decision:** Webhooks to clinic CRMs contain only reference tokens, not patient data.

**Rationale:**
- Clinic CRM webhooks may not be HIPAA-compliant endpoints
- Reference token allows clinic to fetch PHI from our HIPAA-compliant API
- BAA only covers our platform — we cannot control CRM's handling of received data
- Minimizes liability if webhook payload is intercepted or logged

---

### ADR-004: Client-Side Photo Quality Check

**Decision:** Validate photo quality (blur, angle, lighting) on the device before upload.

**Rationale:**
- Saves bandwidth — bad photos never hit S3
- Faster feedback loop for patient (instant, not after upload)
- Reduces AI processing failures from poor quality inputs
- Implementation: MediaPipe Face Detection + custom quality heuristics in WebAssembly

**Trade-offs:**
- Adds ~2 MB to initial widget bundle (WASM model) — acceptable for the UX gain
- Mobile WebAssembly support is near-universal as of 2024

---

### ADR-005: MediaPipe for Client-Side Face Detection

**Decision:** Use Google MediaPipe Face Detection (WASM) for the in-browser photo quality and angle validation step.

**Rationale:**
- Runs entirely in the browser — no photos leave the device during validation
- Provides face bounding box and keypoints sufficient for angle guidance overlay
- Apache 2.0 license, actively maintained
- ~1.8 MB WASM bundle (acceptable for a full-screen intake flow)
- Avoids sending potentially blurry/unusable photos to S3 and AWS Rekognition

**Trade-offs:**
- Requires a browser that supports WebAssembly (covers >97% of mobile browsers)
- First-time load includes model download — mitigated by CDN caching
- MediaPipe is a runtime dependency, not a build-time one — version pinned via CDN URL

**Alternatives considered:**
- TensorFlow.js face-api: heavier bundle, slower inference on low-end phones
- Server-side pre-validation: adds a round-trip and exposes a pre-upload endpoint to abuse
- No client validation: too many poor-quality photos would degrade AI pipeline results

---

## Caching Strategy

Redis (ElastiCache) serves two roles: queue backend and response cache. Cache TTLs are chosen conservatively for PHI sensitivity.

| Cache Key | TTL | Invalidated By | Notes |
|-----------|-----|---------------|-------|
| `tenant:{slug}` | 15 min | Tenant settings update | Tenant resolution on every request |
| `procedures:{tenant_id}` | 60 min | Procedure config change | Procedure list for quiz engine |
| `quiz_definition:{procedure_slug}` | 60 min | Quiz config update | Quiz branching definition |
| `lead_score:{eval_id}` | Until analysis complete | Score recalculation job | Avoid re-computing during dashboard load |
| `evaluation_list:{tenant_id}` | 30 sec | Any evaluation status change | Dashboard list — short TTL for coordinator UX |

**PHI is never cached.** Patient names, emails, phone numbers, and photos are never written to Redis. The evaluation list cache contains only IDs, scores, priorities, and statuses.

```php
// Example: Tenant resolution caching
Cache::remember("tenant:{$slug}", now()->addMinutes(15), fn() =>
    Tenant::where('slug', $slug)->firstOrFail()
);

// Cache MUST be tagged for selective invalidation
Cache::tags(["tenant:{$tenantId}"])->flush(); // On tenant settings change
```
