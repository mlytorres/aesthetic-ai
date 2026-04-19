# Influencer Affiliate Program MVP - Implementation Ticket Breakdown

## Objective
Launch a HIPAA-compliant, multi-tenant influencer attribution and payout system where influencers can promote tenant intake links and earn payouts for qualified completed evaluations.

## Implementation Status (Updated: April 18, 2026)

### Progress Update (April 18, 2026 - FREE Plan Restore)
- Restored `free` billing plan in `DatabaseSeeder` so fresh seeds include admin-assignable FREE access again.
- Set FREE plan to `is_public = false` to keep it hidden from self-serve billing pages and reserved for super-admin assignment.
- Configured seeded FREE plan as unlimited (`max_procedures = null`, `max_evaluations_mo = null`) with full feature access for internal/testing tenants.

### Phase 1 Completed
- `TKT-B1` Core schema delivered:
  - Implemented tables: `affiliate_partners`, `affiliate_campaigns`, `campaign_assets`, `affiliate_links`, `attribution_events`, `affiliate_payout_ledgers`, `affiliate_terms_acceptances`.
  - Added `affiliate_link_id` to `evaluations`.
- `TKT-C1` Domain model foundation delivered:
  - Added tenant-scoped models, constants, casts, and relationships for affiliate entities.
- `TKT-C3` Approved asset enforcement delivered (MVP baseline):
  - Payout ledger generation is blocked when linked asset is not approved/revoked.
- `TKT-C4` Attribution event pipeline delivered:
  - Implemented `click`, `intake_started`, `evaluation_completed` events.
  - Added idempotency for completion and intake-start events.
- `TKT-C6` Payout hold baseline delivered:
  - `pending_hold` ledger entries created on qualified completion with `hold_until`.
- `TKT-D1` Tenant campaign/partner console delivered (baseline):
  - Added clinic pages for partner and campaign management with asset creation.
- `TKT-G1` Initial backend tests delivered:
  - Added feature coverage for click tracking, intake attribution, completion attribution, and payout ledger creation.

### Phase 2 Completed
- `TKT-C6` Payout review/release workflow expansion delivered:
  - Added review (`approved` / `rejected`) and release transitions with audit logging and hold-window checks.
  - Added clinic payout review UI page and route wiring.
  - Added regression tests for state transitions and tenant isolation.

### Phase 3 Completed
- `TKT-C2` Partner onboarding + terms acceptance (partial):
  - Added per-partner secure portal token and rotation flow.
  - Added terms acceptance endpoint storing version + accepted timestamp + hashed IP/user-agent metadata.
  - Added hard attribution gate: active partner + active campaign + approved asset + latest terms acceptance required.
- `TKT-D2` Influencer portal (partial):
  - Added public tenant-scoped affiliate portal page with aggregate-only metrics.
  - Added influencer tracking-link list and terms-gated portal UX (no patient-level data).
- `TKT-C3` Campaign operations expansion:
  - Added clinic link-generation endpoint to issue partner+campaign+asset tracking links.
- `Enablement` Demo + documentation:
  - Added `AffiliateProgramDemoSeeder` for realistic local testing data.
  - Added public partner guide page at `/affiliate-program/guide`.
  - Added operator and partner documentation in `docs/technical` and `docs/partners`.
  - Test safety hardening: forced SQLite in test bootstrap + added `.env.testing` defaults to prevent tests from hitting local development PostgreSQL data.

### Phase 4 Completed (April 18, 2026)
- `TKT-B2` PostgreSQL RLS policies delivered:
  - Migration enables RLS on all 7 affiliate tables (pgsql-only, no-op on SQLite).
  - `TenantContext::set()` syncs `app.current_tenant_id` GUC; `withAllTenants()` sets the `'all'` sentinel for admin reads.
  - Fixed multi-statement `DB::statement()` bug (PostgreSQL rejects multiple commands in a single prepared statement).
- `TKT-C2` Influencer invitation delivery delivered:
  - `AffiliatePartnerInviteMail` mailable auto-queued from `AffiliatePartnerController::store()`.
  - Luxury dark email template; mail failures caught and surfaced as flash message (non-fatal).
- `TKT-C5` Fraud scoring / velocity review queue delivered:
  - Migration adds `fraud_review_required` (bool, indexed), `fraud_reviewed_at`, `fraud_reviewed_by_user_id` to `affiliate_payout_ledgers`.
  - New `high_click_velocity_1h` fraud flag: click burst from same IP in last 1h per partner.
  - Per-tenant velocity thresholds via `tenant.settings` (`affiliate_fraud_daily_completion_threshold`, `affiliate_fraud_click_velocity_1h_threshold`), falling back to global `config/affiliate.php`.
  - Medium+ risk ledgers auto-flagged with `fraud_review_required = true` at creation time.
  - `AffiliatePayoutController::release` blocks payout release until fraud review is cleared.
  - `AffiliateFraudQueueController` with `index` / `clear` / `reject` actions + audit logging.
  - Inertia page `clinic/affiliate-fraud-queue` with stat cards, pending review table, and review history.
  - 9 Pest tests covering tenant isolation, state transitions, and release enforcement.
- `TKT-D3` Compliance review UI delivered (as fraud queue):
  - Clinic staff can view all fraud-flagged payouts, clear legitimate flags, or confirm and reject fraud in one dedicated page.

### Remaining
- `TKT-D2` Influencer portal polish (copy-to-clipboard UX, stronger terms lock, history views).
- `TKT-E1` Post-proof verification workflow.
- `TKT-F1` Security PASS/FAIL/WARNING report.
- `TKT-G2` Frontend Vitest + RTL coverage.
- `TKT-G3` End-to-end Playwright flow.

### Bugs discovered in review (April 18, 2026)
- ✅ `BUG-AF-01` — **Fixed Apr 18, 2026.** Short link moved to `/intake/s/{code}` (routes/web.php). Route name stays `intake.affiliate.short_link`.
- ✅ `BUG-AF-02` — **Fixed Apr 18, 2026.** `AffiliatePortalController::show` now calls `route('intake.affiliate.short_link', …)`.
- ✅ `BUG-AF-03` — **Fixed Apr 18, 2026.** `AffiliatePlatformController::index` now sums `STATUS_PENDING_HOLD + STATUS_APPROVED` via `whereIn`.
- ✅ `BUG-AF-04` — **Fixed Apr 18, 2026.** `TenantAdminController::updateFeatures` now accepts `affiliate_program_enabled`; admin tenant-show has a gold-accented toggle mirroring Video Consultations.
- ✅ `BUG-AF-05` — **Fixed Apr 18, 2026.** Added `affiliate.click` (60/min per token+IP) on `/intake/a/{token}` + `/intake/s/{code}` and `affiliate.portal` (60/min per token+IP) on the partner portal routes. Also added a bot filter in `AffiliateAttributionService::trackClick` that skips `click_count` bumps for known crawler UAs (records event with `metadata.bot=true`).
- ✅ `BUG-AF-06` — **Fixed Apr 18, 2026.** `clinic/affiliate-partners.tsx` Save button now uses the platform gold (`#C9A84C`).
- ✅ `BUG-AF-07` — **Fixed Apr 18, 2026.** `affiliate/portal.tsx` uses the Wayfinder-generated `acceptTerms({ partner, token }).url` helper.

## Compliance and Risk Gates (Launch Blockers)
- Compensation is tied to qualified evaluation completion events, not treatment outcomes.
- Influencers are classified as marketing partners, not clinical referrers.
- Legal review is required for federal and state anti-kickback and patient-brokering compliance before first payout.
- FTC disclosure requirements are mandatory in all campaign terms.
- Influencers may only use platform-approved campaign assets.

## Scope
- Tenant-facing campaign management and asset approval workflow.
- Influencer onboarding, terms acceptance, and unique link generation.
- Click-to-completion attribution pipeline.
- Fraud checks, compliance review, payout hold, and payout ledger.
- Aggregated reporting only for influencer users (no PHI exposure).

## Out of Scope (MVP)
- Automated payouts to external payment rails.
- Multi-touch attribution models.
- Self-serve influencer marketplace discovery.
- AI-generated ad creative.

## Workstream A - Architecture and Governance

### TKT-A1: ADR for Influencer Affiliate Architecture
Owner: ArchitectAgent  
Priority: P0  
Dependencies: None

Acceptance Criteria:
- ADR created in `docs/technical/adr/` with decision, alternatives, and consequences.
- ADR documents tenant isolation, PHI boundaries, and signed URL policy.
- ADR defines event-driven attribution and payout lifecycle states.

### TKT-A2: Legal and Compliance Readiness Checklist
Owner: SecurityAgent + Legal  
Priority: P0  
Dependencies: TKT-A1

Acceptance Criteria:
- Checklist includes federal and state referral law review gates.
- FTC disclosure language baseline is documented.
- Policy for prohibited medical claims and creative usage is documented.

## Workstream B - Database and RLS

### TKT-B1: Core Schema for Affiliate Program
Owner: DatabaseAgent  
Priority: P0  
Dependencies: TKT-A1

Tables:
- `affiliate_partners`
- `affiliate_campaigns`
- `campaign_assets`
- `affiliate_links`
- `attribution_events`
- `affiliate_payout_ledger`
- `affiliate_compliance_reviews`
- `affiliate_terms_acceptances`

Acceptance Criteria:
- Every new table includes `id`, `tenant_id`, `created_at`, `updated_at`, `deleted_at`.
- PHI-sensitive boundaries are explicitly documented in migration comments.
- Required indexes exist for tenant, foreign keys, status, and token lookups.
- Migrations are reversible and follow project naming conventions.

### TKT-B2: Row-Level Security Policies
Owner: DatabaseAgent + SecurityAgent  
Priority: P0  
Dependencies: TKT-B1

Acceptance Criteria:
- RLS prevents cross-tenant reads and writes on all new tables.
- Policies are tested with tenant A vs tenant B isolation scenarios.
- No policy allows wildcard access to partner attribution data.

## Workstream C - Backend (Laravel)

### TKT-C1: Domain Models, Policies, and Resources
Owner: BackendAgent  
Priority: P0  
Dependencies: TKT-B1

Acceptance Criteria:
- Models include tenant scope trait and soft deletes.
- Policies exist for every new model; controllers remain thin.
- API responses use JsonResource classes only.

### TKT-C2: Partner Onboarding and Terms Acceptance
Owner: BackendAgent  
Priority: P0  
Dependencies: TKT-C1

Acceptance Criteria:
- Influencer invitation and activation flow is tenant-scoped.
- Terms acceptance stores version, timestamp, IP metadata, and actor.
- Access is denied until latest required terms are accepted.

### TKT-C3: Campaign and Approved Asset Enforcement
Owner: BackendAgent  
Priority: P0  
Dependencies: TKT-C1

Acceptance Criteria:
- Link generation requires an approved campaign asset reference.
- System rejects payout eligibility when campaign asset is unapproved or revoked.
- Creative hash or asset version is stored with attribution metadata.

### TKT-C4: Attribution Event Pipeline
Owner: BackendAgent  
Priority: P0  
Dependencies: TKT-C1

Acceptance Criteria:
- Events captured: link click, intake start, evaluation complete.
- Tokenized attribution ID is used instead of exposing PHI.
- Idempotency keys prevent duplicate completion credits.

### TKT-C5: Fraud Detection and Review Queue
Owner: BackendAgent + SecurityAgent  
Priority: P1  
Dependencies: TKT-C4

Acceptance Criteria:
- Velocity checks for device and IP are configurable per tenant.
- Duplicate and suspicious activity rules create review flags.
- Flagged records are held from payout until resolved.

### TKT-C6: Payout Ledger and Hold Window
Owner: BackendAgent  
Priority: P0  
Dependencies: TKT-C4

Acceptance Criteria:
- Ledger statuses support `pending_hold`, `approved`, `rejected`, `released`.
- Hold window is configurable by tenant campaign settings.
- All state transitions are audit-logged and idempotent.

### TKT-C7: PHI Audit Logging Coverage
Owner: BackendAgent + SecurityAgent  
Priority: P0  
Dependencies: TKT-C1

Acceptance Criteria:
- Every service method touching PHI writes audit events.
- Logs contain actor, tenant, action, and resource reference only.
- Logs contain no raw PHI payloads.

## Workstream D - Frontend (Inertia React + TypeScript)

### TKT-D1: Tenant Campaign Console
Owner: FrontendAgent  
Priority: P0  
Dependencies: TKT-C3

Acceptance Criteria:
- Tenant users can create campaigns and payout settings with validation.
- Tenant users can upload and approve assets.
- UI clearly marks approved, revoked, and pending assets.

### TKT-D2: Influencer Portal
Owner: FrontendAgent  
Priority: P0  
Dependencies: TKT-C2, TKT-C3

Acceptance Criteria:
- Influencer can accept terms, view approved assets, and copy generated links.
- Influencer dashboard exposes only aggregate campaign metrics.
- No patient-level records are rendered or cached client-side.

### TKT-D3: Compliance Review UI
Owner: FrontendAgent + SecurityAgent  
Priority: P1  
Dependencies: TKT-C5

Acceptance Criteria:
- Review queue shows flagged events and reviewer decisions.
- Reviewer can set `approved`, `warning`, or `rejected` with reason.
- Decision history is immutable and timestamped.

## Workstream E - Integrations and Distribution Controls

### TKT-E1: Post Proof Verification Workflow
Owner: IntegrationAgent + FrontendAgent  
Priority: P1  
Dependencies: TKT-D2

Acceptance Criteria:
- Influencer submits social post URL or screenshot as campaign proof.
- Proof is linked to campaign asset ID and version.
- Proof review result impacts payout eligibility if required by tenant policy.

### TKT-E2: Generic Outbound Webhook (Optional MVP+)
Owner: IntegrationAgent  
Priority: P2  
Dependencies: TKT-C6

Acceptance Criteria:
- Webhook payload excludes PHI and uses secure evaluation reference.
- Payloads are HMAC-signed and idempotent.
- Retry policy supports exponential backoff up to five attempts.

## Workstream F - Security Validation

### TKT-F1: Security Review Report
Owner: SecurityAgent  
Priority: P0  
Dependencies: TKT-B2, TKT-C7, TKT-D2

Acceptance Criteria:
- PASS/FAIL/WARNING report covers PHI exposure, authZ, audit logs, encryption, signed URLs, and third-party risk.
- Report includes remediation tickets for any FAIL or WARNING status.
- Production launch is blocked on unresolved FAIL items.

## Workstream G - QA and Testing

### TKT-G1: Backend Pest Feature Tests
Owner: QAAgent  
Priority: P0  
Dependencies: TKT-C6

Acceptance Criteria:
- Multi-tenant isolation tests for all affiliate tables and endpoints.
- Attribution idempotency tests for duplicate completion events.
- Payout hold and release workflow tests.
- PHI audit logging tests for service methods.

### TKT-G2: Frontend Vitest + RTL Coverage
Owner: QAAgent  
Priority: P1  
Dependencies: TKT-D3

Acceptance Criteria:
- Terms acceptance guard behavior tested.
- Approved-only asset selection and link generation tested.
- Aggregate-only influencer dashboard rendering tested.

### TKT-G3: E2E Playwright Flow
Owner: QAAgent  
Priority: P1  
Dependencies: TKT-D2, TKT-C4

Acceptance Criteria:
- End-to-end flow validates click -> intake -> evaluation complete -> ledger entry.
- Review hold flow validates rejection prevents release.
- Cross-tenant data access attempts are denied.

## Rollout Plan

### Phase 1: Internal Pilot (2 weeks)
- 3 tenants, 10 influencers, payout caps enabled.
- Manual compliance reviews for all payouts.
- Weekly legal and security checkpoint.

### Phase 2: Controlled Expansion (4-6 weeks)
- Increase tenant cohort after KPI and security sign-off.
- Enable tenant-configurable hold windows and fraud thresholds.
- Add optional webhook notification and reporting enhancements.

## Success Metrics
- Cost per qualified evaluation by tenant and influencer.
- Evaluation completion rate by campaign.
- Fraud rejection rate and manual review pass rate.
- Payout processing accuracy and dispute rate.
- Zero cross-tenant data exposure incidents.

## Definition of Done (MVP)
- All P0 tickets completed and accepted.
- Security review has no FAIL statuses.
- QA critical-path tests pass in CI.
- Legal sign-off completed for launch jurisdictions.
- Runbook for incident response and payout rollback is documented.
