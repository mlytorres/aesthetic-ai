# ROADMAP.md — Development Phases

> Phased plan from MVP to full platform. Each phase has clear exit criteria before the next begins.

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

### P1 Sprint 1 — Core Infrastructure (Weeks 1–2)

**Backend:**
- [ ] Laravel 12 project scaffold with strict types
- [ ] PostgreSQL + migrations for: `tenants`, `patients`, `evaluations`, `photos`, `audit_log_entries`
- [ ] `HasTenantScope` trait + `TenantContext` service
- [ ] `AuditLog::record()` service
- [ ] AWS S3 bucket with KMS encryption configured
- [ ] `SecureFileService` — upload + signed URL generation
- [ ] Laravel Horizon + Redis queue setup

**DevOps:**
- [ ] GitHub repo + branch protection rules
- [ ] GitHub Actions CI: PHPStan level 8, Pest tests, TypeScript tsc --noEmit
- [ ] Staging environment on AWS (not production-grade yet)
- [ ] `.env` secrets management via AWS Secrets Manager

---

### P1 Sprint 2 — Patient Intake Wizard (Weeks 3–5)

**Frontend — Patient Portal:**
- [ ] Mobile-first wizard shell with progress bar
- [ ] Step 1: Procedure selection (Rhinoplasty only for MVP)
- [ ] Step 2: Chief concern text input
- [ ] Step 3: Dynamic quiz (rhinoplasty-specific — 8 questions)
  - Nasal concerns checklist (tip, bridge, nostrils, asymmetry)
  - Prior rhinoplasty history (yes/no → branching)
  - Breathing issues (yes/no → branching)
  - Skin type / thickness (thin / medium / thick)
  - Ethnicity considerations (relevant for technique)
  - Timeline / urgency (ready now / 3 months / 6+ months / researching)
  - Budget range (multiple choice)
  - How did you hear about us?
- [ ] Step 4: Photo capture
  - Camera access request with friendly explanation
  - Angle guide overlay (face outline silhouette)
  - Capture: Front, Left Profile, Right Profile
  - Client-side quality check (blur detection)
- [ ] Step 5: Contact info collection (name, email, phone)
- [ ] Step 6: Consent + submission
- [ ] Success screen with "What happens next" explanation

**Backend:**
- [ ] `EvaluationController` — create, update, complete
- [ ] `PhotoUploadController` — secure multi-part upload to S3
- [ ] `QuizDefinition` seed data for Rhinoplasty
- [ ] Evaluation form request validation

---

### P1 Sprint 3 — Basic AI Pipeline (Weeks 6–8)

**AI Jobs:**
- [ ] `ValidatePhotoQualityJob` — use AWS Rekognition to confirm face detected, assess quality score
- [ ] `ExtractFacialLandmarksJob` — AWS Rekognition `DetectFaces` → store 27 landmark points
- [ ] `CalculateProportionsJob` — facial thirds, fifths, symmetry from landmarks
- [ ] `GenerateBasicRecommendationsJob` — rule-based (not ML yet) from quiz answers + proportions

**Lead Scoring:**
- [ ] `LeadScoringService` — calculate 0–100 score
- [ ] Priority assignment (Urgent / High / Medium / Standard)

**Notifications:**
- [ ] `NotifyClinicNewEvaluationJob` — send email to coordinator
- [ ] Email template: "New Rhinoplasty Evaluation — Lead Score: {score}"
- [ ] Magic link generation for coordinator portal access

---

### P1 Sprint 4 — Clinic Dashboard MVP (Weeks 9–11)

**Clinic Dashboard:**
- [ ] Login flow (email + password)
- [ ] Evaluation list view — sortable by lead score, date
- [ ] Priority queue view (Urgent/High flagged at top)
- [ ] Evaluation detail page:
  - Patient photos (signed URLs, 15-min expiry)
  - Quiz answers formatted
  - AI analysis results (proportion scores)
  - Lead score breakdown
  - Procedure recommendation with reasoning
- [ ] Mark as: Contacted / Booked / No-Show / Not a Fit
- [ ] Basic search and filter

**Security:**
- [ ] All coordinator routes behind auth middleware
- [ ] Audit log visible in clinic settings (basic list)
- [ ] Session timeout after 30 minutes of inactivity

---

### P1 Sprint 5 — Polish + Pilot Launch (Weeks 12–13)

- [ ] End-to-end QA (full flow on iPhone, Android, desktop)
- [ ] HIPAA internal review checklist
- [ ] BAA signed with pilot clinic
- [ ] Patient-facing copy review (medical accuracy, tone)
- [ ] Coordinator training session (30 min)
- [ ] Analytics: Funnel drop-off tracking (step completion rates)
- [ ] Monitoring: Sentry errors + CloudWatch alerts
- [ ] Soft launch: Add widget to one page on clinic website

---

## Phase 2 — Foundation

**Theme:** Multi-procedure, multi-tenant, revenue model live.

**Duration:** Months 4–6

**Exit Criteria:** 3 paying clinics, 5 procedure types, webhook sync to one external CRM.

### Key Deliverables

**Multi-Procedure Expansion:**
- [ ] Quiz engine supports multiple procedure definitions (JSONB config)
- [ ] Procedure library: Rhinoplasty, Liposuction 360, BBL, Breast Augmentation, Facelift
- [ ] Procedure-specific photo capture protocols (body vs. face)
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

**Clinical Brief Generator:**
- [ ] PDF generation of pre-consultation brief
- [ ] Printable format for surgeon's physical files
- [ ] Auto-attached to clinic notification email

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
- [ ] Beauty Roadmap PDF — personalized report for patient
  - Their proportion analysis results
  - AI-recommended procedures with explanations
  - Educational content about each procedure
  - FAQ specific to their concerns
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
| AI recommendation accuracy | N/A (rule-based) | > 75% match |
