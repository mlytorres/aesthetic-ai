# SECURITY.md — HIPAA Compliance & Security Controls

> This document defines the security architecture, HIPAA compliance controls, and threat model for SymetriHealth. All developers must read this before contributing.
>
> **Last reviewed:** April 18, 2026  
> **Last code verification:** April 18, 2026 (application source vs this document)

---

## Verification snapshot (code vs document)

This section is refreshed when we compare **claims in SECURITY.md** to **what is actually in the repo** (`bootstrap/app.php`, middleware, `AppServiceProvider`, routes).

| Area | Verified state | Where to look |
|------|----------------|---------------|
| Tenant isolation + audit logging | Implemented (ongoing vigilance in PRs) | `HasTenantScope`, `AuditLog` |
| Intake POST throttles | Implemented | `AppServiceProvider::configureRateLimiters()`, `routes/web.php` intake group |
| Login / 2FA challenge throttles | Implemented | `FortifyServiceProvider::configureRateLimiting()` |
| Global HTTP security headers (HSTS, baseline CSP, etc.) | **Implemented** | `SecurityHeadersMiddleware` on web stack; HSTS only when `APP_ENV=production` and HTTPS |
| Embed / intake framing policy | **Allowlist-based** | `AllowFramesMiddleware` + tenant `settings.embed_parent_origins` (+ optional `SECURITY_EMBED_PARENT_ORIGINS_EXTRA`); defaults to `frame-ancestors 'none'` |
| Magic link route throttle | **Implemented** | `throttle:magic-link` on `GET /magic/{token}` |
| Widget loader throttle | **Implemented** | `throttle:widget-loader` on `GET /widget.js` |
| External REST API v1 throttle | **Implemented** | `throttle:api.v1` on `routes/api.php` v1 group |
| BAA fields on tenants | **Phase A implemented** | `tenants.baa_signed_at`, `tenants.baa_document_path` (encrypted); super-admin tenant UI; intake mutations gated when `SECURITY_REQUIRE_BAA_FOR_INTAKE=true` |
| Mandatory 2FA for privileged roles | **Implemented** (owner / admin / coordinator) | `RequirePrivilegedTwoFactor` on tenant dashboard + billing routes; `SECURITY_REQUIRE_2FA_PRIVILEGED_ROLES`; impersonation bypass |
| Virus scanning on uploads | **Not implemented** | — |
| Sentry + PHI | **Hardened** | User email removed from Sentry scope + `before_send` / `before_breadcrumb` PHI scrubbing callbacks |

---

## Implementation status overview

| Control | Status | Priority |
|---------|--------|----------|
| PHI column encryption (AES-256) | ✅ Implemented | — |
| S3 photo storage (private + signed URLs) | ✅ Implemented | — |
| Audit logging (PHI access) | ✅ Implemented | — |
| Webhook HMAC signing | ✅ Implemented | — |
| Session timeout (30 min) | ✅ Implemented | — |
| Role-based access control | ✅ Implemented | — |
| Multi-tenant data isolation (global scopes) | ✅ Implemented | — |
| Intake / affiliate POST rate limits | ✅ Implemented | — |
| Fortify login + two-factor challenge throttles | ✅ Implemented | — |
| HTTP security headers middleware | ✅ Implemented | — |
| CSP / `frame-ancestors` (embed) | ✅ Allowlist (tenant settings) | — |
| 2FA enforcement for high-privilege roles | ✅ Owner / admin / coordinator (dashboard) | Super-admin `/admin` not covered by this gate |
| BAA tracking per tenant | ✅ Phase A (manual date + optional PDF; e-sign deferred) | **P1** follow-up: BoldSign / automation |
| Rate limits: magic link, `widget.js`, REST API v1 | ✅ Implemented | Observe and tune thresholds in production |
| Virus scan on photo uploads | ❌ Missing | P2 |
| Sentry: scrub user context + `beforeSend` hardening | ✅ Implemented | Continue reviewing new PHI fields in scrub list |

---

## HIPAA Compliance Overview

SymetriHealth handles Protected Health Information (PHI) as defined by HIPAA. The platform acts as a **Business Associate** to covered entities (plastic surgery clinics).

### What Constitutes PHI in This System

```
DIRECT IDENTIFIERS (strict controls):
  ✓ Patient name
  ✓ Email address
  ✓ Phone number
  ✓ Date of birth
  ✓ Geographic data (address, zip code)
  ✓ Photos (facial images — unique biometric identifiers)

CLINICAL DATA (PHI when linked to patient):
  ✓ Quiz answers (medical history, BMI, prior surgeries)
  ✓ AI analysis results (facial measurements, skin laxity scores)
  ✓ Procedure interests and recommendations
  ✓ Consultation notes
  ✓ Lead scores linked to patient records

NOT PHI (in isolation):
  ✗ Anonymized aggregate statistics
  ✗ Procedure pricing information
  ✗ Clinic operational data
  ✗ Anonymous quiz responses (no linked patient)
```

---

## Required HIPAA Safeguards

### Administrative Safeguards

- [ ] **BAA Required:** Sign a Business Associate Agreement with every clinic before they go live
  > **Phase A:** `baa_signed_at` is recorded in-app; optional signed PDF on private disk; intake POSTs can be blocked until a date exists (`SECURITY_REQUIRE_BAA_FOR_INTAKE`). Full e-sign workflow still backlog.
- [ ] **Access Management:** Role-based access control — minimum necessary access principle ✅ Implemented
- [ ] **Workforce Training:** All staff with PHI access complete annual HIPAA training
- [ ] **Incident Response Plan:** Documented breach notification procedure (60-day DHHS notice) — see Breach Response section below
- [ ] **Risk Analysis:** Annual security risk assessment documented

### Physical Safeguards

- [ ] **Data Centers:** AWS HIPAA-eligible services only (RDS, S3, ECS, Rekognition, KMS, SES)
- [ ] **Workstation Policy:** Developers with PHI access use encrypted devices
- [ ] **No Local PHI Storage:** PHI never stored on developer laptops or local environments

### Technical Safeguards

- [x] **Encryption at Rest:** AES-256 for all PHI columns + S3 SSE-KMS encryption ✅
- [x] **Encryption in Transit:** TLS 1.3 minimum on all connections ✅
- [x] **Access Controls:** Role-based access, automatic session timeout (30 min) ✅
- [x] **Audit Controls:** All PHI access logged via `audit_log_entries` (append-only) ✅
- [x] **Integrity Controls:** Photo hashes stored to detect tampering ✅
- [x] **Transmission Security:** 15-min signed URLs for photos, HMAC for webhooks ✅
- [x] **HTTP Security Headers:** HSTS (production HTTPS), X-Content-Type-Options, Referrer-Policy, Permissions-Policy; CSP on non-intake routes; intake CSP via `AllowFramesMiddleware` ✅

---

## Encryption Implementation

### PHI Column Encryption (Application Layer)

```php
// All PHI columns use Laravel's encrypted casting:
protected $casts = [
    'name_encrypted'  => 'encrypted',
    'email_encrypted' => 'encrypted',
    'phone_encrypted' => 'encrypted',
];

// WHY: Even if the database is compromised, PHI columns are unreadable
// without the application's APP_KEY (stored in environment secrets).

// Search/deduplication strategy (avoids decrypting for lookups):
// - name_hash  = hash_hmac('sha256', mb_strtolower($name), config('app.key'))
// - email_hash = hash_hmac('sha256', mb_strtolower($email), config('app.key'))
// Hashes allow exact-match deduplication without exposing the plaintext value.
```

### S3 Photo Storage

```
Bucket: symetrihealth-patient-photos-{env}
Region: us-east-1 (HIPAA-eligible region)
Encryption: SSE-KMS (AWS managed key, dedicated per environment)
Access: All public access blocked
Versioning: Enabled (integrity + recovery)
Lifecycle: 7-year retention (HIPAA requires 6 years for medical records)
Path: {tenant_id}/{patient_id}/{evaluation_id}/{type}_{timestamp}.jpg

Access method:
  ✓ Pre-signed URLs with 15-minute expiry (AWS Signature V4)
  ✗ NEVER direct S3 URLs
  ✗ NEVER public bucket access
  ✗ NEVER store signed URLs in the database
```

---

## Access Control Matrix

| Role | Patient PII | Photos | Quiz Answers | AI Analysis | Lead Score | Audit Log |
|------|-------------|--------|--------------|-------------|------------|-----------|
| Patient (own) | Read | Upload/Read | Read/Write | Read | No | No |
| Coordinator | Read | Read | Read | Read | Read | No |
| Surgeon | **No PII** | Read | Read | Read | No | No |
| Admin | Read | Read | Read | Read | Read | Read |
| Owner | Read | Read | Read | Read | Read | Full |
| SymetriHealth Staff | No | No | No | No | No | Emergency only |

**Surgeon cannot see PII** — they only receive clinical data in the brief. This is intentional: surgeons assess clinical findings, not patient identities.

---

## Threat Model

| Threat | Likelihood | Impact | Mitigation | Status |
|--------|-----------|--------|-----------|--------|
| Cross-tenant data access bug | Medium | Critical | Global Scopes + RLS + tests | ✅ |
| S3 bucket misconfiguration | Low | Critical | All-public-access blocked | ✅ |
| SQL injection | Low | High | Eloquent ORM, parameterized queries | ✅ |
| Coordinator credential theft | Medium | Medium | Mandatory auth step: TOTP or email OTP fallback + session timeout | Residual risk reduced; email channel security still matters |
| Webhook payload interception | Low | Medium | HMAC signing, HTTPS only, no PHI in payload | ✅ |
| Malicious photo upload | Medium | Medium | File type + size validation, Rekognition | ⚠️ No virus scan |
| AI model data exfiltration | Low | High | No PHI passed to external LLMs unredacted | ✅ |
| Prompt injection via quiz | Low | Low | Quiz answers sanitized before AI processing | ✅ |
| Insider threat | Low | Critical | Audit logs, minimum access, no single actor sees all PHI | ✅ |
| Session hijacking | Low | High | HttpOnly + Secure cookies, CSRF protection | ✅ |
| Clickjacking | Low | Medium | X-Frame-Options / CSP frame-ancestors | ✅ App middleware + intake allowlist |
| Missing security headers | Medium | Medium | HSTS, X-Content-Type-Options, Referrer-Policy | ✅ `SecurityHeadersMiddleware` |
| Abuse of unthrottled public routes | Medium | Low–Medium | Throttle magic link, widget loader, API | ⚠️ Gaps remain |

---

## Security Coding Standards

### Never Do These

```php
// ❌ NEVER: Log PHI
Log::info('Patient created', ['email' => $patient->email_encrypted]);

// ❌ NEVER: Return PHI in error messages
return response()->json(['error' => "Patient {$patient->name} not found"], 404);

// ❌ NEVER: Store PHI in URLs
redirect("/dashboard?patient_email={$email}");

// ❌ NEVER: Query without tenant scope (outside of explicit admin contexts)
Patient::withoutGlobalScope('tenant')->get();

// ❌ NEVER: Raw S3 paths in API responses
return ['photo_url' => "s3://bucket/path/to/photo.jpg"];

// ❌ NEVER: PHI in webhook payloads
$payload = ['patient_email' => $patient->email]; // Use tokens instead
```

```typescript
// ❌ NEVER: Log PHI in browser console
console.log('Patient data:', patient);

// ❌ NEVER: Store PHI in localStorage or sessionStorage
localStorage.setItem('patientPhone', phone);

// ❌ NEVER: Include PHI in analytics events
analytics.track('evaluation_completed', { email: patient.email });
```

### Always Do These

```php
// ✅ ALWAYS: Log only safe identifiers
Log::info('Patient created', ['patient_id' => $patient->id, 'tenant_id' => $patient->tenant_id]);

// ✅ ALWAYS: Audit log PHI access
$this->auditLog->record('patient.profile.viewed', $patient);

// ✅ ALWAYS: Return signed URLs, never S3 keys
return ['photo_url' => $this->secureFiles->getSignedUrl($photo->s3_key)];

// ✅ ALWAYS: Use encrypted casts for PHI columns
protected $casts = ['email_encrypted' => 'encrypted'];

// ✅ ALWAYS: Scope queries to the current tenant
// (GlobalScope handles this automatically — don't bypass without justification)
```

---

## Rate Limiting

Throttling is applied to protect against abuse and enumeration. **Values below reflect the current codebase** (`AppServiceProvider`, `FortifyServiceProvider`, `routes/web.php`).

### Implemented (verified)

| Limiter / route | Limit | Key | Source |
|-----------------|-------|-----|--------|
| `intake.evaluation.create` | 3 per 10 min | IP | `POST .../intake/evaluations` |
| `intake.evaluation.submit` | 3 per hour | IP | `POST .../intake/evaluations/{token}/submit` |
| `intake.photos` | 15 per 10 min | evaluation token + IP | `POST .../intake/evaluations/{token}/photos` |
| `intake.quiz` | 30 per minute | token + IP | `POST .../intake/evaluations/{token}/quiz` |
| `intake.lead` | 10 per minute | token + IP | `POST .../intake/evaluations/{token}/lead` |
| `access-requests` | 5 per hour | IP | `POST /access-requests` |
| `affiliate.click` | 60 per minute | link id + IP | affiliate tracking routes |
| `affiliate.portal` | 60 per minute | portal token + IP | `GET /affiliate-portal/...` |
| Fortify `login` | 5 per minute | email + IP | `FortifyServiceProvider` |
| Fortify `two-factor` | 5 per minute | session login id | `FortifyServiceProvider` |
| Settings (sample) | 6 per minute | — | `routes/settings.php` |

All 429 responses must **not** reveal exact rate limit values or internal identifiers.

### Not implemented (backlog)

| Surface | Suggested direction |
|---------|---------------------|
| `GET /magic/{token}` | Add dedicated `RateLimiter` (e.g. per IP + per token prefix) — mitigates enumeration |
| `GET /widget.js` | Throttle by IP (and optionally per-referrer) — mitigates scraping / cost abuse |
| `routes/api.php` `v1` | Per-token or per-IP limits on authenticated API routes |
| `GET .../intake` (wizard load) | Optional: very relaxed limiter if HTML scraping becomes an issue |

---

## HTTP Security Headers

> **Implemented:** `SecurityHeadersMiddleware` is appended to the `web` middleware group in `bootstrap/app.php`. Intake routes (`intake.*`) skip CSP and `X-Frame-Options` here so `AllowFramesMiddleware` can emit a tenant-specific CSP. Edge/CDN may add additional headers — document those in deployment runbooks.

Non-intake responses include the following (via middleware), with **exceptions** for routes that must be framed (see `AllowFramesMiddleware`).

```
Strict-Transport-Security: max-age=63072000; includeSubDomains; preload
X-Content-Type-Options: nosniff
X-Frame-Options: DENY   (or omit where CSP frame-ancestors applies)
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: camera=(), microphone=(), geolocation=()
```

### Content Security Policy

```
Content-Security-Policy:
  default-src 'self';
  script-src 'self';
  style-src 'self' 'unsafe-inline';
  img-src 'self' data: blob: https://*.amazonaws.com;
  media-src blob:;
  connect-src 'self' wss://*.symetrihealth.com;
  frame-ancestors 'none';
  upgrade-insecure-requests;
```

**Embed widget pages** (iFrame context) must **not** use `frame-ancestors *`. Use a per-tenant allowlist of clinic origins stored in tenant settings, and fall back to `'none'` when empty.

> **Intake:** `AllowFramesMiddleware` sets a **full** CSP (including `frame-ancestors` from tenant allowlist or `'none'`). It removes `X-Frame-Options` so framing is governed solely by CSP.

---

## Two-Factor Authentication

2FA is implemented via Laravel Fortify (TOTP). Owner/Admin require authenticator app setup for dashboard access. Coordinators require either authenticator app setup or a short-lived email OTP fallback.

> ℹ️ Implementation: `RequirePrivilegedTwoFactor` enforces the gate on tenant dashboard routes. Coordinator fallback is handled by `CoordinatorEmailOtpController` and `CoordinatorEmailOtpMail`.

Current state:

- TOTP setup available via Settings → Security ✅
- QR code + recovery codes generated ✅
- 2FA challenge on login when enabled ✅
- Enforcement for privileged roles: ❌ Not implemented

---

## Third-Party Subprocessors

All subprocessors that may handle PHI must have a signed BAA.

| Service | Purpose | BAA Required | PHI Exposure | Notes |
|---------|---------|-------------|-------------|-------|
| AWS (RDS, S3, ECS, SES, KMS, Rekognition) | Core infrastructure + AI | ✅ | Photos, encrypted PHI | HIPAA-eligible services only |
| Twilio | SMS notifications | ✅ | Phone number | A2P 10DLC registered |
| Stripe | Clinic subscription billing | ✅ | No patient PHI | Clinic billing data only |
| Sentry | Error monitoring | ✅ | Must scrub PHI from traces | Configure scrubbing; avoid user email in scope — see P2 |
| Laravel Reverb | Real-time WebSocket server | N/A (self-hosted) | Eval tokens only, no PHI | Replaces Pusher — no BAA needed |

> **Note:** Pusher is no longer used. Reverb is self-hosted — no third-party PHI exposure on WebSocket events.

> **Rule:** Before adding any new third-party service that could touch PHI, verify BAA availability and get explicit approval from the security reviewer.

---

## BAA Tracking

**Phase A (implemented):** Super-admins set `baa_signed_at` and may upload an optional signed PDF (`baa_document_path`, encrypted path on the `local` disk). When `SECURITY_REQUIRE_BAA_FOR_INTAKE` is true, patient intake **mutations** (evaluation create, quiz, lead, submit, photos) return 403 until a BAA date exists.

**Still backlog:** E-sign (e.g. BoldSign), S3-stored document URL if you move off local disk, and any “tenant activation” workflow beyond the intake gate.

---

## Breach Response Procedure

**If a potential PHI breach is identified:**

1. **Immediate (< 1 hour)**
   - Isolate the affected system or account
   - Document what was potentially exposed and to whom
   - Notify CTO immediately

2. **Short-term (< 24 hours)**
   - Complete initial risk assessment
   - Determine if breach meets HIPAA notification threshold
   - Engage legal counsel

3. **If Reportable Breach**
   - Notify affected clinics within 48 hours
   - Affected clinics must notify individual patients within 60 days
   - Submit report to HHS Breach Reporting Portal within 60 days
   - Document in breach log

4. **Post-Incident**
   - Root cause analysis
   - Control improvements
   - Retrain relevant staff

---

## Security Checklist for Code Reviews

Before merging any PR that touches patient data:

- [ ] PHI columns use encrypted casts
- [ ] No PHI in log statements
- [ ] All PHI access is audit-logged with `$this->auditLog->record()`
- [ ] File access uses signed URLs (not raw S3 paths)
- [ ] All endpoints require authentication
- [ ] Tenant scope applied to all queries (no `withoutGlobalScope` without justification)
- [ ] No PHI in webhook payloads (reference tokens only)
- [ ] Input validation on all patient-submitted data
- [ ] No sensitive data in URL parameters
- [ ] Error responses don't leak PHI or internal paths
- [ ] New third-party services reviewed for BAA requirement

---

## P1 backlog — ship before broad production / full HIPAA operational readiness

These items are the highest-impact **product and compliance** gaps visible in code today:

### 1. Enforce 2FA for Owner / Admin / Coordinator

**Risk:** Credential theft grants full dashboard access to PHI.  
**Done:** `RequirePrivilegedTwoFactor` middleware (`SECURITY_REQUIRE_2FA_PRIVILEGED_ROLES`) for tenant privileged roles + `RequireSuperAdminTwoFactor` (`SECURITY_REQUIRE_2FA_SUPER_ADMIN`) for `/admin/*`.

### 2. BAA tracking on `tenants`

**Risk:** Clinics may go live without administrative evidence of a BAA — audit and HIPAA program gap.  
**Phase A done:** migration, super-admin UI, intake gate via env. **Follow-up:** e-sign integration and stronger “go live” semantics if needed.

---

## P2 backlog — important hardening

### 5. Rate limits for magic link, widget script, and REST API v1

**Risk:** Enumeration, scraping, noisy neighbor cost.  
**Done:** `magic-link`, `widget-loader`, and `api.v1` named `RateLimiter`s + route middleware.  
**Follow-up:** Tune thresholds from production traffic patterns.

### 6. Virus scanning on photo uploads

**Risk:** Malware disguised as images in object storage.  
**Fix:** ClamAV, Lambda on S3 `PutObject`, or equivalent — quarantine before linking file to evaluation.

### 7. Sentry scrubbing and scope minimization

**Risk:** Identifiers in error telemetry.  
**Done:** Removed email from Sentry user scope; added `before_send` and `before_breadcrumb` scrubbing callbacks for common PHI/sensitive fields.  
**Follow-up:** Keep scrub field list current as payload schemas evolve. If client-side Sentry is enabled in `resources/js/app.tsx`, apply equivalent client scrubbing.

---

## Recommended implementation order (practical)

Use this when prioritizing engineering time. Order weighs **exploitability**, **blast radius**, and **dependencies**.

1. **Require 2FA for privileged roles** — ✅ Implemented for tenant owner / admin / coordinator on dashboard routes.
2. **BAA fields + admin workflow** — Phase A done; e-sign / automation optional follow-up.
3. **Throttle magic links + `widget.js` + API v1** — Reduces abuse noise before it becomes operational pain.
4. **Sentry scope + `beforeSend`** — Prevents accidental identifier leakage in error reports.
5. **Virus scanning** — Important for storage integrity; often deployed after core app hardening unless threat model prioritizes malware.

---

## Changelog (document)

| Date | Change |
|------|--------|
| 2026-04-18 | Code verification pass: updated rate-limit table, Sentry notes, P1/P2 scope, recommended order |
