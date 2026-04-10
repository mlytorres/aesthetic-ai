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
| **Pilot clinic** | https://miamilife.aesthetic-ai.test |

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
**Sprint 6 — Advanced AI Vision + Analytics:** ✅ **Complete**
**Sprint 7 — Multi-Tenant Platform Admin:** ✅ **Complete**

---

## Phase Overview

```
Phase 1 — MVP (Months 1–3)          ✅ COMPLETE
  "Prove the concept. One procedure. One clinic. Real leads."

Phase 2 — Foundation (Months 4–6)   🚧 IN PROGRESS
  "Multi-procedure. Multi-tenant. CRM integrations. Billing."

Phase 3 — Intelligence (Months 7–10) 🚧 IN PROGRESS (Analytics + AI Vision shipped early)
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
- [ ] GitHub Actions CI: PHPStan level 8, Pest tests, TypeScript `tsc --noEmit` *(not configured yet)*
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

**Billing:** ⬜ *Not started*
- [ ] Stripe integration (subscription plans)
- [ ] Plans: Starter (1 procedure, 50 evals/mo) → Growth (5 procedures, 200/mo) → Pro (unlimited)
- [ ] Usage metering (evaluations counted per billing period)
- [ ] Invoice generation + dunning management
- [ ] Plan enforcement in middleware (block intake if over limit)

**CRM Integration — Phase 2:** 🚧 *Partially delivered*
- [x] Generic webhook system (signed `X-AestheticAI-Signature` HMAC-SHA256 payload)
- [x] Webhook delivery log + retry UI in dashboard (`/clinic/webhooks`)
- [ ] HubSpot native integration (contact creation + property sync)
- [ ] Nextech webhook (lead creation)

---

## Phase 3 — Intelligence

**Theme:** Full AI suite. Visible results. Premium differentiation.

**Duration:** Months 7–10

### Key Deliverables

**Advanced AI Vision:** 🚧 *Partially delivered (Sprint 6)*
- [x] Procedure-specific AI flags for all 5 procedures (rhinoplasty, BBL, lipo 360, breast aug, facelift)
- [x] Age estimation from Rekognition (facelift: young/mature candidate detection)
- [x] Skin laxity concern flag (lipo 360: from photo quality proxy metric)
- [x] Body analysis flags (BBL: safety protocol, donor areas, weight stability)
- [ ] Full skin texture / laxity estimation from photo (dedicated ML model, not proxy)
- [ ] Body landmark detection for torso / gluteal proportions (BBL, lipo 360)
- [ ] Improved ML-based procedure matching trained on historical outcome data

**AI Simulation (High Impact Feature):** ⬜ *Not started*
- [ ] "Potential Results" overlay using generative AI
- [ ] Morphing preview for Rhinoplasty (realistic, not cartoonish)
- [ ] Breast augmentation size comparison view
- [ ] IMPORTANT: Clear labeling — "AI Visualization — Not a Guarantee"
- [ ] Results stored alongside evaluation, shareable via secure link

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

**White-Label Program:** ⬜
- [ ] Full rebranding capability (logo, colors, domain, email sender)
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
- Multilingual support (Spanish — critical for Miami market)
- AI chatbot for pre-evaluation patient questions
- Integration with practice management software (ModMed, Kareo)
- Outcome photography tracking (before + after comparison)
- Surgeon outcome portfolio (anonymized, for trust-building)

---

## What's Next (Recommended Priority Order)

| Priority | Item | Phase | Effort |
|---|---|---|---|
| 🔥 1 | Tenant self-registration flow (public sign-up → auto-onboard) | Phase 2 | Medium |
| 🔥 2 | Stripe billing + plan enforcement | Phase 2 | Large |
| 🔥 3 | GitHub Actions CI (PHPStan + Pest + tsc) | Infra | Small |
| 4 | CloudWatch / production alerts | Infra | Small |
| 5 | Patient portal (status check + booking) | Phase 3 | Medium |
| 6 | HubSpot / Nextech CRM integration | Phase 2 | Medium |
| 7 | Body landmark detection (BBL/Lipo) | Phase 3 | Large |
| 8 | AI Simulation (generative overlay) | Phase 3 | X-Large |

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
