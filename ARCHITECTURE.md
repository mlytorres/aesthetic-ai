# Technical Architecture — Aesthetic AI SaaS Platform

---

## 1. System Overview

The platform is a **multi-tenant SaaS application** where each clinic (tenant) operates in a fully isolated data environment. The system is composed of four primary layers: the Patient-Facing Intake Layer, the AI Processing Layer, the Clinic Operations Layer, and the Infrastructure & Security Layer.

```
┌─────────────────────────────────────────────────────────────┐
│                    Patient-Facing Layer                      │
│        Smart Intake Portal  ·  AI Photo Capture Tool        │
└────────────────────────┬────────────────────────────────────┘
                         │
┌────────────────────────▼────────────────────────────────────┐
│                    AI Processing Layer                       │
│     Computer Vision API  ·  Proportion Engine  ·  Scoring   │
└────────────────────────┬────────────────────────────────────┘
                         │
┌────────────────────────▼────────────────────────────────────┐
│                  Clinic Operations Layer                     │
│    Multi-Tenant Dashboard  ·  Lead Queue  ·  Clinical Brief  │
└────────────────────────┬────────────────────────────────────┘
                         │
┌────────────────────────▼────────────────────────────────────┐
│              Infrastructure & Security Layer                 │
│     AWS HealthLake / GCP Healthcare  ·  BAA  ·  Audit Log   │
└─────────────────────────────────────────────────────────────┘
```

---

## 2. Technology Stack

### Backend

- **Framework:** Laravel 12 (PHP 8.3+)
- **API:** RESTful JSON API + Inertia.js server-side rendering
- **Queue:** Laravel Horizon (Redis) for async AI job processing
- **Jobs:** Photo analysis, lead scoring, notification dispatch

### Frontend

- **Framework:** React 18 + Inertia.js (no separate SPA build pipeline)
- **Styling:** TailwindCSS (luxury/premium UI system)
- **3D Mapping:** Three.js for anatomical model interaction
- **Mobile Capture:** Custom PWA camera overlay with ghosting/transparency

### Database

- **Engine:** MySQL 8.x with Row-Level Security enforced via Laravel Global Scopes
- **Multi-Tenancy:** `tenant_id` column on all patient/lead tables; middleware resolves tenant from subdomain
- **Migrations:** All schema changes version-controlled via Laravel Migrations

### AI / Computer Vision

- **Approach:** Hybrid — custom business logic layer wrapping medical-grade third-party CV APIs
- **Photo Analysis:** Face landmark detection, skin laxity estimation, symmetry scoring
- **Proportion Engine:** Golden Ratio calculation, facial thirds analysis
- **Procedure Matcher:** Rule-based + ML model that maps visual markers to procedure recommendations

---

## 3. Multi-Tenancy Design

Each clinic is a **tenant** resolved via subdomain (e.g., `miamilife.aestheticai.com`).

```
Request → SubdomainTenantMiddleware
              → Resolve Tenant from DB
              → Set App::tenant()
              → All Eloquent queries auto-scoped via TenantScope (Global Scope)
```

Key principles:

- Every model that holds patient data implements `BelongsToTenant`
- `tenant_id` is injected at the model boot level — never manually
- Cross-tenant data access is architecturally impossible at the application layer

---

## 4. Security & HIPAA Compliance

### Data Storage

- Patient photos and PHI stored exclusively in **AWS HealthLake** or **Google Cloud Healthcare API** (pre-certified HIPAA environments)
- No PHI ever written to application-layer file storage (no local S3 buckets with plain access)

### Data Transmission

- Patient records accessed only via **time-limited, signed Magic Links** (expiry: 24–72 hours)
- All data in transit encrypted via **TLS 1.3**
- Inbound photo uploads use **direct-to-cloud signed URLs** — never proxied through app servers

### Access Control

- Role-based access: Surgeon · Coordinator · Front Desk · Admin
- Laravel Policies enforce per-role permissions on every resource
- All access events written to an immutable **audit log table**

### Agreements

- A **Business Associate Agreement (BAA)** is executed with every clinic before onboarding
- Platform-level BAA signed with AWS / GCP

---

## 5. Notification & Delivery System

```
New Patient Submission
  → AI Processing Job queued (Redis/Horizon)
  → Photo analysis completes
  → Lead Score computed
  → "New Evaluation Ready" notification dispatched
        → Clinic coordinator receives: Magic Link (encrypted portal)
        → No PHI in email body — link only
  → Surgeon receives: Pre-Op Clinical Brief (portal-only, role-gated)
```

---

## 6. CRM Webhook Integration

The platform exposes outbound webhooks to sync lead data with external CRMs:

CRMIntegration MethodNextechREST webhook → patient record creationPatientNowREST webhook → lead intake syncCustom CRMsGeneric webhook payload (configurable per tenant)

---

## 7. Infrastructure Diagram

```
[Patient Mobile/Web]
       │  HTTPS / TLS 1.3
       ▼
[AWS ALB / CloudFront CDN]
       │
       ▼
[Laravel App Servers (EC2 / ECS)]
       │               │
       ▼               ▼
[MySQL RDS]       [Redis (Horizon)]
                        │
                        ▼
              [AI Processing Workers]
                        │
                        ▼
              [AWS HealthLake / GCP Healthcare API]
                  (HIPAA-certified PHI storage)
```
