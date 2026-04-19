# Affiliate Program Implementation and Usage Guide

Last updated: April 18, 2026

## Known issues (fix before first paid pilot)

1. ✅ ~~Route collision `/intake/a/{token}` vs `/intake/a/{code}`~~ — **fixed Apr 18, 2026.** Short link now lives at `/intake/s/{code}` (route name `intake.affiliate.short_link`).
2. ✅ ~~Broken route name in `AffiliatePortalController`~~ — **fixed Apr 18, 2026.** Portal now generates the short URL via `route('intake.affiliate.short_link', …)`.
3. ✅ ~~Fatal in Admin Affiliate Audit~~ — **fixed Apr 18, 2026.** `AffiliatePlatformController::index` now aggregates `STATUS_PENDING_HOLD + STATUS_APPROVED` via the existing constants.
4. ✅ ~~No super-admin toggle for the feature flag~~ — **fixed Apr 18, 2026.** `PATCH /admin/tenants/{id}/features` now accepts `affiliate_program_enabled`; the admin tenant-show page has a matching toggle next to Video Consultations.
5. ✅ ~~No rate limiting~~ — **fixed Apr 18, 2026.** Named limiters `affiliate.click` (60/min per token+IP) and `affiliate.portal` (60/min per token+IP). UA bot heuristic in `AffiliateAttributionService::trackClick` skips `click_count` increments for crawlers while still recording the event with `metadata.bot = true`.
6. ✅ ~~No PostgreSQL RLS on affiliate tables~~ — **fixed Apr 18, 2026.** Migration `2026_04_18_060000` enables RLS on all 7 affiliate tables. `TenantContext::set()` syncs the `app.current_tenant_id` GUC; `withAllTenants()` sets the `'all'` sentinel for admin reads.
7. ✅ ~~No fraud velocity review queue~~ — **fixed Apr 18, 2026.** See Fraud Queue section below.

## What is implemented
- Partner management (clinic dashboard)
- Campaign + asset management
- Link generation per partner/campaign/asset
- Attribution pipeline: click → intake_started → evaluation_completed
- Payout ledger with hold / review / release lifecycle
- Partner portal with terms acceptance and aggregate metrics
- Hard backend gating for eligibility (partner / campaign / asset / terms)
- PostgreSQL RLS on all 7 affiliate tables (defense-in-depth over application-layer tenant scope)
- Fraud velocity scoring with 5 flags and a dedicated clinic review queue
- Partner invite email auto-queued on partner creation

## Core routes
- Clinic management:
  - `GET  /clinic/affiliates/partners`
  - `GET  /clinic/affiliates/campaigns`
  - `GET  /clinic/affiliates/payouts`
  - `GET  /clinic/affiliates/fraud-queue` ← fraud velocity review queue
  - `PATCH /clinic/affiliates/fraud-queue/{id}/clear`
  - `PATCH /clinic/affiliates/fraud-queue/{id}/reject`
- Tracking:
  - `GET /intake/a/{token}` — full token redirect
  - `GET /intake/s/{code}` — short vanity code redirect
- Partner portal:
  - `GET /affiliate-portal/{partner}/{token}`
- Admin audit:
  - `GET /admin/affiliates`
- Public guide:
  - `GET /affiliate-program/guide`

## Fraud Queue

### How it works
1. On every `evaluation_completed` event, `AffiliateAttributionService::calculateFraudFlags()` runs synchronously before the payout ledger is created.
2. Five signals are evaluated:

| Flag | Trigger |
|---|---|
| `missing_fingerprint` | IP or user-agent hash is null |
| `duplicate_ip_24h` | Same IP hash on 2+ completions for this partner in last 24 h |
| `high_click_velocity_1h` | Same IP hash on more than N clicks for this partner in last 1 h |
| `duplicate_ua_1h` | Same user-agent hash on 2+ completions for this partner in last 1 h |
| `high_velocity_24h` | Partner exceeds N total completions in last 24 h |

3. Risk level: 0 flags = `low`, 1 flag = `medium`, 2+ flags = `high`.
4. Ledgers at `medium` or higher get `fraud_review_required = true` and appear in the clinic fraud queue.
5. Release is **blocked** on any ledger where `fraud_review_required = true` and `fraud_reviewed_at IS NULL`.

### Thresholds
Thresholds are read from `tenant.settings` first, falling back to `config/affiliate.php` / env vars:

| Setting key | Env var | Default |
|---|---|---|
| `affiliate_fraud_daily_completion_threshold` | `AFFILIATE_FRAUD_DAILY_COMPLETION_THRESHOLD` | 10 |
| `affiliate_fraud_click_velocity_1h_threshold` | `AFFILIATE_FRAUD_CLICK_VELOCITY_1H_THRESHOLD` | 5 |
| — | `AFFILIATE_FRAUD_REVIEW_REQUIRED_FROM_RISK` | `medium` |

### Queue actions
- **Clear** — marks the payout as fraud-reviewed (not fraud); it proceeds through normal approve/release flow.
- **Confirm Fraud** — sets `status = rejected` and `rejection_reason = 'Confirmed fraud via velocity review queue.'`

## Seed demo data for local testing
Run migrations then seed affiliate demo data:

```bash
php artisan migrate --no-interaction
php artisan db:seed --class=Database\\Seeders\\AffiliateProgramDemoSeeder --no-interaction
```

Seeder creates:
- 1 tenant campaign (`affiliate-spring-2026`)
- approved + pending assets
- 3 sample partners (accepted terms, no terms, paused)
- tracking links
- sample attribution and payout ledger records

## Suggested demo flow
1. Open clinic affiliate pages and review partners/campaigns/payouts.
2. Open the fraud queue at `/clinic/affiliates/fraud-queue` to see flagged payouts.
3. Open accepted partner portal URL from seeder output.
4. Copy a tracking URL from partner portal.
5. Open tracking URL and submit an intake evaluation.
6. Verify payout appears in clinic payout queue; if fraud flags triggered, review in fraud queue first.

## Security controls currently enforced
- Tenant-scoped model access (application-layer `HasTenantScope` + database-layer PostgreSQL RLS)
- Signed partner portal token validation
- Hashing of IP and user-agent (HMAC-SHA256 keyed on `app.key`) for all attribution events
- Aggregate-only partner-facing analytics (no patient-level data)
- Terms + status + approved asset gate before attribution credit
- Rate limiting on all public affiliate endpoints (60 req/min per token+IP)
- Bot UA heuristic prevents click inflation from crawlers and link-preview agents
- Fraud velocity flags on all payout ledger creation (5 signals, 3 risk levels)
- Release blocked until fraud review cleared for medium/high risk payouts
- All payout state transitions audit-logged

## Remaining gaps (launch blockers)
- Post-proof verification workflow (`TKT-E1`)
- Security PASS/FAIL/WARNING report (`TKT-F1`)
- Frontend Vitest + Playwright E2E coverage (`TKT-G2`, `TKT-G3`)
- Legal sign-off on federal + state anti-kickback, patient-brokering, and FTC disclosure baseline — required before first payout
