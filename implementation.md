# Salon SaaS — AI Implementation Playbook

**Purpose:** Instructions for implementing the product defined in `planning.md` safely and incrementally.  
**Working mode:** One small task at a time; test, review, and only then continue.  
**Important:** This file is a workflow and checklist, not a claim that code or tests already exist.

## 1. Mandatory workflow for every task

For every implementation task, the AI must follow this sequence:

1. **Read context:** review `planning.md`, this file, relevant source files, current git diff, project instructions, runtime versions, and existing tests.
2. **Confirm scope:** state the goal, non-goals, assumptions, files likely to change, risks, and acceptance criteria. Ask a focused question if a blocking policy is undecided.
3. **Inspect before editing:** identify the current implementation and avoid duplicate models, migrations, routes, helpers, or dependencies.
4. **Plan the smallest change:** split the task if it contains multiple independent features.
5. **Implement:** make minimal, maintainable changes; preserve project conventions and keep business logic testable.
6. **Add tests immediately:** unit/feature/security/browser tests appropriate to the change. Do not postpone all tests until the end.
7. **Run checks:** execute targeted tests first, then relevant broader tests, formatter/linter/static checks and dependency/security checks available in the repository.
8. **Review:** inspect the diff for authorization gaps, tenant/branch leakage, mass assignment, unsafe output, race conditions, logging of sensitive data, secrets, and unnecessary changes.
9. **Report truthfully:** list files changed, commands actually run, pass/fail results, skipped checks and why, unresolved risks, and how to manually verify if needed.
10. **Gate:** do not begin the next task/phase until acceptance criteria pass. If blocked, stop and explain the blocker rather than pretending success.

Never claim a test, browser run, security scan, deployment, or external integration succeeded unless it was actually executed and its result was observed.

## 2. Project bootstrap gate

Before application features:

- Confirm the current supported Laravel 11 / PHP 8.2+ versions from official requirements.
- Workspace root: `C:\Users\khans\Downloads\Gloup`. Inspect workspace before any command to ensure existing user files (`planning.md`, `implementation.md`) are preserved.
- Initialize Git repository cleanly and establish production-safe `.gitignore`.
- Configure `.env.example` with placeholders only; never commit real `.env` or secrets.
- Establish Laravel Pint (code formatting) and Pest PHP as the official testing framework.
- Configure SQLite in-memory for lightning-fast Pest CI/unit tests, and local MySQL for development runtime.
- Add CI configuration for linting, security audits, and Pest tests.
- Add a health check and safe error handling; disable debug mode in production configuration.
- Record how to run the app and tests in the project README.

**Gate:** a clean setup can install dependencies, migrate a test database, run a baseline Pest test, and pass baseline checks without committed secrets.

## 3. Phase-by-phase implementation plan

### Phase 0 — Repository reconnaissance and architecture lock

Tasks:
- Inspect existing files/repository before generating boilerplate to guarantee zero overwrite of user documents.
- App Name: GLOUP Salon SaaS.
- Initialize Git in `C:\Users\khans\Downloads\Gloup` with `.gitignore`.
- Scaffold Laravel 11, Tailwind CSS, Alpine.js, Pest PHP, and Laravel Pint.
- Record confirmed architectural models: Unified User with tenant `customer_profiles`, 10-minute `slot_reservations` hold, Pay at Salon MVP.
- Document baseline test and formatting commands in README.

Tests/checks:
- Baseline Pest test executed and passing (`php artisan test`).
- `composer audit` and `npm audit` executed with zero high/critical vulnerabilities.

**Gate:** no unresolved decision blocks Phase 1; repository baseline is repeatable and tests pass.

### Phase 1 — Identity, tenant and membership foundation

Tasks:
- Unified `User` model (Platform Admin, Salon Owner, Salon Staff, Customer).
- User registration/login/logout and email verification.
- Tenant/business creation and owner membership (`Tenant`, `TenantUser`).
- Tenant-scoped `CustomerProfile` model ensuring customer salon records remain isolated across tenants.
- Centralized tenant context service and authorization policies.
- Tenant-aware route/model access patterns; avoid relying on UI visibility alone.

Tests:
- Owner can access own tenant.
- User from Tenant A cannot read, update, delete, export or infer Tenant B's private records.
- Unauthenticated and unauthorized requests are rejected.
- IDOR/BOLA attempts using changed IDs fail.
- Session handling, CSRF and validation behave correctly.
- XSS payloads in user-controlled fields are rendered as text, not executable markup.

**Gate:** cross-tenant feature tests pass before adding business data modules.

### Phase 2 — Branches and subscription entitlements

Tasks:
- Branch entity and tenant relationship.
- Free and Premium plan definitions.
- Business-level subscription state and server-side entitlement service.
- Free plan maximum one branch; Premium multiple branches subject to configured limit.
- Safe upgrade/downgrade/expiry behavior, including excess branches.
- Audit important plan/branch changes.

Tests:
- Free owner can create first branch but cannot create a second through UI, route, API, or concurrent requests.
- Premium owner can create branches within entitlement.
- Tenant A cannot attach a branch to Tenant B.
- Expired/downgraded subscription does not delete historical data or break existing appointments.
- Plan checks cannot be bypassed by changing request payloads.

**Gate:** entitlement and downgrade tests pass; no branch-limit check exists only in frontend code.

### Phase 3 — Salon profiles, services, staff and schedules

Tasks:
- Salon/business profile and public slug.
- Branch-specific profile/location/opening hours.
- Service categories, service duration, price and optional buffers.
- Business-level service defaults only if needed, with branch overrides and clear precedence.
- Staff profiles, branch assignments, service eligibility, shifts, breaks and time off.
- Image upload only after validation/storage policy is defined.

Tests:
- Slugs are unique and safely normalized.
- Branch data is tenant-scoped.
- Prices/durations are validated.
- Staff cannot be assigned outside authorized tenant/branch context.
- Upload MIME/type/size and non-executable storage behavior are checked if uploads exist.
- XSS payloads in salon names, bios, service descriptions and staff names are safely escaped.

**Gate:** owner/manager permissions and data isolation pass for all CRUD paths.

### Phase 4 — Appointment availability and lifecycle

Before coding, document state machines and policy for:
- Appointment statuses (pending, confirmed, completed, cancelled, no-show).
- Payment statuses independently from appointment statuses.
- Slot intervals, multi-service duration, buffers (clean-up/prep time), staff/service compatibility, breaks, time off and branch hours.
- Temporary reservation holds (10-minute hold window, auto-expiration, cleanup).
- Cancellation, rescheduling, late cancellation, no-show and refund rules.
- Branch timezone and UTC storage/display rules.

Implementation tasks:
- Server-side availability service.
- Migration and model for `slot_reservations` (`staff_id`, `branch_id`, `start_time`, `end_time`, `session_token_hash`, `expires_at`).
- Concurrency prevention: Pessimistic row locking (`SELECT ... FOR UPDATE`) inside booking transaction.
- Background command to prune expired reservations.
- Snapshot booked service price/duration.
- Appointment event/audit history.
- Safe reschedule/cancel workflows.

Tests:
- Unit tests for duration/availability calculations and state transitions.
- Feature tests for valid/invalid booking requests.
- Concurrency test: Two parallel requests attempting the same staff/slot interval; assert exactly one succeeds and one receives 409 Conflict.
- Reservation expiry test: Slot becomes available again after 10-minute hold expires.
- Cancelled/no-show bookings follow defined availability and loyalty rules.
- Prices cannot be changed by tampering with client-supplied totals.
- Cross-tenant/branch booking access is rejected.

**Gate:** availability, concurrency (Pest automated race condition tests) and state-transition tests pass before building customer booking UI.

### Phase 5 — Marketplace and customer booking

Tasks:
- Responsive public marketplace and salon slug pages.
- Search/filter by area/service.
- Branch/service/staff/date/time selection with 10-minute temporary reservation hold.
- Pay at Salon default checkout flow.
- Guest booking with minimum required contact details and cryptographically secure management token.
- Unified customer account booking and post-booking account conversion with verified email/phone linking.
- Secure booking confirmation and management view.
- Clear loading, empty, error, success and unavailable-slot states.

Tests:
- Playwright guest booking end-to-end (Pay at Salon).
- Playwright account booking end-to-end.
- Customer cannot access another customer's booking by changing an ID (IDOR prevention).
- Guest management links/tokens expire and cannot be reused beyond policy.
- Rate limits apply to booking/verification endpoints.
- XSS and validation tests cover all public user inputs.
- Mobile viewport and keyboard/basic accessibility checks.

**Gate:** end-to-end booking passes on a clean test database and the responsive flow is reviewed.

### Phase 6 — Salon dashboard, calendar and walk-ins

Tasks:
- Branch switcher with visible current branch context.
- Calendar/list views and filters.
- Walk-in creation and completion.
- Customer history restricted by business/branch policy.
- Staff and manager access permissions.
- Audit sensitive status changes.

Tests:
- Playwright owner and branch-manager flows.
- Manager assigned to Branch A cannot view or edit Branch B.
- Walk-in can be created only for authorized branch.
- Walk-in and online booking cannot double-book a staff member/time.
- Customer exports obey permissions and do not leak other tenants' data.

**Gate:** branch context is clear in UI and every backend operation verifies membership.

### Phase 7 — Branch-specific loyalty and rewards

Tasks:
- Enable/disable program per branch.
- Define threshold, reward type, eligibility, expiry, redemption and reversal policies.
- Create an auditable qualifying-visit ledger linked to its source appointment or verified walk-in.
- Make processing idempotent via compound unique constraint `(branch_id, source_type, source_id)`.
- Provide branch-labelled customer progress and staff redemption workflow.

Tests:
- Visit at Branch A never increments Branch B's counter.
- Duplicate event/job cannot award a second visit.
- Cancelled, no-show, refunded and manually entered visits follow written policy.
- A reward can be redeemed only once and only at an eligible branch.
- Unauthorized staff cannot adjust points/rewards.
- Customer cannot view another customer's loyalty balance.
- Concurrent reward redemption does not allow double spend.

**Gate:** ledger, branch isolation and redemption concurrency tests pass before loyalty is marketed.

### Phase 8 — Payments and notifications

Before implementation, evaluate payment gateways for an India-based business (Razorpay, Cashfree, Stripe India) and finalize settlement, refund, and commission policies.

Tasks:
- Integrate selected India-compliant provider in sandbox mode.
- Verify payment status server-side.
- Signed webhook verification, idempotency (`webhook_events` table) and event logging.
- Maintain payment state decoupled from appointment state.
- Queue notifications and reminders with retry/idempotency rules.
- Avoid sensitive data in logs/messages.

Tests:
- Invalid webhook signatures are rejected.
- Duplicate webhook events do not duplicate payment, booking or loyalty effects.
- Client cannot mark a payment successful by editing a request.
- Failure, timeout, refund and retry scenarios are covered.
- Duplicate reminders are prevented where required.
- Email/SMS provider failures do not corrupt booking state.

**Gate:** sandbox reconciliation and webhook tests pass. Production keys are never used in automated tests.

### Phase 9 — Platform admin, reporting and operational controls

Tasks:
- Tenant/plan/subscription overview and controlled administrative actions.
- Reports scoped to authorized tenant/branch.
- Audit logs for sensitive operations.
- Abuse monitoring, rate limits and safe operational alerts.
- Backups, restore procedure, health checks and error monitoring.

Tests:
- Only platform admins can access platform-wide operations.
- Tenant owners cannot access global reports or other tenant details.
- Exports are permission-scoped.
- Backup restore is rehearsed in a non-production environment.

**Gate:** least privilege and recovery steps are demonstrated, not merely documented.

### Phase 10 — Release readiness and deployment

Checklist:
- Full unit/feature/integration suite passes.
- Playwright critical journeys pass.
- XSS, CSRF, IDOR/BOLA, authorization, rate-limit, hostname and webhook checks reviewed.
- Dependency/security audit and secret scan run.
- Production configuration has debug disabled and HTTPS enforced.
- Database migration and rollback/forward-recovery plan reviewed.
- Backup and restore tested.
- Logs/monitoring do not expose secrets or unnecessary personal data.
- Performance baseline measured; no speculative infrastructure added without evidence.
- Manual smoke test and rollback plan completed.

**Gate:** release is not approved if a critical security issue or unresolved data-loss risk remains.

## 4. Required testing matrix

| Test layer | What it proves | When to run |
|---|---|---|
| Unit tests | Isolated business rules and calculations | Every relevant change |
| Feature/integration tests | Laravel routes, DB, policies and workflows | Every backend feature |
| Tenant/branch isolation | No cross-tenant or unauthorized branch access | Every tenant-scoped feature |
| XSS/input tests | Untrusted input is validated and safely rendered | Every user-input surface |
| Concurrency tests | No double booking, duplicate rewards or plan-limit race | Booking, loyalty, subscription changes |
| Playwright E2E | Real browser journeys work | Each major user journey and regression |
| Security/config tests | CSRF, sessions, rate limits, IDOR, host validation, webhooks | Relevant changes and release gate |
| Accessibility/responsive | Usable keyboard, forms, contrast and mobile layouts | UI tasks and release gate |
| Dependency/secret scan | Known vulnerable dependencies and accidental secrets | CI and release gate |
| Restore/recovery rehearsal | Backups can actually recover the service | Before launch and periodically |

Security tests should be safe, scoped to the test environment, and authorized. Automated checks are evidence, not a guarantee that the application is perfectly secure.

## 5. Playwright rules

- Use a dedicated test database and deterministic seed/factory data.
- Do not run destructive browser tests against production.
- Keep test accounts and secrets out of source control; use CI secret storage where needed.
- Test realistic user journeys and negative permission paths.
- Capture screenshots/traces only when useful; redact personal data and tokens.
- Prefer stable accessible selectors/labels over brittle CSS selectors.
- Cover desktop and representative mobile viewports.
- Ensure test setup cleans up created data and is repeatable.

Minimum E2E journeys:
1. Owner registers and creates business + first branch.
2. Free owner cannot create second branch; Premium owner can.
3. Owner creates service/staff/hours and publishes salon page.
4. Guest completes booking and receives a secure confirmation.
5. Customer account books and views own booking.
6. Customer cannot access another customer's booking.
7. Branch manager can access assigned branch but not another branch.
8. Staff creates a walk-in without causing double booking.
9. Completed visit increments only the correct branch's loyalty progress.
10. Reward redemption cannot be repeated.
11. Cancellation/rescheduling follows policy.
12. Payment sandbox success/failure/webhook retry behaves correctly when payment is implemented.

## 6. XSS and security review checklist

For every user-controlled field (salon name, description, service name, staff profile, review, customer details, comments, uploaded metadata):

- Validate type, length, allowed format and business rules server-side.
- Use framework escaping in HTML templates; avoid rendering raw HTML unless there is a reviewed sanitizer and explicit product need.
- Do not concatenate untrusted values into JavaScript, CSS, URLs, SQL or shell commands.
- Validate URL schemes and safe redirect destinations.
- Test common reflected/stored XSS payloads in a non-production environment.
- Confirm payloads are displayed as text or safely sanitized and do not execute.
- Add a regression test for each discovered issue.

Also review:
- IDOR/BOLA and cross-tenant access.
- Mass assignment and over-posting.
- CSRF, session fixation and session invalidation.
- Authentication and OTP brute-force protection.
- Host-header/domain verification and trusted proxies.
- File upload validation and access control.
- Payment webhook signature and replay/idempotency.
- Sensitive data in logs, URLs, analytics and notifications.
- Dependency vulnerabilities, default credentials and production debug settings.

## 7. AI prompt template for each task

Use a prompt like this at the start of each implementation task:

> Read `planning.md` and `implementation.md` first. We are currently in Phase [N], Task [name]. Do not implement later phases. Inspect the existing repository and tests before changing anything. First report the current implementation, proposed smallest change, affected files, assumptions, risks, and acceptance criteria. If a required product/security decision is unresolved, ask before coding. Implement only after the scope is clear. Add unit/feature/security/Playwright tests appropriate to this task. Run the relevant commands and report their actual results; never claim an unrun check passed. Review tenant and branch isolation, authorization, validation, XSS, concurrency, secrets and data migration safety. Do not proceed to the next task until this task's gate passes.

## 8. End-of-task report format

Every AI implementation response should end with:

- **Phase/task:**
- **Files changed:**
- **Behavior implemented:**
- **Tests added/updated:**
- **Commands actually run:**
- **Results:** passed / failed / not run, with reasons
- **Security and tenant-isolation review:**
- **Known limitations or unresolved decisions:**
- **Next task:** only after the current gate passes

## 9. Rules for handling failures

- Reproduce and understand a failure before editing around it.
- Do not remove/skip a failing test without a documented reason and explicit approval.
- Do not weaken authorization, validation, or security controls to make a test pass.
- If a dependency/provider is unavailable, mock only the external boundary and clearly label the test; keep contract/integration tests for a safe environment where feasible.
- If the runtime cannot run a required test, report it as not run—not passed—and provide the exact command for the user to run.
- If a migration risks production data, stop and propose a backup/forward-recovery plan before proceeding.

## 10. Project status tracker

Keep this tracker updated as work proceeds. Do not mark an item complete until its gate has passed.

- [x] Phase 0 — Repository reconnaissance and architecture lock
- [x] Phase 1 — Identity, tenant and membership foundation
- [x] Phase 2 — Branches and subscription entitlements
- [ ] Phase 3 — Salon profiles, services, staff and schedules
- [ ] Phase 4 — Appointment availability and lifecycle
- [ ] Phase 5 — Marketplace and customer booking
- [ ] Phase 6 — Salon dashboard, calendar and walk-ins
- [ ] Phase 7 — Branch-specific loyalty and rewards
- [ ] Phase 8 — Payments and notifications
- [ ] Phase 9 — Platform admin, reporting and operational controls
- [ ] Phase 10 — Release readiness and deployment
