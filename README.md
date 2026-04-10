# ROADMAP.md — Development Phases

> Phased plan from MVP to full platform. Each phase has clear exit criteria before the next begins.

---

## Implementation Status

> Legend: ✅ Done · 🚧 In Progress · ⬜ Not Started · 🚫 Non-dev (business task)

### Sprint Summary (Updated April 2026)

**Sprint 1 — Core Infrastructure:** ✅ **Complete** **Sprint 2 — Patient Intake Wizard:** ✅ **Complete** **Sprint 2 (Extended) — Clinic Dashboard (Sprint 4 scope):** ✅ **Complete** **Sprint 3 — AI Pipeline:** ✅ **Complete** **Sprint 5 — Polish + Pilot Launch:** ✅ **Complete** (all dev items done; business items pending BAA + QA)

---

## Phase Overview

```
Phase 1 — MVP (Months 1–3)
  "Prove the concept. One procedure. One clinic. Real leads."

Phase 2 — Foundation (Months 4–6)
  "Multi-procedure. Multi-tenant. CRM integrations. Billing."

Phase 3 — Intelligence (Months 7–10)
  "Full AI suite. Simulations. Expand to 5+ clinics."

Phase 4 — Scale (Months 11–18)
  "50+ clinics. White-label. Partner API."
```

---

## Phase 1 — MVP

**Theme:** Prove that AI-guided intake converts better than a contact form.

**Target:** One pilot clinic (Miami Life Cosmetic Center), one procedure (Rhinoplasty).

**Exit Criteria:** 50 completed evaluations, measurable improvement in lead-to-consult conversion rate.

---

### P1 Sprint 1 — Core Infrastructure (Weeks 1–2) ✅ COMPLETE

**Backend:**

-    Laravel 12 project scaffold with strict types (`declare(strict_types=1)` everywhere)
-    PostgreSQL + migrations for: `tenants`, `patients`, `evaluations`, `photos`, `audit_log_entries`, `quiz_definitions`, `procedures`
-    `BelongsToTenant` trait + `TenantContext` service (singleton, Facade)
-    `TenantMiddleware` — subdomain resolution (`miamilife.aesthetic-ai.test`)
-    `TenantScope` — Global Scope applied to all tenant-owned models (Evaluation, Patient, Photo, etc.)
-    `AuditLog::record()` service — append-only HIPAA audit log
-    `SecureFileService` — S3 upload (local disk in dev), KMS in production, signed URL generation (15-min expiry)
-    PHI encryption — all patient columns use Laravel `encrypted` cast (AES-256-GCM)
-    Email/name hash columns for deduplication without decryption
-    Laravel Horizon + Redis queue setup (Horizon dashboard at `/horizon`)
-    Wayfinder — typed TypeScript route generation (`php artisan wayfinder:generate`)

**DevOps:**

-    GitHub repo established, branch protection configured
-    GitHub Actions CI: PHPStan level 8, Pest tests, TypeScript `tsc --noEmit` *(not configured yet)*
-    Staging environment on AWS *(using local dev — `aesthetic-ai.test`)*
-    `.env` secrets management via AWS Secrets Manager *(local `.env` in dev)*

**Models & Seeders:**

-    `Tenant`, `User`, `Patient`, `Evaluation`, `Photo`, `AuditLogEntry`, `QuizDefinition`, `Procedure` models
-    `DatabaseSeeder` — Miami Life tenant, 5 procedures (Rhinoplasty, Liposuction 360, BBL, Breast Augmentation, Facelift), Rhinoplasty quiz with 8 questions + branching logic
-    Evaluation statuses: draft → submitted → analyzing → complete → contacted → booked → no_show → not_a_fit → failed

---

### P1 Sprint 2 — Patient Intake Wizard (Weeks 3–5) ✅ COMPLETE

**Frontend — Patient Portal (`/intake/`):**

-    Mobile-first wizard shell (`WizardShell.tsx`) with animated progress bar
-    Luxury dark design system: `#0A0A0F` bg, `#C9A84C` gold, `#F5F0E8` cream, `#9B9B8E` muted
-    Step 1: Procedure selection — cards with category badges (Face / Body)
-    Step 2 (integrated): Quiz — dynamic question engine, 8 Rhinoplasty questions
    -    Question types: `boolean`, `single`, `multi`, `text`
    -    Quiz branching — `skipToOnTrue`, `skipToOnFalse`, `skipToAlways` pre-resolved on backend (DB branches → array indices)
    -    All branching tested end-to-end (e.g. Q2 "Prior rhinoplasty?" → skips details if No)
-    Step 3: Photo capture — camera permission flow, angle overlays, Front/Left/Right required, quality score display
-    Step 4: Contact info (name, email, phone)
-    Step 5: Consent + submission (HIPAA ack, terms, photo use consent, timestamp)
-    Success screen with "What happens next" explanation

**Backend — Intake API (JSON, not Inertia):**

-    `POST /intake/evaluations` — create draft evaluation + stub patient
-    `POST /intake/evaluations/{token}/quiz` — save quiz answers, advance status
-    `POST /intake/evaluations/{token}/photos` — upload photo to S3, store encrypted key, return signed URL
-    `POST /intake/evaluations/{token}/submit` — upsert patient PHI (encrypted), record consent in quiz_answers, advance to `analyzing`
-    `ProcedureResource` — transforms `photo_protocol` array and quiz question shape for frontend contract
-    `UploadPhotoRequest`, `CreateEvaluationRequest`, `SaveQuizRequest`, `SubmitEvaluationRequest` form requests

**Bugs Fixed During Testing:**

-    `quiz_definitions.questions[].id` → mapped to `key` for frontend; `select/multiselect` → `single/multi`
-    `photo_protocol` DB array → `{required: [], optional: []}` transform in `ProcedureResource`
-    Controller returned `evaluation_token` but frontend read `token` → fixed key name
-    `SecureFileService::putFileAs()` used wrong named param `directory:` → fixed to positional
-    `temporaryUrl()` not supported on local disk → added fallback to `url()` in dev
-    `PhotoController` response shape `{photo_id, status}` → corrected to `{id, analysis_status, signed_url}`

---

### P1 Sprint 2 (Extended) — Clinic Dashboard + Settings ✅ COMPLETE

> *These items were originally planned for Sprint 4 but pulled forward to complete Sprint 1–2 before starting the AI pipeline.*

**Coordinator Dashboard:**

-    `GET /evaluations` — priority queue (urgent → high → medium → standard), paginated, status filter tabs
-    `GET /evaluations/{id}` — full detail: patient PHI, photos gallery (lightbox), quiz answers, AI analysis
-    `PATCH /evaluations/{id}/status` — update to contacted/booked/no_show/not_a_fit
-    `PATCH /evaluations/{id}/notes` — save coordinator notes + follow_up_at date
-    `EvaluationResource` — PHI auto-decrypted, photos with signed URLs, lead score, analysis_data
-    `resources/js/pages/evaluations/index.tsx` — stat pills, tab bar, sortable table, pagination
-    `resources/js/pages/evaluations/show.tsx` — patient card, photos gallery, quiz answers, coordinator panel

**Clinic Settings & Team Management:**

-    `GET /clinic/settings` — edit page: name, theme, procedures_enabled, coordinator_emails, webhook_url
-    `PATCH /clinic/settings` — persist settings to tenant model
-    `GET /clinic/team` — list all team members with roles
-    `POST /clinic/team` — invite new user (coordinator/admin/surgeon/viewer)
-    `DELETE /clinic/team/{user}` — remove team member (cannot remove self)
-    `resources/js/pages/clinic/settings.tsx` — General / Procedures / Notifications / Integrations sections
-    `resources/js/pages/clinic/team.tsx` — role badges, invite form, remove button

**Navigation:**

-    `AppSidebar` updated — "Evaluations" (ClipboardList icon) + "Clinic" section (Settings + Team)
-    All routes use Wayfinder typed functions (no hardcoded URL strings)

---

### P1 Sprint 3 — Basic AI Pipeline (Weeks 6–8) ✅ COMPLETE

**AI Jobs (Laravel Queue — Horizon):**

-    `ValidatePhotoQualityJob` — Rekognition face detect + quality score; simulation mode for dev (`FEATURE_AI_VISION=false`)
-    `ExtractFacialLandmarksJob` — Rekognition `DetectFaces` → 28 landmark points stored in `analysis_data.landmarks`; realistic mock in dev
-    `CalculateProportionsJob` — facial thirds, fifths, nasal symmetry, Goode's ratio, nasal width ratio, eye symmetry, overall harmony score (pure geometry, no API)
-    `GenerateBasicRecommendationsJob` — rule-based recommendations from quiz + proportions; calls `LeadScoringService`, dispatches notification
-    Jobs chained via `Bus::chain()` in `EvaluationController::submit()`; cancellable on photo validation failure
-    `config/features.php` — feature flags for `ai_vision`, `lead_scoring`, `notifications`

**Lead Scoring:**

-    `LeadScoringService` — 100-point weighted score: timeline 30%, budget 25%, AI harmony 20%, photo quality 10%, concerns 10%, referral 5%
-    Priority tiers: Urgent (80+) / High (60–79) / Medium (40–59) / Standard (<40)
-    Auto-boost: revision rhinoplasty or functional component → +1 tier
-    Force-upgrade: budget ≥ $15k + timeline ≤ 3 months → minimum High

**Notifications:**

-    `NotifyClinicNewEvaluationJob` — sends to `coordinator_emails` from tenant settings (falls back to coordinator/owner users)
-    `NewEvaluationMail` — Mailable with priority-tagged subject line
-    `resources/views/emails/new-evaluation.blade.php` — luxury dark HTML email with lead score, priority badge, patient first name, CTA button
-    `AuditLog::recordSystem()` — queue-safe audit logging (no HTTP context required)
-    Magic link — `MagicLink::generate($evaluation, $recipientEmail)` per coordinator
-    `MagicLinkController` — validates SHA-256 token, logs in matching User, redirects to evaluation with audit trail
-    `GET /magic/{token}` route (outside auth middleware, inside tenant middleware)
-    `PruneMagicLinksCommand` — `php artisan magic-links:prune`, scheduled hourly
-    Migration `add_recipient_email_to_magic_links_table` — enables per-recipient user resolution

---

### P1 Sprint 4 — Clinic Dashboard MVP (Weeks 9–11) ✅ COMPLETE (pulled into Sprint 2)

> *Delivered early as part of completing Sprint 1–2 before starting the AI pipeline.*

**Clinic Dashboard:**

-    Login flow (email + password via Laravel Fortify)
-    Evaluation priority queue — sorted by priority then lead score then date
-    Status filter tabs: Active / Analyzing / Complete / Contacted / Booked
-    Evaluation detail page with patient PHI, photos gallery, quiz answers, coordinator notes
-    Mark as: Contacted / Booked / No-Show / Not a Fit
-    Coordinator notes + follow-up date

**Security:**

-    All coordinator routes behind `auth + verified + tenant` middleware
-    Audit log visible in evaluation detail (deferred timeline with user, action, IP, timestamp)
-    Session timeout after 30 minutes of inactivity — `useSessionTimeout` hook + warning dialog + `/keepalive` route

---

### P1 Sprint 5 — Polish + Pilot Launch (Weeks 12–13)

**Dev items:**

-    Analytics dashboard — `AnalyticsController` with `Inertia::defer()` for all 5 metrics (weeklyVolume, statusFunnel, scoreDistrib, priorityBreakdown, avgTimeToContact)
-    Analytics React page (`resources/js/pages/analytics/index.tsx`) with skeleton loaders
-    Analytics sidebar nav entry + Wayfinder route helper
-    Monitoring: Sentry — server-side `SentryContextServiceProvider` (user + tenant context on every error) + client-side `@sentry/react` init in `app.tsx` with `browserTracingIntegration`
-    Clinical Brief PDF — `ClinicalBriefService`, `ClinicalBriefController`, `pdf.clinical-brief` Blade template, download button in evaluation detail
-    Clinical Brief auto-attached to coordinator notification email (`NewEvaluationMail`)
-    HIPAA session timeout — 30-min inactivity timer with warning dialog + `/keepalive` endpoint
-    HIPAA audit log timeline — `AuditTimeline` component on evaluation detail page
-    TypeScript strict — `tsc --noEmit` passes with zero errors
-    Test suite — 102 tests, all passing (`ClinicalBriefTest`, `AnalyticsTest` + all prior suites)
-    **Funnel drop-off tracking** — `funnel_step` on evaluations (1–4), analytics `intakeFunnel` deferred prop, `IntakeFunnelChart` React component
-    **CloudWatch alerts** — CPU/memory/queue-depth alarms for production deploy *(infrastructure — defer to deploy)*
-    **Session timeout after 30 min** — `SESSION_LIFETIME=30` in `.env.example`; must be set in production env

**Business items (non-dev — coordinate with clinic):**

-   🚫 End-to-end QA (full flow on iPhone, Android, desktop)
-   🚫 HIPAA internal review checklist
-   🚫 BAA signed with pilot clinic
-   🚫 Patient-facing copy review (medical accuracy, tone)
-   🚫 Coordinator training session (30 min)
-   🚫 Soft launch: Add intake widget to one page on clinic website

---

## Phase 2 — Foundation

**Theme:** Multi-procedure, multi-tenant, revenue model live.

**Duration:** Months 4–6

**Exit Criteria:** 3 paying clinics, 5 procedure types, webhook sync to one external CRM.

### Key Deliverables

**Multi-Procedure Expansion:** ✅ *Delivered early (Phase 1 Sprint 5)*

-    Quiz engine supports multiple procedure definitions (JSONB config)
-    Procedure library: Rhinoplasty, Liposuction 360, BBL, Breast Augmentation, Facelift — all with branching quizzes
-    Procedure-specific photo capture protocols (body vs. face angles)
-    Tenant settings UI — checkbox grid grouped by Face/Body to enable/disable procedures per clinic
-    Anatomical 3D pin-drop interface (Phase 2 enhancement of chief concern step)

**Multi-Tenant Onboarding:**

-    Tenant self-registration flow (sign up → configure → embed)
-    Tenant settings: logo, colors, procedures offered, coordinator emails
-    Custom domain support (CNAME → AestheticAI)
-    Subdomain provisioning automation

**Billing:**

-    Stripe integration (subscription plans)
-    Plans: Starter (1 procedure, 50 evals/mo) → Growth (5 procedures, 200/mo) → Pro (unlimited)
-    Usage metering (evaluations counted per billing period)
-    Invoice generation + dunning management

**CRM Integration — Phase 2:**

-    Generic webhook system (signed payload)
-    HubSpot native integration (contact creation + property sync)
-    Nextech webhook (lead creation)
-    Webhook delivery log + retry UI in dashboard

**Clinical Brief Generator:** ✅ *Delivered in Sprint 5*

-    PDF generation of pre-consultation brief (`ClinicalBriefService`, spatie/laravel-pdf)
-    Printable format for surgeon's physical files (download button on evaluation detail)
-    Auto-attached to clinic notification email (`NewEvaluationMail`)

---

## Phase 3 — Intelligence

**Theme:** Full AI suite. Visible results. Premium differentiation.

**Duration:** Months 7–10

### Key Deliverables

**Advanced AI Vision:**

-    Skin laxity estimation from photo (proxy metric via texture analysis)
-    Age estimation from visual features (cross-referenced with quiz age input)
-    Improved procedure matching using ML model (trained on historical outcomes)
-    Body analysis for Liposuction / BBL (body landmark detection)

**AI Simulation (High Impact Feature):**

-    "Potential Results" overlay using generative AI
-    Morphing preview for Rhinoplasty (realistic, not cartoonish)
-    Breast augmentation size comparison view
-    IMPORTANT: Clear labeling — "AI Visualization — Not a Guarantee"
-    Results stored alongside evaluation, shareable via secure link

**Patient Experience Enhancements:**

-    Beauty Roadmap PDF — personalized report for patient
    -   Their proportion analysis results
    -   AI-recommended procedures with explanations
    -   Educational content about each procedure
    -   FAQ specific to their concerns
-    Patient portal: check their evaluation status
-    Patient portal: book consultation directly from their portal

**Analytics Dashboard:**

-    Clinic conversion funnel metrics
-    Lead score vs. actual booking rate correlation
-    Procedure mix breakdown
-    Month-over-month comparison

---

## Phase 4 — Scale

**Theme:** 50+ clinics. White-label. Partner API.

**Duration:** Months 11–18

### Key Deliverables

**White-Label Program:**

-    Full rebranding capability (logo, colors, domain, email sender)
-    Reseller program for medical marketing agencies
-    Reseller dashboard: manage multiple clinic accounts

**Partner API:**

-    Public API documentation (OpenAPI spec)
-    SDK: JavaScript, PHP, Python
-    API key management per tenant
-    Rate limiting and usage dashboards

**PatientNow Integration:**

-    Deep sync: evaluation → PatientNow patient record
-    Two-way: consultation status synced back to AestheticAI
-    Photo transfer (HIPAA-compliant, with patient consent)

**ML Model Improvements:**

-    Train proprietary recommendation model on anonymized outcome data
-    A/B test: rule-based vs. ML recommendations
-    Outcome tracking: book, show, convert → feeds model training

**Enterprise Features:**

-    Multi-location support (one clinic group, multiple locations)
-    Role hierarchy: Group Admin → Location Admin → Coordinator
-    Consolidated reporting across locations
-    Enterprise SSO (SAML 2.0)

---

## Backlog (Future Consideration)

-   Mobile native apps (iOS + Android) for photo capture
-   Telemedicine integration (video consultation scheduling)
-   Insurance eligibility pre-check (for reconstructive procedures)
-   Multilingual support (Spanish — critical for Miami market)
-   AI chatbot for pre-evaluation patient questions
-   Integration with practice management software (ModMed, Kareo)
-   Outcome photography tracking (before + after comparison)
-   Surgeon outcome portfolio (anonymized, for trust-building)

---

## Metrics to Track

Metric

MVP Target

Phase 2 Target

Evaluation completion rate

> 60%

> 70%

Lead-to-consult conversion

+20% vs. contact form

+35%

Coordinator time-to-call

< 30 min for High/Urgent

< 15 min

No-show rate

-15% vs. baseline

-25%

Photo quality pass rate

> 80%

> 90%

AI recommendation accuracy

N/A (rule-based)

> 75% match