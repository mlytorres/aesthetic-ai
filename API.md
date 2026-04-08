# API.md — AestheticAI API Reference

> Complete reference for all internal and external API endpoints. For integration guides and webhook setup, see [INTEGRATIONS.md](./INTEGRATIONS.md).

---

## Table of Contents

- [Authentication](#authentication)
- [Base URLs](#base-urls)
- [Common Headers](#common-headers)
- [Error Codes](#error-codes)
- [Rate Limits](#rate-limits)
- [Patient / Widget API](#patient--widget-api) *(unauthenticated, widget-scoped)*
- [Clinic Dashboard API](#clinic-dashboard-api) *(session auth, Inertia)*
- [External REST API v1](#external-rest-api-v1) *(API token auth)*
- [Auth Endpoints](#auth-endpoints)
- [Webhook Events Reference](#webhook-events-reference)

---

## Authentication

AestheticAI has three distinct authentication surfaces:

| Surface | Mechanism | Used By |
|---------|-----------|---------|
| Patient widget | Signed JWT embed token (no login) | Patients submitting evaluations |
| Clinic dashboard | Email/password session + optional TOTP | Coordinators, surgeons, admins |
| Magic link | One-time signed token (15-min expiry) | Coordinator portal access from email |
| External REST API | Bearer token (`Authorization: Bearer {token}`) | Clinic backend servers, Zapier, CRMs |

---

## Base URLs

| Environment | Base URL |
|-------------|----------|
| Production | `https://app.aestheticai.com` |
| Staging | `https://staging.aestheticai.com` |
| Widget CDN | `https://cdn.aestheticai.com/widget/v1` |

All API requests must use HTTPS. HTTP requests are redirected (301) to HTTPS.

---

## Common Headers

```http
Content-Type: application/json
Accept: application/json
X-Request-ID: {uuid}          # Optional — for request tracing in logs
```

For External REST API calls, also include:
```http
Authorization: Bearer {api_token}
X-Clinic-ID: {clinic_uuid}
```

---

## Error Codes

All errors follow a consistent envelope:

```json
{
  "error": {
    "code": "EVALUATION_NOT_FOUND",
    "message": "The requested evaluation does not exist or you do not have access.",
    "status": 404
  }
}
```

| HTTP Status | Error Code | Meaning |
|-------------|-----------|---------|
| 400 | `VALIDATION_ERROR` | Request body failed validation. `errors` field contains per-field detail. |
| 401 | `UNAUTHENTICATED` | Missing or invalid authentication credentials. |
| 401 | `MAGIC_LINK_EXPIRED` | Magic link token has expired or already been used. |
| 403 | `UNAUTHORIZED` | Authenticated but not permitted to perform this action. |
| 403 | `TENANT_MISMATCH` | Token belongs to a different clinic (never exposed in detail). |
| 404 | `NOT_FOUND` | Resource does not exist or is not visible to the caller. |
| 409 | `EVALUATION_ALREADY_SUBMITTED` | Attempt to re-submit a completed evaluation. |
| 413 | `PHOTO_TOO_LARGE` | Photo exceeds 10 MB size limit. |
| 415 | `UNSUPPORTED_MEDIA_TYPE` | Photo must be JPEG or PNG. |
| 422 | `PHOTO_QUALITY_FAILED` | Server-side quality check failed (no face detected). |
| 429 | `RATE_LIMIT_EXCEEDED` | Too many requests. See `Retry-After` header. |
| 500 | `INTERNAL_ERROR` | Unexpected server error. Logged and alerted automatically. |
| 503 | `AI_PIPELINE_UNAVAILABLE` | AI processing services temporarily unavailable. Evaluation saved; will retry. |

**Validation error shape:**
```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The given data was invalid.",
    "status": 422,
    "errors": {
      "email": ["The email field is required."],
      "phone": ["The phone must be a valid phone number."]
    }
  }
}
```

---

## Rate Limits

| Endpoint Group | Limit | Window |
|---------------|-------|--------|
| Widget embed load | 60 | per IP / min |
| `POST /api/evaluations` | 5 | per IP / 15 min |
| Photo upload | 10 | per evaluation |
| Magic link verify | 3 | per IP / hour |
| Dashboard login | 10 attempts | per IP / 15 min |
| External REST API | 300 | per token / min |

Rate-limited responses include:
```http
HTTP/1.1 429 Too Many Requests
Retry-After: 47
X-RateLimit-Limit: 5
X-RateLimit-Remaining: 0
X-RateLimit-Reset: 1718460780
```

---

## Patient / Widget API

These endpoints are called by the embedded widget. They use a signed embed JWT (contained in the widget loader) for tenant identification — no patient login required.

---

### Load Widget Configuration

```http
GET /embed/{clinic_slug}
```

Returns the clinic's widget configuration (theme, procedures offered, custom copy).

**Auth:** None (public, rate-limited by IP)

**Response `200 OK`:**
```json
{
  "data": {
    "clinic": {
      "name": "Miami Life Cosmetic Center",
      "logo_url": "https://cdn.aestheticai.com/tenants/uuid/logo.png",
      "theme": "luxury-dark"
    },
    "procedures": [
      { "slug": "rhinoplasty", "label": "Rhinoplasty", "enabled": true },
      { "slug": "bbl", "label": "Brazilian Butt Lift", "enabled": true }
    ],
    "widget_config": {
      "button_label": "Start Free Evaluation",
      "entry_point": "full",
      "language": "en"
    }
  }
}
```

---

### Create Evaluation (Begin Intake)

```http
POST /api/evaluations
```

Creates a new evaluation record. Called when the patient begins the wizard. Returns a secure token used for all subsequent steps.

**Auth:** Signed embed JWT (from widget loader)

**Request body:**
```json
{
  "procedure_slug": "rhinoplasty",
  "embed_token": "eyJhbGc...",
  "source": "widget"
}
```

**Response `201 Created`:**
```json
{
  "data": {
    "evaluation_token": "evl_01HXYZ9ABC123",
    "expires_at": "2025-06-15T16:00:00Z"
  }
}
```

---

### Submit Quiz Answers

```http
POST /api/evaluations/{evaluation_token}/quiz
```

Saves the patient's quiz responses. Can be called multiple times as the wizard progresses (partial saves for non-PHI steps). Final step must include `final: true`.

**Auth:** Valid `evaluation_token`

**Request body:**
```json
{
  "step": "rhinoplasty_concerns",
  "answers": {
    "concerns": ["tip", "bridge"],
    "prior_rhinoplasty": false,
    "breathing_issues": true,
    "skin_thickness": "medium",
    "timeline": "within_3_months",
    "budget_range": "15000_25000",
    "referral_source": "instagram"
  },
  "final": false
}
```

**Response `200 OK`:**
```json
{
  "data": {
    "next_step": "photo_capture",
    "progress": 0.4
  }
}
```

---

### Upload Patient Photo

```http
POST /api/evaluations/{evaluation_token}/photos
Content-Type: multipart/form-data
```

Uploads a single photo. Client-side quality validation must pass before upload. Server performs a secondary quality check via AWS Rekognition.

**Auth:** Valid `evaluation_token`

**Request (multipart):**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `photo` | File (JPEG/PNG) | Yes | Max 10 MB |
| `type` | string | Yes | `front` \| `left_profile` \| `right_profile` \| `additional` |
| `quality_score` | number | Yes | Client-side quality score (0–100) |
| `capture_metadata` | JSON string | No | Device, orientation, lighting estimate |

**Response `201 Created`:**
```json
{
  "data": {
    "photo_id": "pht_01HABC",
    "type": "front",
    "quality_score": 91,
    "status": "accepted"
  }
}
```

**Error — quality failed:**
```json
{
  "error": {
    "code": "PHOTO_QUALITY_FAILED",
    "message": "No face detected in the submitted photo. Please retake.",
    "status": 422
  }
}
```

---

### Submit Evaluation (Final Step)

```http
POST /api/evaluations/{evaluation_token}/submit
```

Finalises the evaluation with patient contact info and consent. Triggers the AI processing pipeline. After this call the evaluation token is read-only.

**Auth:** Valid `evaluation_token`

**Request body:**
```json
{
  "patient": {
    "name": "Jane Doe",
    "email": "jane@example.com",
    "phone": "+13055550123"
  },
  "consent": {
    "hipaa_acknowledged": true,
    "terms_accepted": true,
    "photo_use_consent": true,
    "consented_at": "2025-06-15T14:32:00Z"
  }
}
```

**Response `200 OK`:**
```json
{
  "data": {
    "status": "submitted",
    "portal_url": "https://app.aestheticai.com/patient/evl_01HXYZ9ABC123",
    "message": "Your evaluation has been received. Our team will reach out within 24 hours."
  }
}
```

---

### Get Evaluation Status (Patient Portal)

```http
GET /api/evaluations/{evaluation_token}/status
```

Allows the patient to check their evaluation status from the confirmation/portal page.

**Auth:** Valid `evaluation_token`

**Response `200 OK`:**
```json
{
  "data": {
    "status": "analyzing",
    "ai_complete": false,
    "estimated_ready_at": "2025-06-15T14:50:00Z",
    "clinic_name": "Miami Life Cosmetic Center"
  }
}
```

---

## Clinic Dashboard API

These endpoints back the Inertia.js clinic dashboard. They use session-based authentication (cookie). All responses are scoped to the authenticated coordinator's tenant.

---

### List Evaluations

```http
GET /api/evaluations
```

Returns paginated, tenant-scoped evaluation list. Used by the clinic dashboard priority queue.

**Auth:** Session (coordinator, admin, owner)

**Query parameters:**

| Param | Type | Default | Description |
|-------|------|---------|-------------|
| `status` | string | — | Filter: `submitted` \| `analyzing` \| `complete` \| `contacted` \| `booked` \| `no_show` \| `not_a_fit` |
| `priority` | string | — | Filter: `urgent` \| `high` \| `medium` \| `standard` |
| `procedure` | string | — | Filter by procedure slug |
| `sort` | string | `lead_score` | `lead_score` \| `created_at` \| `priority` |
| `order` | string | `desc` | `asc` \| `desc` |
| `page` | int | 1 | Page number |
| `per_page` | int | 20 | Max 100 |

**Response `200 OK`:**
```json
{
  "data": [
    {
      "id": "eval_01HABC",
      "procedure_interest": "rhinoplasty",
      "status": "complete",
      "priority": "high",
      "lead_score": 87,
      "ai_analysis_complete": true,
      "photos_count": 3,
      "created_at": "2025-06-15T14:00:00Z",
      "completed_at": "2025-06-15T14:32:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 142,
    "last_page": 8
  }
}
```

---

### Get Evaluation Detail

```http
GET /api/evaluations/{id}
```

Returns full evaluation detail including patient info, quiz summary, AI analysis, and signed photo URLs.

**Auth:** Session (coordinator, admin, owner, surgeon — surgeon sees clinical data only, no PII)

**Response `200 OK`:**
```json
{
  "data": {
    "id": "eval_01HABC",
    "status": "complete",
    "priority": "high",
    "lead_score": 87,
    "procedure_interest": "rhinoplasty",
    "created_at": "2025-06-15T14:00:00Z",
    "patient": {
      "id": "pat_01XYZ",
      "name": "Jane Doe",
      "email": "jane@example.com",
      "phone": "+13055550123"
    },
    "quiz_summary": {
      "concerns": ["tip", "bridge"],
      "prior_surgery": false,
      "breathing_issues": true,
      "skin_thickness": "medium",
      "timeline": "within_3_months",
      "budget_range": "15000_25000"
    },
    "ai_analysis": {
      "model_version": "rekognition-v2.1",
      "analyzed_at": "2025-06-15T14:45:00Z",
      "overall_score": 82,
      "symmetry_score": 78,
      "facial_thirds_balance": 85,
      "golden_ratio_deviation": 0.08,
      "top_recommendations": [
        {
          "procedure": "rhinoplasty",
          "match_score": 94,
          "reasoning": "Dorsal hump and tip refinement indicated by proportion analysis and patient concerns."
        }
      ]
    },
    "photos": {
      "front": {
        "url": "https://s3.us-east-1.amazonaws.com/...",
        "expires_at": "2025-06-15T15:00:00Z",
        "quality_score": 91
      },
      "left_profile": {
        "url": "https://s3.us-east-1.amazonaws.com/...",
        "expires_at": "2025-06-15T15:00:00Z",
        "quality_score": 88
      },
      "right_profile": {
        "url": "https://s3.us-east-1.amazonaws.com/...",
        "expires_at": "2025-06-15T15:00:00Z",
        "quality_score": 85
      }
    }
  }
}
```

> **HIPAA Note:** The `patient` object is omitted for `surgeon` role — surgeons only receive `quiz_summary`, `ai_analysis`, and `photos`. This is enforced by `EvaluationResource` using role-based field inclusion.

---

### Update Evaluation Status

```http
PATCH /api/evaluations/{id}/status
```

Coordinator updates the workflow status of an evaluation.

**Auth:** Session (coordinator, admin, owner)

**Request body:**
```json
{
  "status": "contacted",
  "notes": "Called patient. Consultation booked for July 2nd at 10am.",
  "follow_up_at": "2025-07-02T10:00:00Z"
}
```

**Valid status transitions:**

```
submitted → analyzing (system)
analyzing → complete (system, on AI pipeline finish)
complete  → contacted
contacted → booked | no_show | not_a_fit
booked    → no_show | not_a_fit
```

**Response `200 OK`:**
```json
{
  "data": {
    "id": "eval_01HABC",
    "status": "contacted",
    "updated_at": "2025-06-15T15:10:00Z"
  }
}
```

---

### Download Clinical Brief

```http
GET /api/evaluations/{id}/brief
```

Generates and returns a signed download URL for the pre-op clinical brief PDF.

**Auth:** Session (coordinator, surgeon, admin, owner)

**Response `200 OK`:**
```json
{
  "data": {
    "download_url": "https://app.aestheticai.com/download/brief/signed-token",
    "expires_at": "2025-06-15T15:30:00Z",
    "filename": "evaluation-brief-eval_01HABC.pdf"
  }
}
```

---

### Dashboard Analytics

```http
GET /api/analytics/funnel
```

Returns conversion funnel metrics for the clinic dashboard.

**Auth:** Session (admin, owner)

**Query parameters:**

| Param | Type | Default |
|-------|------|---------|
| `from` | ISO date | 30 days ago |
| `to` | ISO date | today |
| `procedure` | string | all |

**Response `200 OK`:**
```json
{
  "data": {
    "period": { "from": "2025-05-15", "to": "2025-06-15" },
    "funnel": {
      "widget_loads": 1240,
      "evaluations_started": 310,
      "evaluations_completed": 198,
      "coordinator_contacted": 142,
      "consultations_booked": 89,
      "completion_rate": 0.638,
      "lead_to_consult_rate": 0.449
    },
    "by_procedure": [
      { "procedure": "rhinoplasty", "completed": 112, "booked": 54 },
      { "procedure": "bbl", "completed": 86, "booked": 35 }
    ],
    "lead_score_distribution": {
      "urgent": 18,
      "high": 67,
      "medium": 89,
      "standard": 24
    }
  }
}
```

---

## External REST API v1

Used by clinic backend servers, Zapier, and native CRM integrations. All requests require a Bearer token and `X-Clinic-ID` header.

```http
Authorization: Bearer {api_token}
X-Clinic-ID: {clinic_uuid}
```

> The `X-Clinic-ID` header is needed because API calls come from backend servers that don't use clinic subdomains. The token is validated against the clinic ID — a token cannot access a different clinic's data.

---

### List Evaluations

```http
GET /api/v1/evaluations
```

Same filters and response shape as the dashboard endpoint above. PHI (name, email, phone) is included only when the API token has `phi:read` scope.

**Query parameters:** `status`, `priority`, `procedure`, `sort`, `order`, `page`, `per_page` (same as dashboard)

---

### Get Evaluation by Token

```http
GET /api/v1/evaluations/{evaluation_token}
```

Full evaluation detail. See dashboard endpoint for response shape.

---

### Update Evaluation Status

```http
PATCH /api/v1/evaluations/{evaluation_token}/status
```

**Request body:**
```json
{
  "status": "contacted",
  "notes": "CRM auto-updated on contact",
  "external_id": "nextech-patient-4892"
}
```

The `external_id` field stores the CRM's own identifier for the patient — used for two-way sync.

---

## Auth Endpoints

---

### Coordinator Login

```http
POST /auth/login
```

**Request body:**
```json
{
  "email": "coordinator@miamilife.com",
  "password": "secret",
  "totp_code": "123456"
}
```

`totp_code` is only required if the account has TOTP enabled.

**Response `200 OK`:**
```json
{
  "data": {
    "user": {
      "id": "usr_01HABC",
      "name": "Sarah M.",
      "role": "coordinator",
      "clinic_name": "Miami Life Cosmetic Center"
    },
    "redirect": "/dashboard"
  }
}
```

**Response `403` — TOTP required:**
```json
{
  "error": {
    "code": "TOTP_REQUIRED",
    "message": "Two-factor authentication code required.",
    "status": 403
  }
}
```

---

### Coordinator Logout

```http
POST /auth/logout
```

Invalidates the current session.

**Response `204 No Content`**

---

### Verify Magic Link

```http
GET /portal/clinic/{magic_link_token}
```

Validates a magic link token from the coordinator notification email. On success, establishes a session and redirects to the evaluation detail page.

**Errors:**
- `401 MAGIC_LINK_EXPIRED` — Token has expired (15-minute window) or already been used.
- `404 NOT_FOUND` — Token does not exist.

---

### Generate API Token (Settings)

```http
POST /api/settings/api-tokens
```

**Auth:** Session (owner only)

**Request body:**
```json
{
  "name": "HubSpot Integration",
  "scopes": ["evaluations:read", "evaluations:write", "phi:read"]
}
```

**Available scopes:**

| Scope | Permission |
|-------|-----------|
| `evaluations:read` | Read evaluation data (no PHI) |
| `evaluations:write` | Update evaluation status |
| `phi:read` | Read patient PII (name, email, phone) — requires explicit grant |
| `analytics:read` | Read dashboard analytics |
| `webhooks:manage` | Configure webhook settings |

**Response `201 Created`:**
```json
{
  "data": {
    "id": "tok_01HABC",
    "name": "HubSpot Integration",
    "token": "aai_live_xxxxxxxxxxxx",
    "scopes": ["evaluations:read", "evaluations:write", "phi:read"],
    "created_at": "2025-06-15T16:00:00Z"
  }
}
```

> ⚠️ The `token` value is only returned once at creation. Store it immediately — it cannot be retrieved again.

---

### Revoke API Token

```http
DELETE /api/settings/api-tokens/{token_id}
```

**Auth:** Session (owner only)

**Response `204 No Content`**

---

## Webhook Events Reference

When AestheticAI fires a webhook to your configured endpoint, the payload follows this envelope:

```json
{
  "event": "{event_name}",
  "api_version": "2025-01",
  "idempotency_key": "unique-per-delivery",
  "timestamp": "2025-06-15T14:32:00Z",
  "data": { ... }
}
```

---

### `evaluation.started`

Fired when a patient opens the intake wizard (procedure selected, wizard initialized).

```json
{
  "event": "evaluation.started",
  "data": {
    "evaluation_token": "evl_01HXYZ9ABC123",
    "procedure_interest": "rhinoplasty",
    "source": "widget"
  }
}
```

---

### `evaluation.completed`

Fired when the patient submits their contact info and consents. AI pipeline starts immediately after.

```json
{
  "event": "evaluation.completed",
  "data": {
    "evaluation_token": "evl_01HXYZ9ABC123",
    "procedure_interest": "rhinoplasty",
    "lead_score": null,
    "priority": null,
    "ready_for_call": false,
    "timeline": "within_3_months",
    "budget_range": "15000_25000",
    "photos_available": true,
    "ai_analysis_complete": false,
    "portal_url": "https://app.aestheticai.com/portal/clinic/TOKEN"
  }
}
```

> `lead_score` and `priority` are null at this stage — AI processing hasn't finished yet. Listen for `evaluation.analysis_complete` to get the final score.

---

### `evaluation.analysis_complete`

Fired when the AI pipeline finishes processing all photos and generating recommendations.

```json
{
  "event": "evaluation.analysis_complete",
  "data": {
    "evaluation_token": "evl_01HXYZ9ABC123",
    "lead_score": 87,
    "priority": "high",
    "ready_for_call": true,
    "ai_summary": "Strong rhinoplasty candidate. Dorsal hump and tip refinement indicated.",
    "portal_url": "https://app.aestheticai.com/portal/clinic/TOKEN"
  }
}
```

---

### `evaluation.status_changed`

Fired when a coordinator updates the evaluation status.

```json
{
  "event": "evaluation.status_changed",
  "data": {
    "evaluation_token": "evl_01HXYZ9ABC123",
    "previous_status": "complete",
    "new_status": "contacted",
    "changed_by": "coordinator",
    "external_id": "nextech-patient-4892"
  }
}
```

---

### `consultation.booked`

Fired when a consultation is confirmed through the Google Calendar integration.

```json
{
  "event": "consultation.booked",
  "data": {
    "evaluation_token": "evl_01HXYZ9ABC123",
    "scheduled_at": "2025-07-02T10:00:00Z",
    "duration_minutes": 45,
    "calendar_event_id": "gcal_XXXXX"
  }
}
```

---

## Changelog

| Version | Date | Changes |
|---------|------|---------|
| `2025-01` | 2025-06-01 | Initial API version. Patient intake, clinic dashboard, webhook events. |
