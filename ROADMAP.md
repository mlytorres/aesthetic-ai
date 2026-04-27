# Development Roadmap — Aesthetic AI SaaS Platform

> Last updated: April 2026

---

## Roadmap Summary

| Phase | Name | Focus | Est. Duration |
|---|---|---|---|
| Phase 0 | Foundation | Infrastructure, auth, multi-tenancy | 3–4 weeks |
| Phase 1 | MVP | AI Photo Capture + Secure Intake (Rhinoplasty) | 6–8 weeks |
| Phase 2 | Core Platform | Smart Intake Engine + full CV suite | 8–10 weeks |
| Phase 3 | Clinic Dashboard | Multi-tenant dashboard, lead scoring, briefs | 6–8 weeks |
| Phase 4 | SaaS & Integrations | CRM webhooks, billing, self-serve onboarding | 6–8 weeks |
| Phase 5 | Expansion | AI simulations, multi-procedure, scale | Ongoing |

---

## Phase 0 — Foundation *(Weeks 1–4)*

**Goal:** Establish a production-grade, HIPAA-compliant infrastructure baseline before any feature work.

### Milestones
- [ ] Laravel 12 project scaffolded with Inertia.js + React + TailwindCSS
- [ ] Multi-tenant architecture implemented (subdomain resolution + Global Scopes)
- [ ] MySQL schema with `tenant_id` isolation on all patient tables
- [ ] BAA executed with AWS HealthLake or GCP Healthcare API
- [ ] Role-based access control (Surgeon, Coordinator, Front Desk, Admin)
- [ ] Audit logging table and middleware in place
- [ ] CI/CD pipeline configured (GitHub Actions or equivalent)
- [ ] Staging and production environments provisioned on AWS/GCP

### Definition of Done
A clean, empty multi-tenant application that can create a clinic (tenant), onboard a user to that tenant, and enforce data isolation — with all infrastructure HIPAA-compliant.

---

## Phase 1 — MVP *(Weeks 5–12)*

**Goal:** Launch a working product for a single procedure (Rhinoplasty) that demonstrates the full patient-to-clinic data pipeline.

### Milestones
- [ ] Patient intake form with procedure selection (Rhinoplasty focus)
- [ ] **AI-Guided Photo Capture Tool** — mobile PWA with ghosting/transparency overlay
- [ ] Direct-to-cloud photo upload (signed URLs → AWS HealthLake)
- [ ] Basic AI photo analysis integration (face landmarks + symmetry score)
- [ ] Secure Magic Link generation and delivery to clinic coordinator
- [ ] Clinic coordinator portal — view patient pre-profile via Magic Link
- [ ] Basic lead record stored per tenant with computed score
- [ ] "New Evaluation Ready" email notification (link only, no PHI in body)
- [ ] End-to-end HIPAA compliance audit of data flow

### Definition of Done
A Rhinoplasty patient can complete the intake flow, submit photos, and a coordinator at the clinic receives a secure link to view the full pre-profile — with zero PHI transmitted outside the encrypted portal.

---

## Phase 2 — Core Platform *(Weeks 13–22)*

**Goal:** Expand the Smart Intake Engine and AI suite to cover all primary procedures.

### Milestones
- [ ] **Anatomical 3D Mapping** — Three.js body/face model with pin-drop interaction
- [ ] **Dynamic Branching Quiz** — procedure-adaptive question logic for all 5 launch procedures
- [ ] Expand AI CV suite: skin laxity estimation, Golden Ratio analysis, "Beauty Roadmap" report
- [ ] **Anatomic Procedure Matcher** — AI recommendation engine (e.g., Deep Plane Facelift vs. Fillers)
- [ ] Instant consultation booking flow (calendar integration)
- [ ] Multi-procedure support: Lipo 360, BBL, J-Plasma, Facelift
- [ ] Patient-facing "Beauty Roadmap" PDF report generation

### Definition of Done
Any of the 5 supported procedures can be selected by a patient, triggering a fully adaptive intake experience and producing a complete AI-analyzed clinical profile.

---

## Phase 3 — Clinic Dashboard *(Weeks 23–30)*

**Goal:** Build the full multi-tenant coordinator and surgeon dashboard experience.

### Milestones
- [ ] Clinic coordinator dashboard with lead queue (sorted by lead score)
- [ ] Lead scoring algorithm v1 — weights procedure type, completion rate, photo quality
- [ ] **Pre-Op Clinical Brief** — structured PDF/view generated per patient for surgeons
- [ ] Surgeon portal (role-gated) — view clinical briefs, annotate, flag for follow-up
- [ ] Coordinator call queue with priority flags (high-value procedures at top)
- [ ] Patient record management — status tracking (New → Contacted → Booked → Consulted)
- [ ] Clinic admin settings — manage users, roles, notification preferences
- [ ] Tenant onboarding flow (self-serve clinic registration)

### Definition of Done
A coordinator can log in, see a prioritized queue of new patient profiles, open a clinical brief, update lead status, and assign follow-up tasks — all within a single, HIPAA-compliant dashboard.

---

## Phase 4 — SaaS & Integrations *(Weeks 31–38)*

**Goal:** Transform the platform into a fully self-serve, monetizable SaaS product.

### Milestones
- [ ] **CRM Webhooks** — outbound sync to Nextech, PatientNow, and generic webhook targets
- [ ] Subscription billing (Stripe) — per-clinic monthly/annual plans
- [ ] Usage-based add-ons (e.g., AI analysis credits per profile)
- [ ] Self-serve clinic onboarding wizard (no manual setup required)
- [ ] Tenant branding customization (clinic logo, color scheme, custom intake URL)
- [ ] API documentation for third-party integrations
- [ ] Admin super-dashboard (platform-wide metrics, tenant management)

### Definition of Done
A new clinic can discover the product, sign up, execute a BAA digitally, configure their intake portal, and go live — without any manual intervention from the platform team.

---

## Phase 5 — Expansion *(Ongoing from Week 39+)*

**Goal:** Extend AI capabilities, grow the clinic network, and increase per-patient value.

### Planned Features
- [ ] **AI Result Simulations** — "potential results" overlays based on the patient's own photos
- [ ] Expanded procedure library (e.g., breast augmentation, eyelid surgery)
- [ ] Patient re-engagement workflows (automated follow-up sequences)
- [ ] Multi-language support (Spanish as first priority — Miami market)
- [ ] Analytics dashboard — conversion rates, no-show rates, procedure mix per clinic
- [ ] Surgeon feedback loop — outcomes data feeds back into AI recommendation accuracy
- [ ] Mobile app (iOS + Android) for patients

---

## Key Risks & Mitigations

| Risk | Mitigation |
|---|---|
| HIPAA compliance gap | BAA-first approach; PHI only in certified cloud storage from Day 1 |
| AI accuracy concerns | Conservative rollout — AI provides suggestions, surgeon always decides |
| Clinic adoption friction | White-label intake portal; no learning curve for patients |
| No-show rate unchanged | Validated by published research: interactive pre-consult tools reduce no-shows 25–40% |
| CRM integration complexity | Phase 4 webhooks are configurable per tenant; generic payload fallback |
