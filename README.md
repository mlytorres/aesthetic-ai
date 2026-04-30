# Aesthetic AI — Digital Consultation Concierge

> **An AI-powered pre-evaluation SaaS platform that transforms raw lead inquiries into high-intent, pre-qualified surgical candidates.**

---

## Overview

The Aesthetic AI platform bridges the gap between a patient's first inquiry and their first in-person consultation. Instead of capturing just a name and phone number, the platform delivers a complete clinical pre-profile — including AI-analyzed photos, a targeted procedure questionnaire, and a computed lead score — before the clinic ever picks up the phone.

Clinics receive a rich "pre-op clinical brief" that helps surgeons prepare for consultations in advance, reducing per-consult prep time by 15–20 minutes and dramatically lowering no-show rates.

---

## Core Modules

### 1. Smart Intake Engine

- **Anatomical 3D Mapping** — patients interact with a 3D body/face model to drop pins on areas of concern
- **Dynamic Branching Quiz** — questions adapt based on the selected procedure (e.g., BMI for BBL, skin laxity for facelifts)
- **Instant Booking** — high-intent patients can book a paid consultation directly within the intake flow

### 2. AI Computer Vision Suite

- **Guided Photo Capture** — mobile-optimized ghosting/transparency overlay for standardized "before" angles
- **Proportion Analysis** — AI computes facial symmetry and Golden Ratio metrics, generating a personalized "Beauty Roadmap"
- **Anatomic Matching** — automatically recommends the optimal procedure based on visual anatomical markers

### 3. Clinic Dashboard (Multi-Tenant)
- **Lead Scoring & Priority Queues** — high-value inquiries automatically surfaced to the top of the coordinator's call list
- **Secure Magic Links** — patient photos and PHI are never sent via standard email; coordinators receive encrypted portal links only
- **Pre-Op Clinical Brief** — structured patient summary delivered to the surgeon before each consultation

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 12 (PHP) |
| Frontend | React + Inertia.js |
| Styling | TailwindCSS |
| Database | MySQL (multi-tenant, Row-Level Security via Global Scopes) |
| Cloud / HIPAA | AWS HealthLake or Google Cloud Healthcare API |
| AI / CV | Medical-grade CV APIs + custom anatomical logic |
| Auth & Links | HIPAA-compliant Magic Link delivery, time-limited tokens |

---

## Compliance & Security

- All patient data stored in **HIPAA-certified cloud environments**
- **Business Associate Agreements (BAA)** signed with every clinic partner
- Patient photos and PHI **never transmitted via standard email**
- All sensitive data accessed through **encrypted, time-limited portal links**
- Full **audit logging** for every data access event

---

## Target Market

Initial focus: high-volume Miami-area aesthetic clinics (Miami Life, 305 Plastic Surgery, etc.)

**Procedures supported at launch:** Rhinoplasty · Liposuction / Lipo 360 · BBL · Abdomen J-Plasma · Facelift

---

## Documentation

| Document | Description |
|---|---|
| **CRM integration (staff)** | In-app **`/clinic/api-docs`** (authenticated) — CRM `X-Api-Key`, REST v1, outbound webhook HMAC. |

| [`ARCHITECTURE.md`](./ARCHITECTURE.md) | Technical architecture, infrastructure, and security design |
| [`ROADMAP.md`](./ROADMAP.md) | Development phases, milestones, and timelines |
| [`presentation.md`](./presentation.md) | Owner / board approval deck — ecosystem enrollment (print with companion product decks) |
| [`CLAUDE.md`](./CLAUDE.md) | Project instructions and AI context |

---

*Proprietary — All rights reserved.*
