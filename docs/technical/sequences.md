# Sequence Diagrams — AestheticAI

> Key system flows illustrated as sequence diagrams. Standalone `.mermaid` files for each diagram are in [`diagrams/`](./diagrams/).

---

## Table of Contents

1. [Patient Intake Flow](#1-patient-intake-flow)
2. [AI Processing Pipeline](#2-ai-processing-pipeline)
3. [Coordinator Views Lead (Magic Link)](#3-coordinator-views-lead--magic-link)
4. [Webhook Delivery & Retry](#4-webhook-delivery--retry)
5. [Tenant Resolution (Embed Widget)](#5-tenant-resolution--embed-widget)
6. [External API Authentication](#6-external-api-authentication)

---

## 1. Patient Intake Flow

Full journey from widget load to evaluation submission. Photos are uploaded during the wizard; the final submit dispatches the AI pipeline.

```mermaid
sequenceDiagram
    autonumber

    actor Patient
    participant Widget as Embed Widget<br/>(Browser)
    participant API as Laravel API
    participant S3 as AWS S3
    participant Queue as Horizon Queue

    Patient->>Widget: Opens clinic page with widget
    Widget->>API: GET /embed/{clinic_slug}
    API-->>Widget: Clinic config (theme, procedures)

    Patient->>Widget: Selects procedure (Rhinoplasty)
    Widget->>API: POST /api/evaluations
    note right of API: Creates draft evaluation<br/>Returns secure token
    API-->>Widget: { evaluation_token }

    loop For each quiz step
        Patient->>Widget: Answers question(s)
        Widget->>API: POST /api/evaluations/{token}/quiz
        API-->>Widget: { next_step, progress }
    end

    rect rgb(20, 40, 60)
        note over Patient,S3: Photo Capture Step
        Patient->>Widget: Grants camera permission
        Widget->>Widget: MediaPipe quality check<br/>(client-side, no upload)

        loop For each photo (front, left, right)
            Patient->>Widget: Captures photo
            Widget->>Widget: Blur + angle validation
            Widget->>API: POST /api/evaluations/{token}/photos<br/>(multipart)
            API->>API: Server-side quality check<br/>(Rekognition face detection)
            API->>S3: Store encrypted (KMS)
            S3-->>API: Stored at s3_key
            API-->>Widget: { photo_id, quality_score }
        end
    end

    Patient->>Widget: Enters name, email, phone
    Patient->>Widget: Accepts HIPAA consent
    Widget->>API: POST /api/evaluations/{token}/submit
    API->>API: Encrypt PHI columns
    API->>Queue: Dispatch AI pipeline jobs
    API-->>Widget: { status: submitted, portal_url }

    Widget-->>Patient: Success screen + portal link
```

---

## 2. AI Processing Pipeline

Asynchronous processing chain after evaluation submission. Each job enqueues the next on success, or marks as failed and alerts on error.

```mermaid
sequenceDiagram
    autonumber

    participant Queue as Horizon Queue
    participant VPQ as ValidatePhotoQualityJob
    participant EFL as ExtractFacialLandmarksJob
    participant CP as CalculateProportionsJob
    participant GR as GenerateRecommendationsJob
    participant LS as LeadScoringService
    participant NC as NotifyClinicNewEvalJob
    participant Rekognition as AWS Rekognition
    participant DB as PostgreSQL
    participant Email as SES Email
    participant WH as WebhookDispatcher

    Queue->>VPQ: Dispatch after submit

    VPQ->>Rekognition: DetectFaces (quality check)
    Rekognition-->>VPQ: Face confidence score

    alt Photo quality fails
        VPQ->>DB: evaluation.status = 'failed'
        VPQ->>Email: Notify clinic (manual review needed)
    else Quality passes
        VPQ->>Queue: Dispatch ExtractFacialLandmarksJob
    end

    Queue->>EFL: Process

    EFL->>Rekognition: DetectFaces (landmark extraction)
    Rekognition-->>EFL: 27 landmark points
    EFL->>DB: Store landmarks in analysis_data JSONB
    EFL->>Queue: Dispatch CalculateProportionsJob

    Queue->>CP: Process

    CP->>DB: Read landmarks from analysis_data
    CP->>CP: Calculate facial thirds, fifths<br/>symmetry score, golden ratio deviation
    CP->>DB: Update analysis_data with proportions
    CP->>Queue: Dispatch GenerateRecommendationsJob

    Queue->>GR: Process

    GR->>DB: Read quiz_answers + analysis_data
    GR->>GR: Rule-based recommendation engine<br/>(MVP — ML model in Phase 3)
    GR->>LS: CalculateLeadScore(quiz, proportions, recommendations)
    LS-->>GR: lead_score (0–100), priority
    GR->>DB: Update: lead_score, priority, analysis_data.recommendations
    GR->>DB: evaluation.status = 'complete'
    GR->>Queue: Dispatch NotifyClinicNewEvalJob

    Queue->>NC: Process

    NC->>DB: Generate magic_link token (SHA-256)
    NC->>DB: Store hashed token in magic_links
    NC->>Email: Send coordinator notification<br/>"New eval ready — Score: 87, Priority: High"
    NC->>WH: Dispatch webhook (evaluation.analysis_complete)
    WH->>DB: Create webhook_deliveries record
    WH-->>WH: POST to clinic webhook URL (signed HMAC)
```

---

## 3. Coordinator Views Lead — Magic Link

Coordinator receives a notification email, clicks the magic link, and accesses the lead detail page.

```mermaid
sequenceDiagram
    autonumber

    actor Coordinator
    participant Email as Email Client
    participant Browser as Browser
    participant MagicLink as MagicLinkService
    participant Auth as AuthMiddleware
    participant API as Laravel API
    participant AuditLog as AuditLog Service
    participant S3 as AWS S3
    participant DB as PostgreSQL

    Email-->>Coordinator: "New Rhinoplasty Evaluation — Score: 87"
    Coordinator->>Email: Clicks portal link

    Browser->>API: GET /portal/clinic/{raw_token}
    API->>MagicLink: validate(raw_token)
    MagicLink->>DB: Lookup token_hash = SHA256(raw_token)

    alt Token expired or used
        MagicLink-->>Browser: 401 MAGIC_LINK_EXPIRED
        Browser-->>Coordinator: "Link expired — log in to continue"
    else Token valid
        MagicLink->>DB: Mark token used_at = now()
        MagicLink->>Auth: Establish coordinator session
        AuditLog->>DB: Record 'coordinator.portal.accessed'
        API-->>Browser: Redirect to /dashboard/evaluations/{id}
    end

    Browser->>API: GET /api/evaluations/{id}
    Auth->>Auth: Verify session + tenant scope

    API->>DB: Fetch evaluation (tenant-scoped)
    API->>DB: Fetch patient (decrypt PHI in memory)
    AuditLog->>DB: Record 'evaluation.dashboard.viewed' (PHI access)

    API->>S3: Generate signed URLs (15-min expiry) for each photo
    S3-->>API: Signed URLs

    API-->>Browser: Evaluation detail + signed photo URLs
    Browser-->>Coordinator: Dashboard rendered

    Coordinator->>Browser: Views photos
    AuditLog->>DB: Record 'evaluation.photos.viewed' (PHI access)

    Coordinator->>Browser: Marks as "Contacted" + adds note
    Browser->>API: PATCH /api/evaluations/{id}/status
    API->>DB: Update status + coordinator_notes
    AuditLog->>DB: Record 'evaluation.status.changed'
    API-->>Browser: 200 OK
```

---

## 4. Webhook Delivery & Retry

Outbound webhook delivery with HMAC signing, exponential backoff retry, and failure notification.

```mermaid
sequenceDiagram
    autonumber

    participant Job as NotifyClinicNewEvalJob
    participant WH as WebhookDispatcher
    participant DB as PostgreSQL
    participant Queue as Horizon Queue
    participant CRM as Clinic CRM

    Job->>WH: dispatch(evaluation, 'evaluation.analysis_complete')

    WH->>WH: Build payload (no PHI — token reference only)
    WH->>WH: Sign payload: HMAC-SHA256(secret, payload)
    WH->>DB: Create webhook_deliveries { status: pending }

    WH->>CRM: POST webhook_url<br/>X-AestheticAI-Signature: sha256=...

    alt CRM responds 2xx within 10s
        CRM-->>WH: 200 OK
        WH->>DB: Update status=delivered, delivered_at=now()
    else CRM timeout or non-2xx
        CRM-->>WH: 500 / timeout
        WH->>DB: attempt_count++, status=retrying
        WH->>DB: next_retry_at = now() + backoff_delay

        note over Queue,CRM: Retry schedule:<br/>Attempt 2: +30s<br/>Attempt 3: +2min<br/>Attempt 4: +10min<br/>Attempt 5: +1hr

        loop Up to 5 total attempts
            Queue->>WH: Re-dispatch at next_retry_at
            WH->>CRM: POST webhook_url (resend)
            alt Succeeds
                CRM-->>WH: 200 OK
                WH->>DB: status=delivered
            else Still failing
                WH->>DB: attempt_count++, schedule next retry
            end
        end

        alt All 5 attempts failed
            WH->>DB: status=failed
            WH->>DB: Create audit_log: 'webhook.failed'
            WH-->>WH: Send admin alert email to clinic
        end
    end
```

---

## 5. Tenant Resolution — Embed Widget

How the platform identifies which clinic is making a request across the three resolution methods.

```mermaid
flowchart TD
    Request([Incoming Request]) --> Method{Resolution\nMethod?}

    Method -->|Subdomain| Sub["Extract slug from\nclinic-a.aestheticai.com"]
    Method -->|Custom Domain| Custom["Lookup domain in\ntenants.settings->>'custom_domain'"]
    Method -->|Embed Token| Embed["Decode JWT embed token\nExtract tenant_id claim"]
    Method -->|Magic Link| Magic["Decode magic_link token\nLookup evaluation → tenant_id"]
    Method -->|API Bearer Token| Bearer["Lookup token_hash\nLoad tenant from api_tokens"]

    Sub --> Resolve[TenantResolver::resolve]
    Custom --> Resolve
    Embed --> Resolve
    Magic --> Resolve
    Bearer --> Resolve

    Resolve --> Found{Tenant\nFound?}

    Found -->|Yes| SetContext["SET LOCAL app.current_tenant_id\n= tenant.id"]
    Found -->|No| Abort["Abort: 404 / 401"]

    SetContext --> Scope["Eloquent HasTenantScope\nautomatically applied\nto all queries"]
    SetContext --> RLS["PostgreSQL RLS\nalso enforces isolation\n(safety net)"]

    Scope --> Handler[Request Handler]
    RLS --> Handler
```

---

## 6. External API Authentication

How Bearer token auth works for the REST API v1.

```mermaid
sequenceDiagram
    autonumber

    participant CRM as Clinic CRM / Server
    participant ALB as Load Balancer
    participant MW as AuthMiddleware
    participant TR as TenantResolver
    participant DB as PostgreSQL
    participant Controller as API Controller

    CRM->>ALB: GET /api/v1/evaluations<br/>Authorization: Bearer aai_live_xxx<br/>X-Clinic-ID: tenant-uuid

    ALB->>MW: Forward request (TLS terminated)

    MW->>MW: Extract Bearer token
    MW->>DB: SELECT * FROM api_tokens<br/>WHERE token_hash = SHA256(token)<br/>AND revoked_at IS NULL

    alt Token not found or revoked
        DB-->>MW: No rows
        MW-->>CRM: 401 UNAUTHENTICATED
    else Token found
        DB-->>MW: api_token row (tenant_id, scopes)

        MW->>MW: Verify X-Clinic-ID matches api_token.tenant_id
        alt Clinic ID mismatch
            MW-->>CRM: 403 TENANT_MISMATCH
        else Match confirmed
            MW->>DB: UPDATE api_tokens SET last_used_at = now()
            MW->>TR: SetTenantContext(tenant_id)
            TR->>DB: SET LOCAL app.current_tenant_id = 'uuid'

            MW->>MW: Check required scope for this endpoint
            alt Missing scope
                MW-->>CRM: 403 UNAUTHORIZED (scope)
            else Scope granted
                MW->>Controller: Forward request
                Controller->>DB: Query (tenant-scoped via HasTenantScope + RLS)
                DB-->>Controller: Tenant-scoped data
                Controller-->>CRM: 200 OK + JSON response
            end
        end
    end
```

---

*Diagrams last updated: 2026-04. For corrections, edit the corresponding `.mermaid` file in `docs/technical/diagrams/`.*
