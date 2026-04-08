# AestheticAI — Digital Consultation Concierge

> **Pre-Qualification SaaS Platform for Aesthetic & Plastic Surgery Clinics**

[![Status](https://img.shields.io/badge/status-MVP%20Planning-blue)]()
[![Stack](https://img.shields.io/badge/stack-Laravel%20%7C%20React%20%7C%20Inertia-purple)]()
[![Compliance](https://img.shields.io/badge/compliance-HIPAA%20Ready-green)]()

---

## What Is AestheticAI?

AestheticAI bridges the gap between a raw lead inquiry and a high-intent surgical patient. Instead of collecting basic contact forms, it delivers **pre-qualified clinical profiles** to clinics — complete with AI-analyzed photos, procedure recommendations, and a readiness score — before the first phone call ever happens.

**The problem it solves:** Cosmetic surgery coordinators spend enormous time on unqualified leads. Patients arrive for consultations with zero preparation. No-show rates are high. Surgeons waste 15–20 minutes per consult gathering basic history.

**The solution:** An intelligent, HIPAA-compliant intake experience that qualifies, educates, and commits the patient — delivered as a white-label widget that plugs into any existing CRM.

---

## Core Value Propositions

| For Clinics | For Patients |
|-------------|--------------|
| Leads arrive pre-scored and procedure-matched | Guided, educational experience before arriving |
| Coordinator call list auto-prioritized | AI Beauty Roadmap with Golden Ratio analysis |
| Surgeons receive pre-op brief before consult | Secure photo capture with angle guidance |
| 24/7 intake — no staff required | Instant estimated candidacy for their procedure |
| Webhook sync to existing CRM (Nextech, PatientNow) | Transparent, no-pressure process |

---

## Product Modules

```
AestheticAI Platform
├── 1. Smart Intake Engine
│   ├── Anatomical 3D Pin-Drop Interface
│   ├── Dynamic Branching Quiz (procedure-specific)
│   └── Readiness Score Calculator
│
├── 2. AI Computer Vision Suite
│   ├── Guided Photo Capture (angle calibration)
│   ├── Proportion & Golden Ratio Analysis
│   └── Procedure Recommendation Engine
│
├── 3. Clinic Dashboard (Multi-Tenant)
│   ├── Lead Scoring & Priority Queue
│   ├── Secure Magic Link Portal
│   └── Pre-Op Clinical Brief Generator
│
└── 4. Integration Layer
    ├── CRM Webhooks (Nextech, PatientNow, HubSpot)
    ├── Calendar Booking Sync
    └── Embed Widget (iFrame / JS SDK)
```

---

## Tech Stack

| Layer | Technology |
|-------|------------|
| Backend | Laravel 12 + PHP 8.3 |
| Frontend | React 18 + Inertia.js + TypeScript |
| Styling | TailwindCSS 4 + Shadcn/UI |
| Database | PostgreSQL (RLS enforced) |
| Cloud / HIPAA | AWS HealthLake or GCP Healthcare API |
| AI / Vision | AWS Rekognition + Custom ML Models |
| Auth | Laravel Sanctum + Magic Links |
| Queue | Laravel Horizon + Redis |
| Storage | S3 (encrypted, signed URLs only) |

---

## Repository Structure

```
aesthetic-ai/
├── README.md                  # This file
├── AGENTS.md                  # AI agent roles & behavior rules
├── SKILLS.md                  # Reusable capability definitions
├── ARCHITECTURE.md            # System design & data flow
├── SECURITY.md                # HIPAA compliance & threat model
├── API.md                     # External API contracts
├── INTEGRATIONS.md            # CRM webhook & embed guide
├── ROADMAP.md                 # Phased development plan
├── CONTRIBUTING.md            # Dev workflow & coding standards
│
├── docs/
│   ├── product/               # PRD, user stories, wireframe notes
│   ├── technical/             # ADRs, schema diagrams, sequence flows
│   └── compliance/            # BAA templates, HIPAA controls
│
├── app/                       # Laravel application
├── resources/                 # React + Inertia frontend
├── database/                  # Migrations, seeders, factories
├── tests/                     # Feature + unit tests
└── .claude/                   # AI coding context files
    ├── context.md
    └── patterns.md
```

---

## Quick Start (Development)

```bash
# Clone and install
git clone https://github.com/your-org/aesthetic-ai
cd aesthetic-ai
composer install && npm install

# Environment setup
cp .env.example .env
php artisan key:generate

# Database
php artisan migrate --seed

# Start dev servers
php artisan serve &
npm run dev

# Queue worker (required for AI jobs)
php artisan horizon
```

---

## Documentation Index

- [Architecture](./ARCHITECTURE.md) — System design, multi-tenancy, data flow
- [Agents](./AGENTS.md) — AI agent definitions for vibe coding
- [Skills](./SKILLS.md) — Reusable AI skill modules
- [Security](./SECURITY.md) — HIPAA compliance controls
- [API Reference](./API.md) — Internal and external API contracts
- [Integrations](./INTEGRATIONS.md) — CRM webhooks and embed SDK
- [Roadmap](./ROADMAP.md) — MVP → Scale phased plan
- [Contributing](./CONTRIBUTING.md) — Dev standards and workflow

---

## License

Proprietary — Miami Life Cosmetic Center © 2025
