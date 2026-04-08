# CONTRIBUTING.md — Development Workflow & Standards

> Engineering standards, branching strategy, and development workflow for AestheticAI.

---

## Core Principles

1. **HIPAA First:** Security and compliance are never optional. Every PR is reviewed against the SECURITY.md checklist.
2. **Tenant Isolation is Sacred:** A bug that exposes one clinic's data to another is a P0 incident. Tests must prove isolation.
3. **Type Everything:** No `any` in TypeScript. No untyped PHP. PHPStan level 8.
4. **Async by Default:** User-facing actions never wait for AI processing.
5. **Fail Secure:** When uncertain, deny access. Never expose data on error.

---

## Branching Strategy

```
main           → Production. Protected. Requires 2 approvals.
staging        → Mirrors production. Deploys automatically.
develop        → Integration branch. All feature PRs target here.
feature/*      → Feature branches (e.g., feature/bbl-quiz)
fix/*          → Bug fixes (e.g., fix/lead-score-calculation)
hotfix/*       → Urgent production fixes (merge to main + develop)
```

**Branch naming:**
```
feature/P1-S2-photo-capture-step
fix/wizard-progress-bar-mobile
hotfix/tenant-scope-missing-on-evaluations
```

---

## Git Workflow

```bash
# Start a new feature
git checkout develop
git pull origin develop
git checkout -b feature/your-feature-name

# Work, commit often with conventional commits
git commit -m "feat(quiz): add BMI branching for BBL procedure"
git commit -m "fix(photos): handle camera permission denial gracefully"
git commit -m "test(tenant): verify coordinator cannot access other tenant evaluations"

# Push and open PR
git push origin feature/your-feature-name
# Open PR → develop
```

### Conventional Commit Format

```
type(scope): description

Types: feat | fix | test | docs | refactor | chore | security
Scope: quiz | photos | dashboard | auth | ai | webhooks | billing

Examples:
feat(quiz): add dynamic branching for prior rhinoplasty history
fix(auth): magic link expiry not enforced after first use
security(photos): enforce signed URL expiry on coordinator dashboard
test(tenant): add cross-tenant isolation tests for evaluations
```

---

## Code Standards

### PHP / Laravel

```php
<?php

declare(strict_types=1); // Required on every PHP file

namespace App\Services;

use App\Models\Evaluation;
use App\Services\AuditLog;
use App\Exceptions\TenantContextMissingException;

/**
 * Handles evaluation lifecycle operations.
 *
 * HIPAA: This service processes PHI. All access is audit-logged.
 */
final class EvaluationService
{
    public function __construct(
        private readonly LeadScoringService $leadScoring,
        private readonly AuditLog $auditLog,
    ) {}

    /**
     * @throws TenantContextMissingException
     */
    public function getForDashboard(int $evaluationId): Evaluation
    {
        // Tenant scope applied automatically via HasTenantScope
        $evaluation = Evaluation::with(['patient', 'photos'])->findOrFail($evaluationId);

        // PHI access must always be logged
        $this->auditLog->record('evaluation.dashboard.viewed', $evaluation);

        return $evaluation;
    }
}
```

**PHPStan:** All code must pass level 8. Run: `./vendor/bin/phpstan analyse`

**Formatting:** Laravel Pint. Run: `./vendor/bin/pint`

### TypeScript / React

```typescript
// All TypeScript must be strict — no `any`
// tsconfig.json has strict: true

import { type FC, useState } from 'react';
import { useForm } from '@inertiajs/react';

// Use interfaces for props, not type aliases (prefer interfaces for objects)
interface EvaluationCardProps {
  evaluation: Evaluation;
  onStatusChange: (status: EvaluationStatus) => void;
}

// Named exports for components, not default
export const EvaluationCard: FC<EvaluationCardProps> = ({ evaluation, onStatusChange }) => {
  const [isExpanded, setIsExpanded] = useState(false);

  return (
    <div className="rounded-xl border border-white/10 bg-white/5 p-6">
      {/* Component content */}
    </div>
  );
};
```

**Formatting:** Prettier. Run: `npm run format`
**Linting:** ESLint. Run: `npm run lint`
**Type check:** `npm run typecheck`

---

## Testing Requirements

### Coverage Requirements

| Area | Minimum Coverage |
|------|-----------------|
| Services (business logic) | 90% |
| API Controllers | 80% |
| Models + Traits | 80% |
| Jobs | 85% |
| Frontend components (critical paths) | 60% |

### Required Test Cases (Non-Negotiable)

Every new feature must include tests for:

```php
// 1. Tenant isolation (CRITICAL)
it('prevents coordinator from accessing evaluations from another tenant', function () {
    // ...
});

// 2. PHI audit logging
it('logs PHI access when evaluation is viewed', function () {
    // ...
});

// 3. Authorization (role-based)
it('prevents surgeons from accessing patient contact info', function () {
    // ...
});

// 4. Business logic happy path
it('calculates lead score correctly for high-value rhinoplasty lead', function () {
    // ...
});

// 5. Business logic edge cases
it('assigns minimum score when evaluation is incomplete', function () {
    // ...
});
```

### Running Tests

```bash
# All tests
php artisan test

# Specific suite
php artisan test --testsuite=Feature
php artisan test --testsuite=Unit

# With coverage
php artisan test --coverage --min=80

# Frontend tests
npm run test
npm run test:coverage
```

---

## Pull Request Process

### PR Template

Every PR must include:

```markdown
## What does this PR do?
Brief description of the change.

## Type of change
- [ ] New feature
- [ ] Bug fix
- [ ] Security fix
- [ ] Refactor
- [ ] Documentation

## HIPAA Checklist
- [ ] No PHI in log statements
- [ ] PHI access is audit-logged
- [ ] No PHI in API error responses
- [ ] File access uses signed URLs
- [ ] Tenant scope applied to all queries

## Testing
- [ ] Added tests for new functionality
- [ ] Tenant isolation test included (if touching patient data)
- [ ] All existing tests pass

## Screenshots (if UI change)
Before / After screenshots

## Notes for Reviewer
Any context that helps review.
```

### Review Requirements

- **All PRs:** 1 approval required
- **PHI-touching PRs:** 2 approvals required, one must be a senior dev
- **Security changes:** Must be approved by the designated security reviewer
- **Database migrations:** Must include rollback migration

---

## Local Development Setup

```bash
# Prerequisites
# PHP 8.3, Composer, Node 20+, PostgreSQL 16, Redis

# Clone
git clone https://github.com/your-org/aesthetic-ai
cd aesthetic-ai

# Backend
composer install
cp .env.example .env.local
php artisan key:generate

# Configure .env.local:
# DB_CONNECTION=pgsql
# DB_DATABASE=aesthetic_ai_dev
# QUEUE_CONNECTION=redis
# AWS_* credentials (for S3 and Rekognition)

# Database
createdb aesthetic_ai_dev
php artisan migrate
php artisan db:seed

# Frontend
npm install

# Start everything (uses concurrently)
npm run dev:full
# → Laravel dev server: http://localhost:8000
# → Vite HMR: http://localhost:5173
# → Horizon dashboard: http://localhost:8000/horizon

# Queue worker (in separate terminal)
php artisan horizon
```

### Feature Flags (Local)

```env
# .env.local — disable AI features for local dev
FEATURE_AI_VISION=false          # Use mock responses instead of Rekognition
FEATURE_PHOTO_ANALYSIS=false     # Skip photo processing
FEATURE_WEBHOOKS=false           # Don't fire external webhooks
```

---

## Deployment

### Staging

Automatic deployment on merge to `develop`:
```
GitHub Actions → Build → PHPStan + Tests → Docker build → ECS deploy (staging)
```

### Production

Manual deploy after staging validation:
```
GitHub Actions → Create release tag → Approval gate → ECS deploy (production)
```

### Database Migrations in Production

**Never run migrations automatically in production.**

```bash
# Production migration process:
# 1. Review migration for backwards compatibility
# 2. Deploy new code (old code must work with new schema)
# 3. Run migration manually during low-traffic window
# 4. Verify, then remove backwards-compat shims in next deploy

php artisan migrate --force  # Requires explicit --force in production
```

---

## AI Coding Assistant Guidelines

When using Claude Code, Cursor, or similar tools:

1. **Always specify the agent role** — prefix prompts with the agent context from AGENTS.md
2. **Reference this codebase** — mention "multi-tenant Laravel CRM" and "HIPAA compliance" in every session
3. **Verify tenant scoping** — after generating any query code, confirm tenant_id is applied
4. **Check for PHI exposure** — after generating any API response, confirm no raw PHI leaks
5. **Run the full test suite** after any AI-generated code before committing

### Example Prompts

```
// Good:
"Using the BackendAgent context from AGENTS.md: Create a service method to
list evaluations for the clinic dashboard. Must include tenant scoping,
PHI audit logging, and return paginated results using JsonResource."

// Bad:
"Create a method to get evaluations"
```
