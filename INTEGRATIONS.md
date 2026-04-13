# INTEGRATIONS.md — CRM Webhooks & Embed SDK

> Complete guide for integrating AestheticAI with existing clinic CRMs and embedding the intake widget on clinic websites.

---

## 1. Embed Widget

### Quick Start (5 minutes)

Add to any page where you want the intake form to appear:

```html
<!DOCTYPE html>
<html>
<head>
  <title>Free Consultation — Rhinoplasty</title>
</head>
<body>

  <!-- 1. Widget container -->
  <div id="aestheticai-widget"></div>

  <!-- 2. Load widget script -->
  <script
    src="https://cdn.aestheticai.com/widget/v1/loader.js"
    data-clinic-id="YOUR_CLINIC_ID"
    data-procedure="rhinoplasty"
    data-theme="luxury-dark"
    data-language="en"
    async
  ></script>

</body>
</html>
```

### Widget Configuration Options

| Attribute | Required | Values | Default | Description |
|-----------|----------|--------|---------|-------------|
| `data-clinic-id` | Yes | UUID string | — | Your unique clinic identifier |
| `data-procedure` | No | See procedure slugs | — | Pre-select a procedure |
| `data-theme` | No | `luxury-dark`, `luxury-light`, `clinical`, `custom` | `luxury-dark` | Visual theme |
| `data-language` | No | `en`, `es` | `en` | Interface language |
| `data-height` | No | CSS value | `700px` | Widget container height |
| `data-button-label` | No | String | `Start Free Evaluation` | CTA button text |
| `data-entry-point` | No | `full`, `button` | `full` | `full` = shows immediately, `button` = click to open |

### Procedure Slugs

| Procedure | Slug |
|-----------|------|
| Rhinoplasty | `rhinoplasty` |
| Liposuction 360 | `lipo_360` |
| Brazilian Butt Lift | `bbl` |
| Breast Augmentation | `breast_augmentation` |
| Facelift | `facelift` |
| Blepharoplasty | `blepharoplasty` |
| Tummy Tuck | `abdominoplasty` |
| J-Plasma Abdomen | `j_plasma_abdomen` |
| (Patient chooses) | *(omit attribute)* |

### JavaScript API

```javascript
// Listen for evaluation completion
window.addEventListener('message', function(event) {
  if (event.origin !== 'https://app.aestheticai.com') return;

  switch(event.data.type) {
    case 'AESTHETICAI_EVALUATION_COMPLETE':
      console.log('Evaluation token:', event.data.evaluationToken);
      // Optional: redirect patient to thank you page
      window.location.href = '/thank-you';
      break;

    case 'AESTHETICAI_STEP_CHANGED':
      console.log('Patient on step:', event.data.stepName);
      // Optional: track in your analytics
      break;

    case 'AESTHETICAI_ABANDONED':
      console.log('Patient left at step:', event.data.lastStep);
      break;
  }
});
```

### Camera Permissions

The photo capture step requires camera access. Add this to your page's HTTP headers:

```
Permissions-Policy: camera=(self "https://app.aestheticai.com")
```

Or as an HTML meta tag:

```html
<meta http-equiv="Permissions-Policy" content="camera=(self 'https://app.aestheticai.com')">
```

---

## 2. Webhook Integration

When a patient completes their evaluation, AestheticAI sends a signed webhook to your configured endpoint.

### Webhook Setup

1. Go to **Dashboard → Settings → Integrations → Webhook**
2. Enter your endpoint URL (must be HTTPS)
3. Copy your webhook secret
4. Click **Test Webhook** to verify connectivity

### Payload Format

```json
{
  "event": "evaluation.completed",
  "api_version": "2025-01",
  "idempotency_key": "eval_01HXYZ9ABC123",
  "timestamp": "2025-06-15T14:32:00Z",
  "data": {
    "evaluation_token": "eyJhbGc...",
    "procedure_interest": "rhinoplasty",
    "lead_score": 87,
    "priority": "high",
    "ready_for_call": true,
    "timeline": "within_3_months",
    "budget_range": "15000_25000",
    "photos_available": true,
    "ai_analysis_complete": true,
    "portal_url": "https://app.aestheticai.com/portal/clinic/TOKEN"
  }
}
```

**Note:** The payload intentionally contains no PHI (no name, email, phone). Use the `evaluation_token` to fetch full details via the API.

### Signature Verification

Every webhook is signed with HMAC-SHA256. Always verify before processing:

```php
// PHP
function verifyWebhookSignature(string $payload, string $signature, string $secret): bool
{
    $expected = 'sha256=' . hash_hmac('sha256', $payload, $secret);
    return hash_equals($expected, $signature);
}

// Usage in your webhook handler:
$signature = $_SERVER['HTTP_X_SYMETRIHEALTH_SIGNATURE'];
$payload = file_get_contents('php://input');

if (!verifyWebhookSignature($payload, $signature, $yourWebhookSecret)) {
    http_response_code(401);
    exit('Invalid signature');
}

$event = json_decode($payload, true);
```

```javascript
// Node.js
const crypto = require('crypto');

function verifySignature(payload, signature, secret) {
  const expected = 'sha256=' + crypto
    .createHmac('sha256', secret)
    .update(payload)
    .digest('hex');
  return crypto.timingSafeEqual(
    Buffer.from(expected),
    Buffer.from(signature)
  );
}

// Express.js route
app.post('/webhook/symetrihealth', express.raw({ type: 'application/json' }), (req, res) => {
  const signature = req.headers['x-symetrihealth-signature'];
  if (!verifySignature(req.body, signature, process.env.WEBHOOK_SECRET)) {
    return res.status(401).send('Invalid signature');
  }
  const event = JSON.parse(req.body);
  // Process event...
  res.sendStatus(200);
});
```

### Webhook Events

| Event | When Triggered |
|-------|---------------|
| `evaluation.started` | Patient begins intake form |
| `evaluation.photos_uploaded` | Patient uploads photos |
| `evaluation.completed` | Patient submits evaluation |
| `evaluation.analysis_complete` | AI processing finished |
| `evaluation.status_changed` | Coordinator updates status |

### Retry Policy

If your endpoint returns a non-2xx response or times out (> 10 seconds), we retry with exponential backoff:

```
Attempt 1: Immediate
Attempt 2: 30 seconds later
Attempt 3: 2 minutes later
Attempt 4: 10 minutes later
Attempt 5: 1 hour later
After 5 failures: Webhook marked as failed, notification sent to clinic admin
```

Your endpoint should respond with `200 OK` as quickly as possible and process the event asynchronously. Do not perform heavy operations (CRM API calls, database writes) synchronously in the webhook handler.

### Rotating Your Webhook Secret

If your webhook secret is ever compromised, rotate it immediately:

1. Go to **Dashboard → Settings → Integrations → Webhook → Rotate Secret**
2. A new secret is generated. Your **old secret remains valid for 24 hours** to allow a grace period for in-flight deliveries.
3. Update your webhook handler with the new secret before the grace period expires.
4. Confirm rotation by clicking **Verify New Secret** — this fires a test `ping` event signed with the new secret.

During the rotation grace period, AestheticAI signs payloads with **both** secrets and includes both in the `X-SymetriHealth-Signature` header as comma-separated values. Accept either:

```php
function verifyWithRotation(string $payload, string $header, string $oldSecret, string $newSecret): bool
{
    $signatures = explode(',', $header);
    foreach ($signatures as $sig) {
        if (verifyWebhookSignature($payload, trim($sig), $newSecret)) return true;
        if (verifyWebhookSignature($payload, trim($sig), $oldSecret)) return true;
    }
    return false;
}
```

---

## 3. REST API

### Authentication

```http
GET /api/v1/evaluations
Authorization: Bearer {your_api_token}
X-Clinic-ID: {your_clinic_id}
```

> **Why `X-Clinic-ID`?** The platform normally resolves your clinic via subdomain (e.g. `yourslug.aestheticai.com`). Direct REST API calls from your backend server do not go through a subdomain, so the `X-Clinic-ID` header is used instead for tenant resolution. This header is validated against the API token — a token cannot be used to access a different clinic's data.

API tokens are generated in **Dashboard → Settings → API → Generate Token**

### Fetch Evaluation Details

```http
GET /api/v1/evaluations/{evaluation_token}
```

**Response:**
```json
{
  "data": {
    "id": "eval_01HXYZ9ABC123",
    "status": "complete",
    "procedure_interest": "rhinoplasty",
    "lead_score": 87,
    "priority": "high",
    "created_at": "2025-06-15T14:00:00Z",
    "completed_at": "2025-06-15T14:32:00Z",
    "patient": {
      "id": "pat_01ABC",
      "name": "Jane Doe",
      "email": "jane@example.com",
      "phone": "+13055550123"
    },
    "quiz_summary": {
      "timeline": "within_3_months",
      "budget_range": "15000_25000",
      "prior_surgery": false,
      "concerns": ["tip", "bridge"],
      "breathing_issues": false
    },
    "ai_analysis": {
      "overall_score": 82,
      "symmetry_score": 78,
      "facial_thirds_balance": 85,
      "top_recommendations": [
        { "procedure": "rhinoplasty", "match_score": 94, "reasoning": "Strong candidate based on..." }
      ]
    },
    "photos": {
      "front": { "url": "https://...", "expires_at": "2025-06-15T14:47:00Z" },
      "left_profile": { "url": "https://...", "expires_at": "2025-06-15T14:47:00Z" },
      "right_profile": { "url": "https://...", "expires_at": "2025-06-15T14:47:00Z" }
    }
  }
}
```

### Update Evaluation Status

```http
PATCH /api/v1/evaluations/{evaluation_token}/status

{
  "status": "contacted",
  "notes": "Called patient, consultation booked for July 2",
  "follow_up_at": "2025-07-02T10:00:00Z"
}
```

### List Evaluations

```http
GET /api/v1/evaluations?priority=high&status=complete&page=1&per_page=20
```

---

## 4. Native CRM Integrations

### HubSpot

**Setup:**
1. Dashboard → Settings → Integrations → HubSpot
2. Authorize HubSpot connection (OAuth)
3. Map evaluation fields to HubSpot contact properties
4. Enable automatic contact creation

**What syncs automatically:**
- New evaluation → Creates/Updates HubSpot Contact
- Lead score → Maps to custom property `aestheticai_lead_score`
- Priority → Maps to HubSpot Deal stage
- Procedure interest → Maps to custom property
- Status changes → Updates Deal stage

### Nextech

**Setup:**
1. Dashboard → Settings → Integrations → Nextech
2. Enter your Nextech API credentials
3. Map tenant locations to Nextech practice IDs
4. Enable sync

**What syncs:**
- Completed evaluation → Creates Nextech patient record (if not exists)
- Evaluation data → Creates New Patient Inquiry record
- Lead score → Added to patient notes

### PatientNow

*(Phase 4 — not yet available)*

### Generic Webhook + Zapier

Any CRM can be connected via our webhook + Zapier:

1. Create a Zapier Zap: **Webhook (Catch Hook)** → **Your CRM (Create Contact)**
2. In **Dashboard → Settings → Integrations → Webhook**, enter your Zapier Catch Hook URL and save
3. The `evaluation.completed` event fires automatically once AI scoring finishes — all lead fields (`lead_score`, `priority`, `ready_for_call`, `timeline`, `budget_range`) are included in the payload
4. Add a second Zapier step — **Code (Run JavaScript)** or **Webhooks (GET request)** — to call our API and fetch the full patient profile using the `evaluation_token` from the webhook payload:

```http
GET https://app.aestheticai.com/api/v1/evaluations/{evaluation_token}
Authorization: Bearer {your_api_token}
X-Clinic-ID: {your_clinic_id}
```

The response includes the patient's name, email, phone, quiz answers, and signed photo URLs — everything you need to create a contact in your CRM.

**Zapier Zap structure (recommended):**

```
Trigger  →  Webhooks by Zapier (Catch Hook)
Step 2   →  Webhooks by Zapier (GET)
              URL: https://app.aestheticai.com/api/v1/evaluations/{{evaluation_token}}
              Headers: Authorization: Bearer aai_live_xxx
                       X-Clinic-ID: your-clinic-uuid
Step 3   →  Your CRM (e.g. HubSpot → Create/Update Contact)
              Name:  {{data.patient.name}}
              Email: {{data.patient.email}}
              Phone: {{data.patient.phone}}
```

---

## 5. Calendar / Booking Integration

### Google Calendar

Link the clinic's Google Calendar to allow patients to book consultations directly from their evaluation portal.

**Setup:** Dashboard → Settings → Integrations → Google Calendar → Connect

**Behavior:**
- Available consultation slots fetched from clinic's Google Calendar
- Patient selects time from evaluation completion screen
- Google Calendar event created with evaluation reference
- Reminder sent to patient 24 hours before

### Booking Confirmation Webhook

When a booking is confirmed through Google Calendar integration:

```json
{
  "event": "consultation.booked",
  "data": {
    "evaluation_token": "...",
    "scheduled_at": "2025-07-02T10:00:00Z",
    "duration_minutes": 45,
    "calendar_event_id": "..."
  }
}
```
