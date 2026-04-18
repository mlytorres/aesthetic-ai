# Affiliate Program Implementation and Usage Guide

Last updated: April 18, 2026

## What is implemented
- Partner management (clinic dashboard)
- Campaign + asset management
- Link generation per partner/campaign/asset
- Attribution pipeline: click -> intake_started -> evaluation_completed
- Payout ledger with hold/review/release lifecycle
- Partner portal with terms acceptance and aggregate metrics
- Hard backend gating for eligibility (partner/campaign/asset/terms)

## Core routes
- Clinic management:
  - `/clinic/affiliates/partners`
  - `/clinic/affiliates/campaigns`
  - `/clinic/affiliates/payouts`
- Tracking:
  - `/intake/a/{token}`
- Partner portal:
  - `/affiliate-portal/{partner}/{token}`
- Public guide:
  - `/affiliate-program/guide`

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
2. Open accepted partner portal URL from seeder output.
3. Copy a tracking URL from partner portal.
4. Open tracking URL and submit an intake evaluation.
5. Verify payout appears in clinic payout queue.

## Security controls currently enforced
- Tenant-scoped model access
- Signed partner portal token validation
- Hashing of IP and user-agent for terms acceptance metadata
- Aggregate-only partner-facing analytics (no patient-level data)
- Terms + status + approved asset gate before attribution credit

## Current gaps (next phases)
- PostgreSQL RLS policies for affiliate tables
- Fraud scoring / velocity-based review queue
- Post-proof verification workflow
- Frontend unit tests + E2E coverage for portal/review queue
