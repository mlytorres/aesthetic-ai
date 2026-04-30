# SymetriHealth / Aesthetic AI
## Digital Consultation Concierge — Pre-Evaluation Intelligence

### Owner approval & ecosystem enrollment deck · April 2026

**Print this deck** for board or operating-partner review alongside the **MiamiLife CRM hub** deck and companion **RecoverIQ** and **SalesNeural** presentations. Together they describe one **governed enrollment** into the MiamiLife **ecosystem platform** — four coordinated products, four approval decisions.

---

> *We don't only capture leads. We deliver clinically structured, prioritized patients — **before** the first coordinated call.*

---

## Executive summary (for approval meetings)

**Aesthetic AI (SymetriHealth)** sits **upstream** of conversion: intake, standardized imaging, anatomical structuring, lead scoring, and surgeon-ready briefing. It complements **MiamiLife CRM** (system of record) and feeds better consults; it does **not** replace surgeons or coordinators — it prepares them.

**Enrollment ask:** authorize implementation of this product plus **credential separation**: your clinic receives its **own SymetriHealth / Aesthetic API credentials**, distinct from RecoverIQ and SalesNeural (**three keys total per clinic** — see MiamiLife **`ECOSYSTEM-INTEGRATION-SPEC`**).

---

## The problem we're solving

Every aesthetic clinic faces friction between inquiry and consultation:

| Friction point | Operational cost |
|----------------|-------------------|
| Static forms — name + phone only | Coordinators re-qualify on every call |
| Slow first response | Competitors engage first |
| Surgeons walk in cold | Lost time and weaker close rates |
| No-shows and low-intent consults | Wasted chair time |

**You still run the clinic.** This platform standardizes **how intent and anatomy enter your CRM**.

---

## Our solution — in one paragraph

**Aesthetic AI** transforms the first touch into a **structured clinical profile**: procedure intent, adaptive questionnaire data, **AI-guided “before” photos**, proportion / symmetry signals, and a **Pre-Op Clinical Brief** for the surgeon — delivered through **encrypted, time-limited access patterns** appropriate for regulated data.

---

## Where this product sits in the ecosystem

| Layer | Product | Role |
|-------|---------|------|
| **Hub** | **MiamiLife CRM** | Source of truth; schedules; surgical milestones; outbound webhooks |
| **Pre-consult** | **Aesthetic AI** (this product) | Structured intake · imaging · briefing |
| **Post-op** | **RecoverIQ** | Recovery automation · triage · milestone-driven growth |
| **Pipeline** | **SalesNeural** | AI-augmented sales conversations on your channels |

CRM staff documentation for **this product only**: authenticated **`/clinic/api-docs`**. Canonical **cross-product** keys and HMAC rules: **MiamiLife CRM · `docs/ECOSYSTEM-INTEGRATION-SPEC.md`**.

---

## The platform at a glance

```
 PATIENT SIDE                         CLINIC SIDE
 ───────────────                      ──────────────────────────
 Pick procedure                   →    Scored queue (High / Medium / Low)
 Adaptive quiz                    →    No PHI in unsecured email —
 AI-guided standardized photos      “New evaluation ready” + magic link
 Personal report (“Beauty Roadmap”)→    Surgeon: Pre-Op Clinical Brief
 Book consult                     →    CRM synced per integration
```

---

## Module 1 — Smart intake engine

### Anatomical mapping
Patients mark **regions of concern** on guided models where applicable — structured data replaces free-text guesses.

### Dynamic branching quiz
Flows adapt by procedure (**BBL, facelift, rhinoplasty**, etc.) so coordinators receive **consistent clinical context**.

---

## Module 2 — AI computer vision suite

### Guided capture
**Ghost overlays** steer angle, framing, and distance — fewer unusable uploads.

### Proportion narrative
Landmarks → symmetry / proportion summaries → patient-facing **roadmap-style** artifact that increases consultation commitment (**not a surgical guarantee**).

### Procedure hints (clinical judgment stays with MD)
Suggested directions based on imaging signals — **final determination by your surgeon.**

---

## Module 3 — Clinic dashboard

- **Queues** prioritized by engagement, procedure weight, and model confidence  
- **Magic-link / portal access patterns** instead of PHI in plaintext email (**configure per BAAs**)  
- **Pre-Op Clinical Brief**: demographics, quiz flags, standardized images, summaries — targets **meaningful surgeon prep time recovered per block**

---

## Technology, integration & security — owner-facing facts

### Engineering stack

| Layer | Technology |
|-------|------------|
| Backend | Laravel (PHP), multi-tenant |
| Frontend | React + Inertia.js |
| UI | Tailwind — premium aesthetic |
| Credentials | Tenant-scoped — **never** shared with RecoverIQ or SalesNeural keys |

### CRM integration (what IT approves)

| Item | Detail |
|------|--------|
| **Staff docs** | **`/clinic/api-docs`** (authenticated) — **this product + CRM only** |
| **Inbound** | **`X-Api-Key`** preferred; **`X-Clinic-ID`**; Bearer legacy where documented |
| **Outbound webhooks** | Verify **`X-SymetriHealth-Signature`** (HMAC-SHA256 of **raw JSON body**) against your **`webhook_secret`** |

### HIPAA posture (architecture-first)

Execute **Business Associate Agreements (BAAs)** with your clinic where required. PHI handling follows **minimum necessary**, **encryption in transit**, **access controls**, **audit trails**, **time-bound links** — **exact cloud regions** and subprocessors finalized during enterprise onboarding.

*(Avoid storing PHI in general-purpose ticketing or chat tools.)*

---

## Market fit (illustrative)

High-volume aesthetic markets combine **conversion pressure** + **premium consult expense** — structured pre-consult intake has outsized ROI. Pilot targets remain **discussion-only** until contracts are countersigned.

---

## Commercial model (framework)

| Tier | Orientation |
|------|-------------|
| **Starter** | Limited providers / monthly evaluation cap |
| **Growth** | Multi-provider, higher volume |
| **Enterprise** | White-label, custom integrations, dedicated review |

**Usage add-ons:** AI analysis packs, simulation features, additional CRM connectors — **aligned to your stage of rollout**.

---

## Why approve this now

1. **Differentiated conversion** — better first touch than static forms.  
2. **Ecosystem alignment** — same MiamiLife operating model as RecoverIQ / SalesNeural.

3. **Clear security story** — separate credentials; cross-product spec in **one** CRM document.

---

## Owner approval — what you are endorsing

- [ ] Budget and **BAA / compliance** path for this product.  
- [ ] **Dedicated API credentials** for SymetriHealth / Aesthetic (**not** reused from other SaaS).  
- [ ] IT participation: **`/clinic/api-docs`**, webhook verification, staging **before** production.  
- [ ] Alignment with **MiamiLife CRM** as integration owner (**`docs/ECOSYSTEM-INTEGRATION-SPEC.md`** in the CRM repo).

---

## Next steps after sign-off

1. **Staging** tenant + keys + smoke test (`X-Api-Key` + sample evaluation).  
2. **CRM mapping** — which leads / procedures flow into MiamiLife.  
3. **Pilot cohort** — 1–2 locations, success metrics (show rate, prep time, lead quality).  
4. **Production** cutover with monitoring on auth and webhook logs.

---

## Companion documents

| Document | Location |
|----------|----------|
| Cross-product integration | **MiamiLife CRM** — `docs/ECOSYSTEM-INTEGRATION-SPEC.md` |
| Webhook verification | **MiamiLife CRM** — `docs/CRM-WEBHOOK-VERIFICATION.md` |
| Runbook | **MiamiLife CRM** — `docs/INTEGRATION-RUNBOOK.md` |
| Hub deck | **MiamiLife CRM** — `presentation.md` |

---

*SymetriHealth / Aesthetic AI · Confidential · April 2026 · For owner approval and ecosystem enrollment*
