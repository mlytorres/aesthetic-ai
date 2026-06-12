# Clinic admin

_Last verified: 2026-06-12_

Owner/admin reference for configuring the clinic workspace.

## Clinic settings

**Clinic Settings**: name, contact details, and **logo** (shown on the intake wizard and patient-facing pages). Keep the logo current — it's what patients see first.

## Team

**Team**: invite staff and assign roles (admin, coordinator, surgeon, viewer). Role changes apply on next login. Remove access promptly when someone leaves — evaluations contain PHI.

## Integrations

- **Outbound webhook** — push completed evaluations into your CRM (e.g., MiamiLife). Configure the URL, **rotate the secret** when needed, and use **Send test** to verify.
- **Webhooks Hub** — delivery log with per-delivery **retry**; first stop when "evaluations aren't reaching the CRM."
- **API tokens** — for custom integrations; create scoped tokens and revoke unused ones.
- **CRM API docs** — payload reference for your integration developer.

## Billing (owner)

**Billing** shows your plan, evaluation limits, and Stripe-managed payment method. If the subscription lapses, the intake widget shows a "temporarily unavailable" page and staff pages are gated until payment — patients are never shown an error with your name on it.

## Security & compliance

- **BAA** — a signed Business Associate Agreement is required before patient intake runs; the platform admin manages BAA status.
- **2FA** — privileged users (owner/admin and super-admin) must complete two-factor setup.
- **Magic links** — coordinator one-click links from notification emails are single-use and expire; treat them like passwords.
- **Impersonation** — platform support can impersonate accounts for troubleshooting; these sessions are audit-logged.

## Tips

- After changing the webhook secret, update the CRM side immediately — deliveries fail signature checks until both match.
- Check the Webhooks Hub after any CRM deployment; a changed endpoint is the most common silent breakage.
