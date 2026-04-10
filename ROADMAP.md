# ROADMAP.md — Development Phases

> Phased plan from MVP to full platform. Each phase has clear exit criteria before the next begins.

---

## Production Environment

| | |
|---|---|
| **Production URL** | https://aesthai.laravel.cloud/ |
| **Platform** | Laravel Cloud |
| **Deployed** | April 2026 |
| **Local dev URL** | https://aesthetic-ai.test (Laravel Herd) |

> When testing webhooks in production, set the webhook URL in Clinic Settings to your CRM endpoint and verify the `X-AestheticAI-Signature` header using the tenant's `webhook_secret`.
> For smoke-testing the intake wizard against production, use the tenant subdomain once configured (e.g. `https://miamilife.aesthai.laravel.cloud/intake`).

---

## Implementation Status

> Legend: ✅ Done · 🚧 In Progress · ⬜ Not Started · 🚫 Non-dev (business task)

### Sprint Summary (Updated April 2026)

**Sprint 1 — Core Infrastructure:** ✅ **Complete**
**Sprint 2 — Patient Intake Wizard:** ✅ **Complete**
**Sprint 2 (Extended) — Clinic Dashboard (Sprint 4 scope):** ✅ **Complete**
**Sprint 3 — AI Pipeline:** ✅ **Complete**
**Sprint 5 — Polish + Pilot Launch:** ✅ **Complete** (all dev items done; business items pending BAA + QA)

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
- [x] Laravel 12 project scaffold with strict types (`declare(strict_types=1)` everywhere)
- [x] PostgreSQL + migrations for: `tenants`, `patients`, `evaluations`, `photos`, `audit_log_entries`, `quiz_definitions`, `procedures`
- [x] `BelongsToTenant` trait + `TenantContext` service (singleton, Facade)
- [x] `TenantMiddleware` — subdomain resolution (`miamilife.aesthetic-ai.test`)
- [x] `TenantScope` — Global Scope applied to all tenant-owned models (Evaluation, Patient, Photo, etc.)
- [x] `AuditLog::record()` service — append-only HIPAA audit log
- [x] `SecureFileService` — S3 upload (local disk in dev), KMS in production, signed URL generation (15-min expiry)
- [x] PHI encryption — all patient columns use Laravel `encrypted` cast (AES-256-GCM)
- [x] Email/name hash columns for deduplication without decryption
- [x] Laravel Horizon + Redis queue setup (Horizon dashboard at `/horizon`)
- [x] Wayfinder — typed TypeScript route generation (`php artisan wayfinder:generate`)

**DevOps:**
- [x] GitHub repo established, branch protection configured
- [ ] GitHub Actions CI: PHPStan level 8, Pest tests, TypeScript `tsc --noEmit` *(not configured yet)*
- [ ] Staging environment on AWS *(using local dev — `aesthetic-ai.test`)*
- [ ] `.env` secrets management via AWS Secrets Manager *(local `.env` in dev)*

**Models & Seeders:**
- [x] `Tenant`, `User`, `Patient`, `Evaluation`, `Photo`, `AuditLogEntry`, `QuizDefinition`, `Procedure` models
- [x] `DatabaseSeeder` — Miami Life tenant, 5 procedures (Rhinoplasty, Liposuction 360, BBL, Breast Augmentation, Facelift), Rhinoplasty quiz with 8 questions + branching logic
- [x] Evaluation statuses: draft → submitted → analyzing → complete → contacted → booked → no_show → not_a_fit → failed

---

### P1 Sprint 2 — Patient Intake Wizard (Weeks 3–5) ✅ COMPLETE

**Frontend — Patient Portal (`/intake/`):**
- [x] Mobile-first wizard shell (`WizardShell.tsx`) with animated progress bar
- [x] Luxury dark design system: `#0A0A0F` bg, `#C9A84C` gold, `#F5F0E8` cream, `#9B9B8E` muted
- [x] Step 1: Procedure selection — cards with category badges (Face / Body)
- [x] Step 2 (integrated): Quiz — dynamic question engine, 8 Rhinoplasty questions
  - [x] Question types: `boolean`, `single`, `multi`, `text`
  - [x] Quiz branching — `skipToOnTrue`, `skipToOnFalse`, `skipToAlways` pre-resolved on backend (DB branches → array indices)
  - [x] All branching tested end-to-end (e.g. Q2 "Prior rhinoplasty?" → skips details if No)
- [x] Step 3: Photo capture — camera permission flow, angle overlays, Front/Left/Right required, quality score display
- [x] Step 4: Contact info (name, email, phone)
- [x] Step 5: Consent + submission (HIPAA ack, terms, photo use consent, timestamp)
- [x] Success screen with "What happens next" explanation

**Backend — Intake API (JSON, not Inertia):**
- [x] `POST /intake/evaluations` — create draft evaluation + stub patient
- [x] `POST /intake/evaluations/{token}/quiz` — save quiz answers, advance status
- [x] `POST /intake/evaluations/{token}/photos` — upload photo to S3, store encrypted key, return signed URL
- [x] `POST /intake/evaluations/{token}/submit` — upsert patient PHI (encrypted), record consent in quiz_answers, advance to `analyzing`
- [x] `ProcedureResource` — transforms `photo_protocol` array and quiz question shape for frontend contract
- [x] `UploadPhotoRequest`, `CreateEvaluationRequest`, `SaveQuizRequest`, `SubmitEvaluationRequest` form requests

**Bugs Fixed During Testing:**
- [x] `quiz_definitions.questions[].id` → mapped to `key` for frontend; `select/multiselect` → `single/multi`
- [x] `photo_protocol` DB array → `{required: [], optional: []}` transform in `ProcedureResource`
- [x] Controller returned `evaluation_token` but frontend read `token` → fixed key name
- [x] `SecureFileService::putFileAs()` used wrong named param `directory:` → fixed to positional
- [x] `temporaryUrl()` not supported on local disk → added fallback to `url()` in dev
- [x] `PhotoController` response shape `{photo_id, status}` → corrected to `{id, analysis_status, signed_url}`

---

### P1 Sprint 2 (Extended) — Clinic Dashboard + Settings ✅ COMPLETE

> *These items were originally planned for Sprint 4 but pulled forward to complete Sprint 1–2 before starting the AI pipeline.*

**Coordinator Dashboard:**
- [x] `GET /evaluations` — priority queue (urgent → high → medium → standard), paginated, status filter tabs
- [x] `GET /evaluations/{id}` — full detail: patient PHI, photos gallery (lightbox), quiz answers, AI analysis
- [x] `PATCH /evaluations/{id}/status` — update to contacted/booked/no_show/not_a_fit
- [x] `PATCH /evaluations/{id}/notes` — save coordinator notes + follow_up_at date
- [x] `EvaluationResource` — PHI auto-decrypted, photos with signed URLs, lead score, analysis_data
- [x] `resources/js/pages/evaluations/index.tsx` — stat pills, tab bar, sortable table, pagination
- [x] `resources/js/pages/evaluations/show.tsx` — patient card, photos gallery, quiz answers, coordinator panel

**Clinic Settings & Team Management:**
- [x] `GET /clinic/settings` — edit page: name, theme, procedures_enabled, coordinator_emails, webhook_url
- [x] `PATCH /clinic/settings` — persist settings to tenant model
- [x] `GET /clinic/team` — list all team members with roles
- [x] `POST /clinic/team` — invite new user (coordinator/admin/surgeon/viewer)
- [x] `DELETE /clinic/team/{user}` — remove team member (cannot remove self)
- [x] `resources/js/pages/clinic/settings.tsx` — General / Procedures / Notifications / Integrations sections
- [x] `resources/js/pages/clinic/team.tsx` — role badges, invite form, remove button

**Navigation:**
- [x] `AppSidebar` updated — "Evaluations" (ClipboardList icon) + "Clinic" section (Settings + Team)
- [x] All routes use Wayfinder typed functions (no hardcoded URL strings)

---

### P1 Sprint 3 — Basic AI Pipeline (Weeks 6–8) ✅ COMPLETE

**AI Jobs (Laravel Queue — Horizon):**
- [x] `ValidatePhotoQualityJob` — Rekognition face detect + quality score; simulation mode for dev (`FEATURE_AI_VISION=false`)
- [x] `ExtractFacialLandmarksJob` — Rekognition `DetectFaces` → 28 landmark points stored in `analysis_data.landmarks`; realistic mock in dev
- [x] `CalculateProportionsJob` — facial thirds, fifths, nasal symmetry, Goode's ratio, nasal width ratio, eye symmetry, overall harmony score (pure geometry, no API)
- [x] `GenerateBasicRecommendationsJob` — rule-based recommendations from quiz + proportions; calls `LeadScoringService`, dispatches notification
- [x] Jobs chained via `Bus::chain()` in `EvaluationController::submit()`; cancellable on photo validation failure
- [x] `config/features.php` — feature flags for `ai_vision`, `lead_scoring`, `notifications`

**Lead Scoring:**
- [x] `LeadScoringService` — 100-point weighted score: timeline 30%, budget 25%, AI harmony 20%, photo quality 10%, concerns 10%, referral 5%
- [x] Priority tiers: Urgent (80+) / High (60–79) / Medium (40–59) / Standard (<40)
- [x] Auto-boost: revision rhinoplasty or functional component → +1 tier
- [x] Force-upgrade: budget ≥ $15k + timeline ≤ 3 months → minimum High

**Notifications:**
- [x] `NotifyClinicNewEvaluationJob` — sends to `coordinator_emails` from tenant settings (falls back to coordinator/owner users)
- [x] `NewEvaluationMail` — Mailable with priority-tagged subject line
- [x] `resources/views/emails/new-evaluation.blade.php` — luxury dark HTML email with lead score, priority badge, patient first name, CTA button
- [x] `AuditLog::recordSystem()` — queue-safe audit logging (no HTTP context required)
- [x] Magic link — `MagicLink::generate($evaluation, $recipientEmail)` per coordinator
- [x] `MagicLinkController` — validates SHA-256 token, logs in matching User, redirects to evaluation with audit trail
- [x] `GET /magic/{token}` route (outside auth middleware, inside tenant middleware)
- [x] `PruneMagicLinksCommand` — `php artisan magic-links:prune`, scheduled hourly
- [x] Migration `add_recipient_email_to_magic_links_table` — enables per-recipient user resolution

---

### P1 Sprint 4 — Clinic Dashboard MVP (Weeks 9–11) ✅ COMPLETE (pulled into Sprint 2)

> *Delivered early as part of completing Sprint 1–2 before starting the AI pipeline.*

**Clinic Dashboard:**
- [x] Login flow (email + password via Laravel Fortify)
- [x] Evaluation priority queue — sorted by priority then lead score then date
- [x] Status filter tabs: Active / Analyzing / Complete / Contacted / Booked
- [x] Evaluation detail page with patient PHI, photos gallery, quiz answers, coordinator notes
- [x] Mark as: Contacted / Booked / No-Show / Not a Fit
- [x] Coordinator notes + follow-up date

**Security:**
- [x] All coordinator routes behind `auth + verified + tenant` middleware
- [x] Audit log visible in evaluation detail (deferred timeline with user, action, IP, timestamp)
- [x] Session timeout after 30 minutes of inactivity — `useSessionTimeout` hook + warning dialog + `/keepalive` route

---

### P1 Sprint 5 — Polish + Pilot Launch (Weeks 12–13)

**Dev items:**
- [x] Analytics dashboard — `AnalyticsController` with `Inertia::defer()` for all 5 metrics (weeklyVolume, statusFunnel, scoreDistrib, priorityBreakdown, avgTimeToContact)
- [x] Analytics React page (`resources/js/pages/analytics/index.tsx`) with skeleton loaders
- [x] Analytics sidebar nav entry + Wayfinder route helper
- [x] Monitoring: Sentry — server-side `SentryContextServiceProvider` (user + tenant context on every error) + client-side `@sentry/react` init in `app.tsx` with `browserTracingIntegration`
- [x] Clinical Brief PDF — `ClinicalBriefService`, `ClinicalBriefController`, `pdf.clinical-brief` Blade template, download button in evaluation detail
- [x] Clinical Brief auto-attached to coordinator notification email (`NewEvaluationMail`)
- [x] HIPAA session timeout — 30-min inactivity timer with warning dialog + `/keepalive` endpoint
- [x] HIPAA audit log timeline — `AuditTimeline` component on evaluation detail page
- [x] TypeScript strict — `tsc --noEmit` passes with zero errors
- [x] Test suite — 102 tests, all passing (`ClinicalBriefTest`, `AnalyticsTest` + all prior suites)
- [x] **Funnel drop-off tracking** — `funnel_step` on evaluations (1–4), analytics `intakeFunnel` deferred prop, `IntakeFunnelChart` React component
- [ ] **CloudWatch alerts** — CPU/memory/queue-depth alarms for production deploy *(infrastructure — defer to deploy)*
- [x] **Session timeout after 30 min** — `SESSION_LIFETIME=30` in `.env.example`; must be set in production env

**Business items (non-dev — coordinate with clinic):**
- 🚫 End-to-end QA (full flow on iPhone, Android, desktop)
- 🚫 HIPAA internal review checklist
- 🚫 BAA signed with pilot clinic
- 🚫 Patient-facing copy review (medical accuracy, tone)
- 🚫 Coordinator training session (30 min)
- 🚫 Soft launch: Add intake widget to one page on clinic website

---

## Phase 2 — Foundation

**Theme:** Multi-procedure, multi-tenant, revenue model live.

**Duration:** Months 4–6

**Exit Criteria:** 3 paying clinics, 5 procedure types, webhook sync to one external CRM.

### Key Deliverables

**Multi-Procedure Expansion:** ✅ *Delivered early (Phase 1 Sprint 5)*
- [x] Quiz engine supports multiple procedure definitions (JSONB config)
- [x] Procedure library: Rhinoplasty, Liposuction 360, BBL, Breast Augmentation, Facelift — all with branching quizzes
- [x] Procedure-specific photo capture protocols (body vs. face angles)
- [x] Tenant settings UI — checkbox grid grouped by Face/Body to enable/disable procedures per clinic
- [ ] Anatomical 3D pin-drop interface (Phase 2 enhancement of chief concern step)

**Multi-Tenant Onboarding:**
- [ ] Tenant self-registration flow (sign up → configure → embed)
- [ ] Tenant settings: logo, colors, procedures offered, coordinator emails
- [ ] Custom domain support (CNAME → AestheticAI)
- [ ] Subdomain provisioning automation

**Billing:**
- [ ] Stripe integration (subscription plans)
- [ ] Plans: Starter (1 procedure, 50 evals/mo) → Growth (5 procedures, 200/mo) → Pro (unlimited)
- [ ] Usage metering (evaluations counted per billing period)
- [ ] Invoice generation + dunning management

**CRM Integration — Phase 2:**
- [ ] Generic webhook system (signed payload)
- [ ] HubSpot native integration (contact creation + property sync)
- [ ] Nextech webhook (lead creation)
- [ ] Webhook delivery log + retry UI in dashboard

**Clinical Brief Generator:** ✅ *Delivered in Sprint 5*
- [x] PDF generation of pre-consultation brief (`ClinicalBriefService`, spatie/laravel-pdf)
- [x] Printable format for surgeon's physical files (download button on evaluation detail)
- [x] Auto-attached to clinic notification email (`NewEvaluationMail`)

---

## Phase 3 — Intelligence

**Theme:** Full AI suite. Visible results. Premium differentiation.

**Duration:** Months 7–10

### Key Deliverables

**Advanced AI Vision:**
- [ ] Skin laxity estimation from photo (proxy metric via texture analysis)
- [ ] Age estimation from visual features (cross-referenced with quiz age input)
- [ ] Improved procedure matching using ML model (trained on historical outcomes)
- [ ] Body analysis for Liposuction / BBL (body landmark detection)

**AI Simulation (High Impact Feature):**
- [ ] "Potential Results" overlay using generative AI
- [ ] Morphing preview for Rhinoplasty (realistic, not cartoonish)
- [ ] Breast augmentation size comparison view
- [ ] IMPORTANT: Clear labeling — "AI Visualization — Not a Guarantee"
- [ ] Results stored alongside evaluation, shareable via secure link

**Patient Experience Enhancements:**
- [x] Beauty Roadmap PDF — personalized report for patient ✅ *Phase 3 Sprint 1*
  - [x] Harmony score + label (Excellent / Very Good / Good / Moderate)
  - [x] Proportion measurement highlights (symmetry, Goode's ratio, photo quality)
  - [x] Patient-friendly key insights derived from AI recommendations + quiz answers
  - [x] Dynamic FAQs per procedure and concern selections (capped at 6 items)
  - [x] Next steps + medical AI disclaimer
  - [x] Emailed to patient after analysis completes (`SendPatientReportJob` on notifications queue)
  - [x] PDF attached to email + secure download link via `GET /intake/evaluations/{token}/report`
  - [x] `PatientReportService`, `PatientReportMail`, `PatientReportController`, `SendPatientReportJob`
  - [x] 13 tests in `PatientReportTest.php`
- [ ] Patient portal: check their evaluation status
- [ ] Patient portal: book consultation directly from their portal

**Analytics Dashboard:**
- [ ] Clinic conversion funnel metrics
- [ ] Lead score vs. actual booking rate correlation
- [ ] Procedure mix breakdown
- [ ] Month-over-month comparison

---

## Phase 4 — Scale

**Theme:** 50+ clinics. White-label. Partner API.

**Duration:** Months 11–18

### Key Deliverables

**White-Label Program:**
- [ ] Full rebranding capability (logo, colors, domain, email sender)
- [ ] Reseller program for medical marketing agencies
- [ ] Reseller dashboard: manage multiple clinic accounts

**Partner API:**
- [ ] Public API documentation (OpenAPI spec)
- [ ] SDK: JavaScript, PHP, Python
- [ ] API key management per tenant
- [ ] Rate limiting and usage dashboards

**PatientNow Integration:**
- [ ] Deep sync: evaluation → PatientNow patient record
- [ ] Two-way: consultation status synced back to AestheticAI
- [ ] Photo transfer (HIPAA-compliant, with patient consent)

**ML Model Improvements:**
- [ ] Train proprietary recommendation model on anonymized outcome data
- [ ] A/B test: rule-based vs. ML recommendations
- [ ] Outcome tracking: book, show, convert → feeds model training

**Enterprise Features:**
- [ ] Multi-location support (one clinic group, multiple locations)
- [ ] Role hierarchy: Group Admin → Location Admin → Coordinator
- [ ] Consolidated reporting across locations
- [ ] Enterprise SSO (SAML 2.0)

---

## Backlog (Future Consideration)

- Mobile native apps (iOS + Android) for photo capture
- Telemedicine integration (video consultation scheduling)
- Insurance eligibility pre-check (for reconstructive procedures)
- Multilingual support (Spanish — critical for Miami market)
- AI chatbot for pre-evaluation patient questions
- Integration with practice management software (ModMed, Kareo)
- Outcome photography tracking (before + after comparison)
- Surgeon outcome portfolio (anonymized, for trust-building)

---

## Metrics to Track

| Metric | MVP Target | Phase 2 Target |
|--------|-----------|----------------|
| Evaluation completion rate | > 60% | > 70% |
| Lead-to-consult conversion | +20% vs. contact form | +35% |
| Coordinator time-to-call | < 30 min for High/Urgent | < 15 min |
| No-show rate | -15% vs. baseline | -25% |
| Photo quality pass rate | > 80% | > 90% |
| AI recommendation accuracy | N/A (rule-based) | > 75% match 