# Troubleshooting & FAQ

_Last verified: 2026-06-12_

## Intake & widget

| Problem | Likely cause | Fix |
|---------|--------------|-----|
| Widget shows "temporarily unavailable" | Subscription lapsed or evaluation cap reached | Owner: check **Billing** |
| Patient says photos won't upload | Slow connection or unsupported file | Have them retry on Wi-Fi with the phone camera (not a screenshot) |
| Patient never appears in the queue | They didn't finish the wizard | Send the intake link again; check Analytics funnel for the drop-off step |
| Quiz asks odd questions for a procedure | Quiz definition issue | Report it — quizzes are managed by the platform admin |

## Evaluations

| Problem | Likely cause | Fix |
|---------|--------------|-----|
| Evaluation stuck on "analyzing" | AI pipeline delay | Wait a few minutes; if it persists, contact support with the evaluation ID |
| Can't change status or add notes | Your role is viewer/surgeon | Ask an owner/admin to adjust your role under **Team** |
| Can't download the clinical brief | Viewers can't access briefs | Same — role change needed |
| Export button missing | Coordinator+ only | Role change needed |

## CRM sync

| Problem | Likely cause | Fix |
|---------|--------------|-----|
| Evaluations not arriving in the CRM | Webhook URL or secret mismatch | **Integrations**: send a test; check **Webhooks Hub** for failed deliveries and retry |
| Deliveries failing with 401/403 | Secret rotated on one side only | Re-sync the secret on both sides |
| Duplicate leads in the CRM | CRM not de-duplicating on evaluation ID | Have the CRM key on the evaluation ID in the payload |

## Access & login

| Problem | Likely cause | Fix |
|---------|--------------|-----|
| Magic link "expired or invalid" | Links are single-use and time-limited | Log in normally; open the evaluation from the queue |
| Locked out after inactivity | HIPAA session timeout | Sign in again — work in progress is saved server-side on each action |
| 2FA device lost | — | Use a recovery code; owner/support can reset 2FA |
| Can't see Affiliates section | Feature not enabled for your clinic | Owner: contact support to enable the affiliate program |

## Still stuck?

Grab the evaluation ID (not patient name), the screen, and the time, and contact your owner/admin — they can check integrations, billing, and the audit log.
