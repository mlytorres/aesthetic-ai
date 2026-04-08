# SECURITY.md — HIPAA Compliance & Security Controls

> This document defines the security architecture, HIPAA compliance controls, and threat model for AestheticAI. All developers must read this before contributing.

---

## HIPAA Compliance Overview

AestheticAI handles Protected Health Information (PHI) as defined by HIPAA. The platform acts as a **Business Associate** to covered entities (plastic surgery clinics).

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
- [ ] **Access Management:** Role-based access control — minimum necessary access principle
- [ ] **Workforce Training:** All staff with PHI access complete annual HIPAA training
- [ ] **Incident Response Plan:** Documented breach notification procedure (60-day DHHS notice)
- [ ] **Risk Analysis:** Annual security risk assessment documented

### Physical Safeguards

- [ ] **Data Centers:** AWS HIPAA-eligible services only (RDS, S3, ECS, etc.)
- [ ] **Workstation Policy:** Developers with PHI access use encrypted devices
- [ ] **No Local PHI Storage:** PHI never stored on developer laptops or local environments

### Technical Safeguards

- [ ] **Encryption at Rest:** AES-256 for all PHI columns + S3 KMS encryption
- [ ] **Encryption in Transit:** TLS 1.3 minimum on all connections
- [ ] **Access Controls:** Unique user IDs, automatic session timeout (30 min)
- [ ] **Audit Controls:** All PHI access logged via `audit_log_entries` (immutable)
- [ ] **Integrity Controls:** Photo hashes stored to detect tampering
- [ ] **Transmission Security:** Signed URLs for photo access, HMAC for webhooks

---

## Encryption Implementation

### PHI Column Encryption (Application Layer)

```php
// All PHI columns use encrypted casting:
class Patient extends Model
{
    protected $casts = [
        'email'  => EncryptedString::class,
        'phone'  => EncryptedString::class,
        'dob'    => EncryptedDate::class,
        // name stored separately — see note below
    ];
}

// WHY: Even if the database is compromised, PHI columns are unreadable
// without the application's encryption key (stored in AWS Secrets Manager).

// Name storage strategy:
// - Full name: Encrypted column (email notifications only)
// - Search: Bloom filter or name_hash for deduplication without exposing name
// - Display: Decrypted in memory only, never cached
```

### S3 Photo Storage

```
Bucket: aestheticai-patient-photos-{env}
Region: us-east-1 (HIPAA compliant region)
Encryption: SSE-KMS (AWS managed keys, dedicated key per environment)
Access: No public access (all blocks enabled)
Versioning: Enabled (for integrity)
Lifecycle: 7 years retention (HIPAA requires 6 years for medical records)
Path: {tenant_id}/{patient_id}/{evaluation_id}/{type}_{timestamp}.jpg

Access method:
  ✓ Pre-signed URLs with 15-minute expiry
  ✓ Signed with AWS Signature V4
  ✗ NEVER direct S3 URLs
  ✗ NEVER public bucket access
  ✗ NEVER store signed URLs in database
```

---

## Access Control Matrix

| Role | Patient PII | Photos | Quiz Answers | AI Analysis | Lead Score | Audit Log |
|------|-------------|--------|--------------|-------------|------------|-----------|
| Patient (own) | Read | Upload/Read | Read/Write | Read | No | No |
| Coordinator | Read | Read | Read | Read | Read | No |
| Surgeon | No | Read | Read | Read | No | No |
| Admin | Read | Read | Read | Read | Read | Read |
| Owner | Read | Read | Read | Read | Read | Full |
| AestheticAI Staff | No | No | No | No | No | Emergency only |

**Surgeon cannot see PII** — they only receive clinical data in the brief. This is intentional: surgeons don't need names/emails to do clinical review.

---

## Threat Model

### Threats and Mitigations

| Threat | Likelihood | Impact | Mitigation |
|--------|-----------|--------|-----------|
| Cross-tenant data access bug | Medium | Critical | Double isolation (app scope + RLS), tests |
| S3 bucket misconfiguration | Low | Critical | All-public-access blocked, automated checks |
| SQL injection | Low | High | Eloquent ORM, parameterized queries always |
| Coordinator credential theft | Medium | High | TOTP optional, magic link for patients, session timeout |
| Webhook payload interception | Low | Medium | HMAC signing, HTTPS only, no PHI in payload |
| AI model data exfiltration | Low | High | No PHI in ML model training, anonymized datasets |
| Malicious photo upload | Medium | Medium | File type validation, size limits, virus scan |
| Prompt injection via quiz | Low | Low | Quiz answers never passed to LLMs without sanitization |
| Insider threat | Low | Critical | Audit logs, minimum access principle, no one sees all PHI |

---

## Security Coding Standards

### Never Do These

```php
// ❌ NEVER: Log PHI
Log::info('Patient created', ['email' => $patient->email]);

// ❌ NEVER: Return PHI in error messages
return response()->json(['error' => "Patient {$patient->name} not found"], 404);

// ❌ NEVER: Store PHI in URLs
redirect("/dashboard?patient_email={$email}");

// ❌ NEVER: Query without tenant scope
Patient::withoutGlobalScope('tenant')->get(); // Only allowed in explicit admin contexts

// ❌ NEVER: Raw file paths in API responses
return ['photo_url' => "s3://bucket/path/to/photo.jpg"];
```

```typescript
// ❌ NEVER: Log PHI in browser console
console.log('Patient data:', patient);

// ❌ NEVER: Store PHI in localStorage
localStorage.setItem('patientPhone', phone);

// ❌ NEVER: Include PHI in analytics events
analytics.track('evaluation_completed', { email: patient.email });
```

### Always Do These

```php
// ✅ ALWAYS: Log only safe identifiers
Log::info('Patient created', ['patient_id' => $patient->id, 'tenant_id' => $patient->tenant_id]);

// ✅ ALWAYS: Audit log PHI access
AuditLog::record('patient.profile.viewed', $patient);

// ✅ ALWAYS: Return signed URLs, never S3 keys
return ['photo_url' => $this->secureFiles->getSignedUrl($photo->s3_key)];

// ✅ ALWAYS: Use encrypted casts for PHI columns
protected $casts = ['email' => EncryptedString::class];
```

---

## Breach Response Procedure

**If a potential PHI breach is identified:**

1. **Immediate (< 1 hour):**
   - Isolate affected system/account
   - Document what was potentially exposed and to whom
   - Notify CTO

2. **Short-term (< 24 hours):**
   - Complete initial risk assessment
   - Determine if breach meets HIPAA notification threshold
   - Legal counsel review

3. **If Reportable Breach:**
   - Notify affected clinics within 48 hours
   - Affected clinics must notify individual patients within 60 days
   - Submit to DHHS (HHS Breach Reporting Portal) within 60 days
   - Document in breach log

4. **Post-Incident:**
   - Root cause analysis
   - Control improvements
   - Retrain relevant staff

---

## Security Checklist for Code Reviews

Before merging any PR that touches patient data:

- [ ] PHI columns use encrypted casts
- [ ] No PHI in log statements
- [ ] All PHI access is audit-logged with `AuditLog::record()`
- [ ] File access uses signed URLs (not raw S3 paths)
- [ ] All endpoints require authentication
- [ ] Tenant scope applied to all queries (no `withoutGlobalScope` without justification)
- [ ] No PHI in webhook payloads (reference tokens only)
- [ ] Input validation on all patient-submitted data
- [ ] No sensitive data in URL parameters
- [ ] Error responses don't leak PHI or internal paths

---

## HTTP Security Headers

All responses from the AestheticAI application must include the following headers. These are enforced at the CloudFront/ALB layer and verified in middleware.

```
Strict-Transport-Security: max-age=63072000; includeSubDomains; preload
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
Referrer-Policy: strict-origin-when-cross-origin
Content-Security-Policy: (see below)
```

### Content Security Policy (CSP)

```
Content-Security-Policy:
  default-src 'self';
  script-src 'self' https://cdn.aestheticai.com;
  style-src 'self' 'unsafe-inline';
  img-src 'self' data: blob: https://*.amazonaws.com;
  media-src blob:;
  connect-src 'self' https://app.aestheticai.com https://rekognition.us-east-1.amazonaws.com;
  frame-ancestors 'none';
  upgrade-insecure-requests;
```

**Embed widget pages** (iFrame context) relax `frame-ancestors` to allow clinic domains:
```
Content-Security-Policy: frame-ancestors https://*.aestheticai.com [clinic-allowlisted-domains]
```

---

## CORS Policy

The embed widget loads from `app.aestheticai.com` but is embedded on clinic-owned domains. CORS is handled as follows:

| Endpoint | Allowed Origins | Credentials |
|----------|----------------|-------------|
| `/embed/*` (widget load) | Any (public) | No |
| `/api/evaluations` | Allowlisted clinic domains + `*.aestheticai.com` | Yes |
| `/api/v1/*` (REST API) | `*.aestheticai.com` only | Yes |
| `/webhooks/*` | None (server-to-server only) | No |

**Implementation — Laravel:**
```php
// config/cors.php
'paths' => ['api/*', 'embed/*'],
'allowed_origins_patterns' => [
    '#^https://[a-z0-9-]+\.aestheticai\.com$#',
    // Clinic custom domains added dynamically per tenant settings
],
'allowed_methods' => ['GET', 'POST', 'PATCH'],
'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With'],
'exposed_headers' => [],
'max_age' => 3600,
'supports_credentials' => true,
```

---

## Rate Limiting

Throttling is applied at multiple levels to protect against abuse and enumeration attacks.

| Endpoint Group | Limit | Window | Response |
|---------------|-------|--------|----------|
| Widget embed load | 60 req | per IP/min | 429 |
| Evaluation create | 5 req | per IP/15 min | 429 |
| Photo upload | 10 req | per evaluation | 429 |
| Magic link request | 3 req | per IP/hour | 429 |
| Coordinator login | 10 attempts | per IP/15 min | 429 + lockout |
| REST API (authenticated) | 300 req | per token/min | 429 |
| Webhook delivery (inbound) | 100 req | per tenant/min | 429 |

**Implementation:**
```php
// routes/api.php
Route::middleware(['throttle:evaluation-create'])->group(function () {
    Route::post('/evaluations', [EvaluationController::class, 'store']);
});

// app/Providers/RouteServiceProvider.php
RateLimiter::for('evaluation-create', function (Request $request) {
    return Limit::perMinutes(15, 5)
        ->by($request->ip())
        ->response(fn() => response()->json(['error' => 'Too many submissions. Please try again later.'], 429));
});
```

All 429 responses must **not** reveal the exact rate limit values or internal identifiers.

---

## Third-Party Subprocessors

All subprocessors that may handle PHI must have a signed BAA. Current subprocessors:

| Service | Purpose | HIPAA BAA | PHI Exposure |
|---------|---------|-----------|-------------|
| AWS (RDS, S3, ECS, SES, KMS, Rekognition) | Core infrastructure + AI | ✅ Required | Photos, encrypted PHI |
| SendGrid | Transactional email | ✅ Required | Name, email in notifications |
| Twilio | SMS notifications | ✅ Required | Phone number |
| Pusher / Laravel Echo | Real-time dashboard updates | ⚠️ Minimal PHI — only eval tokens | Reference tokens only |
| Sentry | Error monitoring | ✅ Required | Must be configured to scrub PHI from stack traces |

**Developer note:** Before adding any new third-party service that could touch PHI, get explicit approval from the security reviewer and verify BAA availability.

---

## Compliance Checklist

**Before launching any new clinic:**

- [ ] BAA signed and stored
- [ ] Clinic coordinator trained on their HIPAA obligations
- [ ] Webhook endpoint security reviewed (must be HTTPS)
- [ ] Tenant configuration audited (procedure list, coordinator access)
- [ ] Penetration test completed (annual requirement post-Phase 2)
