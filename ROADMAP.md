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
| **Admin panel** | https://aesthetic-ai.test/admin (super-admin only) |
| **Pilot clinic (local)** | https://miamilife.aesthetic-ai.test |
| **Pilot clinic (prod)** | https://miamilife.symetrihealth.com |

> **Production APP_URL:** Must be set to `https://symetrihealth.com` — TenantMiddleware resolves tenants from subdomains of this domain (e.g. `miamilife.symetrihealth.com` → slug `miamilife`).
> When testing webhooks in production, set the webhook URL in Clinic Settings to your CRM endpoint and verify the `X-AestheticAI-Signature` header using the tenant's `webhook_secret`.

---

## Implementation Status

> Legend: ✅ Done · 🚧 In Progress · ⬜ Not Started · 🚫 Non-dev (business task)

### Sprint Summary (Updated April 2026)

**Sprint 1 — Core Infrastructure:** ✅ **Complete**
**Sprint 2 — Patient Intake Wizard:** ✅ **Complete**
**Sprint 2 (Extended) — Clinic Dashboard (Sprint 4 scope):** ✅ **Complete**
**Sprint 3 — AI Pipeline:** ✅ **Complete**
**Sprint 5 — Polish + Pilot Launch:** ✅ **Complete** (all dev items done; business items pending BAA + QA)
**Sprint 6 — Advanced AI Vision + Analytics:** ✅ **Complete**
**Sprint 7 — Multi-Tenant Platform Admin:** ✅ **Complete**
**Sprint 8 — Body Landmarks + AI Simulation:** ✅ **Complete**
**Sprint 9 — Billing, Notifications, Webhooks, Security:** ✅ **Complete**
**Sprint 10 — White-Label, SMS, Multilingual & Usage Alerts:** ✅ **Complete**

---

## Phase Overview

```
Phase 1 — MVP (Months 1–3)          ✅ COMPLETE
  "Prove the concept. One procedure. One clinic. Real leads."

Phase 2 — Foundation (Months 4–6)   🚧 IN PROGRESS
  "Multi-procedure. Multi-tenant. CRM integrations. Billing."

Phase 3 — Intelligence (Months 7–10) 🚧 IN PROGRESS (Analytics + AI Vision + Body Landmarks + Simulation shipped early)
  "Full AI suite. Simulations. Expand to 5+ clinics."

Phase 4 — Scale (Months 11–18)      ⬜ NOT STARTED
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
- [x] GitHub Actions CI: Pest tests (`tests.yml` — PHP 8.3/8.4/8.5 matrix), Pint + ESLint + Prettier + `tsc --noEmit` (`lint.yml`)
- [ ] Staging environment on AWS *(using local dev — `aesthetic-ai.test`)*
- [ ] `.env` secrets management via AWS Secrets Manager *(local `.env` in dev)*

**Models & Seeders:**
- [x] `Tenant`, `User`, `Patient`, `Evaluation`, `Photo`, `AuditLogEntry`, `QuizDefinition`, `Procedure` models
- [x] `DatabaseSeeder` — Miami Life tenant, 5 procedures, Rhinoplasty quiz with 8 questions + branching logic
- [x] `DatabaseSeeder` — Platform super-admin (`admin@aesthetic-ai.test`) with `tenant_id = null`
- [x] Evaluation statuses: draft → submitted → analyzing → complete → contacted → booked → no_show → not_a_fit → failed

---

### P1 Sprint 2 — Patient Intake Wizard (Weeks 3–5) ✅ COMPLETE

**Frontend — Patient Portal (`/intake/`):**
- [x] Mobile-first wizard shell (`WizardShell.tsx`) with animated progress bar
- [x] Luxury dark design system: `#0A0A0F` bg, `#C9A84C` gold, `#F5F0E8` cream, `#9B9B8E` muted
- [x] Step 1: Procedure selection — cards with category badges (Face / Body)
- [x] Step 2 (integrated): Quiz — dynamic question engine, 8 Rhinoplasty questions
  - [x] Question types: `boolean`, `single`, `multi`, `text`
  - [x] Quiz branching — `skipToOnTrue`, `skipToOnFalse`, `skipToAlways` pre-resolved on backend
  - [x] All branching tested end-to-end
- [x] Step 3: Photo capture — camera permission flow, angle overlays, Front/Left/Right required, quality score display
- [x] Step 4: Contact info (name, email, phone)
- [x] Step 5: Consent + submission (HIPAA ack, terms, photo use consent, timestamp)
- [x] Success screen with "What happens next" explanation

**Backend — Intake API (JSON, not Inertia):**
- [x] `POST /intake/evaluations` — create draft evaluation + stub patient
- [x] `POST /intake/evaluations/{token}/quiz` — save quiz answers, advance status
- [x] `POST /intake/evaluations/{token}/photos` — upload photo to S3, store encrypted key, return signed URL
- [x] `POST /intake/evaluations/{token}/submit` — upsert patient PHI (encrypted), record consent, advance to `analyzing`
- [x] `ProcedureResource`, form requests, validation

---

### P1 Sprint 2 (Extended) — Clinic Dashboard + Settings ✅ COMPLETE

**Coordinator Dashboard:**
- [x] `GET /evaluations` — priority queue, paginated, status filter tabs
- [x] `GET /evaluations/{id}` — full detail: patient PHI, photos gallery, quiz answers, AI analysis
- [x] `PATCH /evaluations/{id}/status` + `PATCH /evaluations/{id}/notes`
- [x] `EvaluationResource` — PHI auto-decrypted, photos with signed URLs, lead score, analysis_data

**Clinic Settings & Team Management:**
- [x] `GET/PATCH /clinic/settings` — name, theme, procedures_enabled, coordinator_emails, webhook_url
- [x] `GET /clinic/team` + `POST /clinic/team` + `DELETE /clinic/team/{user}`
- [x] Full Inertia React pages for settings and team management

**Navigation:**
- [x] `AppSidebar` with "Clinic" section (Settings + Team + Webhooks)
- [x] All routes use Wayfinder typed functions

---

### P1 Sprint 3 — Basic AI Pipeline (Weeks 6–8) ✅ COMPLETE

**AI Jobs (Laravel Queue — Horizon):**
- [x] `ValidatePhotoQualityJob` — Rekognition face detect + quality score; simulation mode for dev
- [x] `ExtractFacialLandmarksJob` — Rekognition `DetectFaces` → 28 landmark points
- [x] `CalculateProportionsJob` — facial thirds, fifths, nasal symmetry, Goode's ratio, overall harmony score
- [x] `GenerateBasicRecommendationsJob` — rule-based recommendations + `LeadScoringService`
- [x] Jobs chained via `Bus::chain()` in `EvaluationController::submit()`

**Lead Scoring:**
- [x] `LeadScoringService` — 100-point weighted score (timeline 30%, budget 25%, AI harmony 20%, photo quality 10%, concerns 10%, referral 5%)
- [x] Priority tiers: Urgent (80+) / High (60–79) / Medium (40–59) / Standard (<40)
- [x] Auto-boost: revision rhinoplasty or functional component → +1 tier
- [x] Force-upgrade: budget ≥ $15k + timeline ≤ 3 months → minimum High

**Notifications:**
- [x] `NotifyClinicNewEvaluationJob` → `NewEvaluationMail` (priority-tagged subject, luxury dark HTML, magic link CTA)
- [x] `AuditLog::recordSystem()` — queue-safe audit logging
- [x] Magic link — per-coordinator SHA-256 token, 15-min expiry, auto-login
- [x] `PruneMagicLinksCommand` — scheduled hourly

---

### P1 Sprint 4 — Clinic Dashboard MVP (Weeks 9–11) ✅ COMPLETE (pulled into Sprint 2)

- [x] Login flow (email + password via Laravel Fortify)
- [x] Evaluation priority queue — sorted by priority → lead score → date
- [x] Coordinator notes + follow-up date
- [x] Audit log visible in evaluation detail (deferred timeline)
- [x] Session timeout after 30 minutes of inactivity (`useSessionTimeout` hook + warning dialog)

---

### P1 Sprint 5 — Polish + Pilot Launch (Weeks 12–13) ✅ COMPLETE

- [x] Analytics dashboard — `AnalyticsController` with `Inertia::defer()` for all metrics
- [x] Sentry — server-side `SentryContextServiceProvider` + client-side `@sentry/react`
- [x] Clinical Brief PDF — `ClinicalBriefService`, Blade template, auto-attached to coordinator email
- [x] HIPAA session timeout + keepalive endpoint
- [x] HIPAA audit log timeline — `AuditTimeline` component on evaluation detail
- [x] TypeScript strict — `tsc --noEmit` passes with zero errors
- [x] Funnel drop-off tracking — `funnel_step` on evaluations + `intakeFunnel` deferred prop + `IntakeFunnelChart`
- [x] Test suite — 184 tests, all passing
- [ ] **CloudWatch alerts** — CPU/memory/queue-depth alarms *(defer to production deploy)*

**Business items (non-dev):**
- 🚫 End-to-end QA (iPhone, Android, desktop)
- 🚫 HIPAA internal review checklist
- 🚫 BAA signed with pilot clinic
- 🚫 Patient-facing copy review
- 🚫 Coordinator training session
- 🚫 Soft launch: intake widget on clinic website

---

### P1 Sprint 6 — Advanced AI Vision + Analytics Enhancements ✅ COMPLETE

> *Phase 3 AI Vision and Phase 3 Analytics items delivered early.*

**Advanced AI Vision (procedure-specific):**
- [x] `ExtractFacialLandmarksJob` — per-photo `_face_attributes` (age_range, photo_quality, pose, confidence)
- [x] Rhinoplasty AI flags: revision detection, functional_component, nasal_asymmetry_detected
- [x] BBL AI flags: `bbl_safety_protocol_required` (always), weight stability check, donor areas
- [x] Lipo 360 AI flags: `skin_laxity_concern` (from photo quality proxy metric)
- [x] Breast Augmentation AI flags: revision_breast_surgery, large_volume_request, lift_consideration
- [x] Facelift AI flags: young_facelift_candidate (<40), mature_facelift_candidate (≥60), deep-plane technique note, smoker_high_risk, estimated_age from Rekognition
- [x] All AI Vision procedures covered end-to-end with realistic simulation in dev
- [x] 22 tests in `AIVisionTest.php`

**Analytics Dashboard Enhancements:**
- [x] `monthOverMonth()` — current vs. previous calendar month for evaluations, avg lead score, bookings (with delta indicators)
- [x] `procedureMix()` — per-procedure volume and booking rate, ordered by volume
- [x] `scoreVsBooking()` — booking conversion rate per lead score bucket (0–19, 20–39, 40–59, 60–79, 80–100)
- [x] `Delta` component — emerald/red colored delta indicators
- [x] `DualBarChart` component — volume bar + booking rate bar per procedure row
- [x] 15 tests in `AnalyticsEnhancementTest.php`

**Beauty Roadmap PDF (patient-facing):**
- [x] `PatientReportService` — harmony score + label, proportion highlights, key insights, dynamic FAQs, next steps
- [x] `PatientReportMail` — emailed to patient after analysis completes
- [x] `SendPatientReportJob` — dispatched on notifications queue
- [x] `GET /intake/evaluations/{token}/report` — secure download via evaluation token (no auth required)
- [x] 13 tests in `PatientReportTest.php`

---

### P1 Sprint 8 — Body Landmarks + AI Simulation ✅ COMPLETE

**Body Landmark Detection (body procedure variant):**
- [x] `ExtractBodyLandmarksJob` — 11 front landmarks (shoulders, waist, hips, thighs, abdomen) + 5 side landmarks (gluteal peak, gluteal base, lower-back curve, shoulder, upper abdomen); procedure-aware coordinate variance (BBL → wider hips, Lipo → more abdominal projection)
- [x] `CalculateBodyProportionsJob` — WHR (waist/hip, ideal 0.70), shoulder-waist ratio (ideal 1.40), gluteal projection, abdominal projection, bilateral symmetry score, skin laxity integration, overall contour score (weighted composite 0–100); WHR label: Hourglass / Pear / Rectangular / Apple
- [x] Pipeline branching in `EvaluationController::submit()` — body procedures route to body jobs; face procedures keep facial jobs
- [x] Simulation mode — full landmark + proportion pipeline works without AWS (all values deterministically simulated from quiz answers)
- [x] 12 tests in `BodyLandmarkTest.php`

**AI Before/After Simulation:**
- [x] `OpenAIService` — `editImage()` + `generateImage()` wrappers for `gpt-image-1`; registered as singleton in `AppServiceProvider`
- [x] `GenerateSimulationJob` — builds procedure-specific prompts incorporating proportion scores; calls OpenAI; stores PNG to S3/local; placeholder mode when `FEATURE_AI_VISION=false`
- [x] `SimulationController` — `POST /evaluations/{id}/simulation` (request) + `GET /evaluations/{id}/simulation` (poll); duplicate-request guard; signed S3 URL on completion
- [x] `SimulationViewer` React component — gated by body procedure + analysis complete; spinner with 4-second polling; result image or dev placeholder; "AI Visualization — Not a Guarantee" disclaimer; regenerate button
- [x] Migration — `simulation_status`, `simulation_data`, `simulation_requested_at` columns on evaluations
- [x] `OPENAI_API_KEY` added to `.env.example` and `config/services.php`
- [x] 11 tests in `AISimulationTest.php`

---

### P1 Sprint 7 — Multi-Tenant Platform Admin ✅ COMPLETE

> *Phase 2 multi-tenant onboarding items delivered.*

**Super-Admin Architecture:**
- [x] Super-admin pattern: `User.tenant_id = null` → platform operator (no clinic affiliation)
- [x] `EnsureSuperAdmin` middleware — guards `/admin/*` routes
- [x] `super-admin` middleware alias registered in `bootstrap/app.php`
- [x] `LoginResponse` override — super-admins redirected to `/admin` after login (not `/dashboard`)
- [x] Super-admin seeded: `admin@aesthetic-ai.test / password`

**Admin Tenant Controller:**
- [x] `GET /admin/tenants` — list all tenants (including soft-deleted) with plan, user count, status
- [x] `GET /admin/tenants/create` — create clinic form
- [x] `POST /admin/tenants` — create tenant + initial owner + send invitation email
- [x] `GET /admin/tenants/{id}` — manage clinic: edit details, view staff, add users
- [x] `PATCH /admin/tenants/{id}` — update name, slug, plan
- [x] `DELETE /admin/tenants/{id}` — soft-delete (deactivate) clinic
- [x] `POST /admin/tenants/{id}/restore` — restore deactivated clinic
- [x] `POST /admin/tenants/{id}/users` — add team member + send invite
- [x] `POST /admin/tenants/{id}/users/{user}/resend-invite` — resend credentials email

**Invitation Flow:**
- [x] `UserInviteMail` — sends login URL (tenant subdomain), email, temporary password, role
- [x] `resources/views/emails/user-invite.blade.php` — luxury dark HTML email
- [x] Temporary password auto-generated (`Str::password(12, symbols: false)`)
- [x] Resend invite resets password + resends email

**Admin React Pages:**
- [x] `resources/js/pages/admin/tenants/index.tsx` — stats row + tenants table (active/inactive badges, deactivate/restore)
- [x] `resources/js/pages/admin/tenants/create.tsx` — form with auto-slug from name, procedure toggles, owner account fields
- [x] `resources/js/pages/admin/tenants/show.tsx` — edit details, add staff, staff list with resend invite button

**Sidebar + Theme:**
- [x] `AppSidebar` shows "Platform Admin" nav section for super-admin users, clinic nav for regular users
- [x] `AppLogo` updated — "Aesthetic AI" with gold monogram (was "Laravel Starter Kit")
- [x] Nav group label "Platform" → "Clinic"
- [x] Dark mode default — app now defaults to `dark` (was `system`). `system` and blank both apply dark; only explicit `light` switches to light mode
- [x] Dark theme CSS variables fully mapped to luxury palette (`#0A0A0F` background, `#0D0D14` sidebar, `#C9A84C` primary/ring, `#F5F0E8` foreground)
- [x] 15 tests in `AdminTenantTest.php`

---

---

### P1 Sprint 9 — Billing, Notifications, Webhooks & Security ✅ COMPLETE

**Billing:**
- [x] `BillingController` — plan list, usage, subscription status, Stripe Checkout, plan swap, Customer Portal, post-checkout sync
- [x] Invoice history — Cashier `$tenant->invoices()` mapped to `id`, `number`, `date`, `total`, `status`, `pdf_url` (Stripe-hosted)
- [x] `billing.tsx` — current plan card, usage bar, plan grid cards (Upgrade / Switch / Active), invoice history section
- [x] Billing gate middleware + trial/expiry banners

**Email Notifications:**
- [x] `PatientConfirmationMail` + `emails.patient-confirmation` Blade — immediate post-submission confirmation to patient (PHI-minimal: first name + procedure only, submission reference badge)
- [x] `SendPatientConfirmationJob` — `notifications` queue, 3 tries, guards against null/invalid email

**Webhook Enhancements:**
- [x] `IntegrationController::sendTest()` — synchronous connectivity check to webhook URL; returns `{ok, status_code, latency_ms, body}`; no delivery record created
- [x] `webhooks.tsx` — expandable table rows with delivery ID, evaluation token (truncated), last attempt, delivered_at, full response body in `<pre>`; retry button stops row-click propagation

**Theme System:**
- [x] `luxury-dark`, `luxury-light`, `clinical` themes via CSS custom properties scoped to `data-intake-theme` attribute
- [x] All intake pages (`WizardShell`, all 10 step files) migrated from hardcoded hex to CSS variable references
- [x] `settings.tsx` — "Intake Page Theme" selector (3 buttons: Luxury Dark, Luxury Light, Clinical)
- [x] `integrations.tsx` — "Widget Theme" label with helper text linking to Clinic Settings

**Landing Page & Auth:**
- [x] Self-service tenant registration as primary CTA; "Request a Demo" as secondary for enterprise
- [x] Welcome page: stats bar, How It Works (3-step), 6-card features grid, security/HIPAA section, demo CTA section

**Security & Rate Limiting:**
- [x] Named Laravel rate limiters via `AppServiceProvider::configureRateLimiters()`
  - `intake.evaluation.create` — 3/10 min per IP
  - `intake.evaluation.submit` — 3/hour per IP
  - `intake.photos` — 15/10 min per token+IP
  - `intake.quiz` — 30/min per token+IP
  - `access-requests` — 5/hour per IP
- [x] Per-email+procedure+tenant 24h cooldown in `EvaluationController::submit()` — prevents duplicate submissions within 24 hours; excludes draft and failed evaluations
- [x] 8 tests in `IntakeRateLimitTest.php`

---

---

### P1 Sprint 10 — White-Label, SMS, Multilingual & Usage Alerts ✅ COMPLETE

**White-Label Branding:**
- [x] Logo upload — `POST /clinic/settings/logo` + `DELETE /clinic/settings/logo`; stored in public disk; shown in WizardShell header
- [x] Brand primary color — hex color picker in settings, passed to intake as `--intake-accent` CSS variable override (replaces default `#0E9E8E`)
- [x] Email sender name — `from_name` setting drives Mailable `From:` header (falls back to clinic name)
- [x] Custom domain instructions — store `custom_domain` in settings, show CNAME DNS setup panel inline with a "Pro" badge

**SMS Confirmations:**
- [x] `opt_in_sms` consent checkbox in wizard (already in `ConsentFormData` type, now rendered as optional consent item)
- [x] `SendPatientSmsConfirmationJob` — Twilio REST API via `Http::withBasicAuth`; dispatched on `notifications` queue when patient opts in
- [x] `consent.opt_in_sms` stored in `quiz_answers._consent`; job guards against missing phone or unconfigured Twilio
- [x] SMS opt-in is visibly marked optional in UI

**Usage Overage Alerts:**
- [x] `SendUsageOverageAlertJob` — checks if usage ≥ 80% of plan limit; sends to clinic Owner once per calendar month (cached)
- [x] `UsageOverageAlertMail` + `emails.usage-overage-alert` Blade — shows progress bar, current/remaining/limit stats, upgrade CTA
- [x] Dispatched on every `EvaluationController::store()` call on `notifications` queue (idempotent — skips if already sent this month)

**Multilingual Support (English + Spanish):**
- [x] `resources/js/i18n/translations.ts` — full bilingual dictionary (EN + ES) for all intake UI strings
- [x] `resources/js/i18n/useTranslation.ts` — `useTranslation(locale)` hook returning `t(key, vars)` with `{variable}` interpolation
- [x] `locale` setting in clinic settings — English / Español toggle, stored in `tenant.settings.locale`
- [x] `IntakeController` passes `locale` in `clinic` prop to intake wizard
- [x] All intake step components (`ProcedureSelect`, `ContactInfo`, `ConsentSubmit`, `QuizStep`, `PhotoCapture`, `WizardShell`) accept and use `t()`
- [x] Quiz questions remain clinic-defined (backend) — only UI chrome (labels, buttons, navigation) is translated

---

## Phase 2 — Foundation

**Theme:** Multi-procedure, multi-tenant, revenue model live.

**Duration:** Months 4–6

**Exit Criteria:** 3 paying clinics, 5 procedure types, webhook sync to one external CRM.

### Key Deliverables

**Multi-Procedure Expansion:** ✅ *Delivered early (Phase 1 Sprint 5 + Sprint 6)*
- [x] Quiz engine supports multiple procedure definitions (JSONB config)
- [x] Procedure library: Rhinoplasty, Liposuction 360, BBL, Breast Augmentation, Facelift — all with branching quizzes + AI Vision
- [x] Procedure-specific photo capture protocols (body vs. face angles)
- [x] Tenant settings UI — checkbox grid grouped by Face/Body to enable/disable procedures per clinic
- [ ] Anatomical 3D pin-drop interface (chief concern step enhancement)

**Multi-Tenant Onboarding:** 🚧 *Partially delivered (Sprint 7)*
- [x] Super-admin panel — create/edit/deactivate tenants, add users, send invitations
- [x] Invitation email with temporary credentials per tenant subdomain
- [ ] **Tenant self-registration flow** — public sign-up page → tenant created → owner invited *(next priority)*
- [ ] Custom domain support (CNAME → AestheticAI)
- [ ] Subdomain provisioning automation (DNS record creation on tenant create)

**Billing:** ✅ *Complete (Sprint 9)*
- [x] Stripe integration via Laravel Cashier — Starter / Growth / Pro monthly plans
- [x] Stripe Checkout — new subscription flow (hosted page)
- [x] Plan swaps — prorated immediate plan changes for active subscribers
- [x] Stripe Customer Portal — self-service card/invoice management
- [x] Invoice history — past invoices with PDF download links in billing page
- [x] Usage metering — `evals_this_month` + `procedures_count` displayed with progress bar
- [x] Plan enforcement middleware — blocks new evaluations when trial expired or subscription lapsed
- [x] Trial banner — amber notice with trial end date; red paywall for expired accounts
- [x] Webhook plan sync — `WebhookReceived` listener syncs `plan_id` from Stripe events
- [x] Usage overage alerts — email Owner when eval count reaches 80% of plan limit
- [ ] Annual billing — yearly plans with discount; Stripe annual price IDs
- [ ] Promo codes — Stripe coupon support in checkout flow

**CRM Integration — Phase 2:** 🚧 *Partially delivered*
- [x] Generic webhook system (signed `X-AestheticAI-Signature` HMAC-SHA256 payload)
- [x] Webhook delivery log + retry UI in dashboard (`/clinic/webhooks`) — expandable response bodies, HTTP status, latency, attempt count
- [x] Test webhook endpoint — synchronous connectivity check from integrations page (shows status code + latency inline)
- [ ] HubSpot native integration (contact creation + property sync)
- [ ] Nextech webhook (lead creation)

---

## Phase 3 — Intelligence

**Theme:** Full AI suite. Visible results. Premium differentiation.

**Duration:** Months 7–10

### Key Deliverables

**Advanced AI Vision:** 🚧 *Partially delivered (Sprint 6 + Sprint 8)*
- [x] Procedure-specific AI flags for all 5 procedures (rhinoplasty, BBL, lipo 360, breast aug, facelift)
- [x] Age estimation from Rekognition (facelift: young/mature candidate detection)
- [x] Skin laxity concern flag (lipo 360: from photo quality proxy metric)
- [x] Body analysis flags (BBL: safety protocol, donor areas, weight stability)
- [x] Body landmark detection — `ExtractBodyLandmarksJob`: 11 front + 5 side normalised landmarks, procedure-aware variance
- [x] Body proportions — `CalculateBodyProportionsJob`: WHR, shoulder-waist ratio, gluteal projection, body symmetry, overall contour score (0–100)
- [x] Pipeline branching — body procedures (BBL, Lipo 360, Breast Aug) use body jobs; face procedures use facial jobs
- [ ] Full skin texture / laxity estimation from photo (dedicated ML model, not proxy)
- [ ] Improved ML-based procedure matching trained on historical outcome data

**AI Simulation (High Impact Feature):** ✅ *Complete (Sprint 8 + extended)*
- [x] `laravel/ai` SDK — official Laravel AI package used for image generation
- [x] `ProcedureRegistry` — central registry of all 26 procedure slugs; drives pipeline type (body vs face), high-revenue flags, and prompt routing across the platform
- [x] `GenerateSimulationJob` — procedure-specific prompts for all 26 procedures; body proportions + facial landmarks baked into prompt; stores result to S3; placeholder mode when `FEATURE_AI_VISION=false`
- [x] `SimulationController` — `POST` to request simulation, `GET` to poll signed S3 URL (async, `ai` queue); fixed for Octane tenant scoping via `string $id` + manual `findOrFail()`
- [x] `SimulationViewer` React component — request button, 4-second status polling, result hydrated on page refresh (one-shot fetch on mount when already complete), "not a guarantee" disclaimer, regenerate button, copy share link
- [x] `SimulationShareController` + `/intake/simulations/{token}` — public share page gated by `secure_token`
- [x] Migration — `simulation_status`, `simulation_data`, `simulation_requested_at` on evaluations
- [x] 12 body landmark tests + 11 simulation tests + 39 unit + 51 feature tests for `ProcedureRegistry`
- [x] Shareable secure link for simulation result (`/intake/simulations/{token}` — gated by `secure_token`, no auth required)
- [ ] Morphing preview for Rhinoplasty (facial photo edit using patient's actual photo)
- [ ] S3 photo download for real OpenAI image edit (currently generates from prompt only)

**Patient Experience Enhancements:**
- [x] Beauty Roadmap PDF — personalized report emailed to patient ✅ *Sprint 6*
- [ ] Patient portal: check evaluation status
- [ ] Patient portal: book consultation directly

**Analytics Dashboard:** ✅ *Complete (Sprint 6)*
- [x] Clinic conversion funnel metrics (intake funnel drop-off by step)
- [x] Lead score vs. actual booking rate correlation (scoreVsBooking buckets)
- [x] Procedure mix breakdown (volume + booking rate per procedure)
- [x] Month-over-month comparison (evaluations, avg lead score, bookings + delta indicators)

---

## Phase 4 — Scale

**Theme:** 50+ clinics. White-label. Partner API.

**Duration:** Months 11–18

### Key Deliverables

**White-Label Program:** 🚧 *Partially delivered (Sprint 10)*
- [x] Logo upload and display in intake wizard header
- [x] Brand primary color override (CSS variable, full intake wizard)
- [x] Custom email sender name (From name in patient emails)
- [x] Custom domain field with CNAME setup instructions
- [ ] Full rebranding: custom email domain (actual SMTP / DNS provisioning)
- [ ] Reseller program for medical marketing agencies
- [ ] Reseller dashboard: manage multiple clinic accounts

**Partner API:** ⬜
- [ ] Public API documentation (OpenAPI spec)
- [ ] SDK: JavaScript, PHP, Python
- [ ] API key management per tenant
- [ ] Rate limiting and usage dashboards

**PatientNow Integration:** ⬜
- [ ] Deep sync: evaluation → PatientNow patient record
- [ ] Two-way: consultation status synced back to AestheticAI
- [ ] Photo transfer (HIPAA-compliant, with patient consent)

**ML Model Improvements:** ⬜
- [ ] Train proprietary recommendation model on anonymized outcome data
- [ ] A/B test: rule-based vs. ML recommendations
- [ ] Outcome tracking: book, show, convert → feeds model training

**Enterprise Features:** ⬜
- [ ] Multi-location support (one clinic group, multiple locations)
- [ ] Role hierarchy: Group Admin → Location Admin → Coordinator
- [ ] Consolidated reporting across locations
- [ ] Enterprise SSO (SAML 2.0)

---

## Backlog (Future Consideration)

- Mobile native apps (iOS + Android) for photo capture
- Telemedicine integration (video consultation scheduling)
- Insurance eligibility pre-check (for reconstructive procedures)
- ~~Multilingual support (Spanish — critical for Miami market)~~ ✅ Done (Sprint 10)
- Additional languages: Portuguese, French, Mandarin
- AI chatbot for pre-evaluation patient questions
- Integration with practice management software (ModMed, Kareo)
- Outcome photography tracking (before + after comparison)
- Surgeon outcome portfolio (anonymized, for trust-building)

---

## What's Next (Recommended Priority Order)

| Priority | Item | Phase | Effort |
|---|---|---|---|
| 🔥 1 | Patient portal — status check + self-schedule consultation | Phase 3 | Medium |
| 🔥 2 | HubSpot / Nextech native CRM integration | Phase 2 | Medium |
| ✅ | AI Simulation — rhinoplasty facial edit (source photo via S3 download) | Phase 3 | Done |
| 4 | Annual billing — yearly plans with Stripe discount pricing | Phase 2 | Small |
| 5 | Coordinator digest email — daily summary for high-volume clinics | Phase 2 | Small |
| ✅ | Patient follow-up sequence — scheduled reminder emails | Phase 2 | Done |
| ✅ | White-label branding (logo, brand color, email sender, custom domain) | Phase 4 | Done |
| ✅ | SMS confirmations via Twilio (patient opt-in) | Phase 2 | Done |
| ✅ | Spanish intake wizard (full EN/ES translation system) | Backlog | Done |
| ✅ | Usage overage alerts at 80% eval cap | Phase 2 | Done |
| ✅ | Stripe billing — Checkout, plan swaps, Customer Portal, invoices | Phase 2 | Done |
| ✅ | Tenant self-registration + "Request a Demo" enterprise CTA | Phase 2 | Done |
| ✅ | Patient confirmation email (immediate post-submission) | Phase 2 | Done |
| ✅ | Webhook delivery log expandable rows + test endpoint | Phase 2 | Done |
| ✅ | Intake rate limiting (named limiters + 24h email cooldown) | Security | Done |
| ✅ | Intake theme system (luxury-dark, luxury-light, clinical) | Phase 2 | Done |
| ✅ | GitHub Actions CI (Pest + tsc + Pint + ESLint) | Infra | Done |
| ✅ | Shareable secure link for simulation result | Phase 3 | Done |
| ✅ | Full procedure library (26 procedures via ProcedureRegistry) | Phase 2 | Done |

---

## Metrics to Track

| Metric | MVP Target | Phase 2 Target |
|--------|-----------|----------------|
| Evaluation completion rate | > 60% | > 70% |
| Lead-to-consult conversion | +20% vs. contact form | +35% |
| Coordinator time-to-call | < 30 min for High/Urgent | < 15 min |
| No-show rate | -15% vs. baseline | -25% |
| Photo quality pass rate | > 80% | > 90% |
| AI recommendation accuracy | N/A (rule-based) | > 75% match |
