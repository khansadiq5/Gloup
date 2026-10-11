# Salon SaaS — Product & Architecture Planning

**Status:** Planning baseline — proposed, not yet implemented  
**Purpose:** Single source of truth for AI-assisted development.  
**Rule:** Do not start implementation until the relevant planning decisions and acceptance criteria for that phase are reviewed.

## 1. Product vision

Build a polished, mobile-responsive salon marketplace and salon-management SaaS. Customers can discover salons, view services and prices, and book appointments. Salon businesses can register, manage their salon, staff, appointments, walk-ins, customers, and branch-specific loyalty. A platform administrator manages tenants, plans, subscriptions, moderation, and system-level settings.

The product should be secure, maintainable, testable, and practical to build incrementally with AI assistance. Do not over-engineer the first release.

## 2. Confirmed product decisions

- Product name: GLOUP Salon SaaS.
- Backend: Laravel + PHP.
- Initial frontend: Laravel Blade + Tailwind CSS + Alpine.js.
- Testing framework: Pest PHP (unit, feature, isolation, and security tests) + Playwright (E2E).
- Workspace: `C:\Users\khans\Downloads\Gloup` as project root.
- Public pages may use GSAP selectively for meaningful animations; keep dashboards lightweight.
- Database: MySQL.
- Architecture: modular Laravel monolith; no microservices for the MVP.
- Tenant model: one tenant represents a salon business/organization; a tenant can own multiple physical branches.
- Free plan: one branch per tenant.
- Premium plan: multiple branches, subject to a configurable plan limit if required.
- Loyalty progress is branch-specific; visits at one branch do not count toward another branch's reward counter.
- Customer identity: Unified User Model across the marketplace with tenant-specific customer profiles (`customer_profiles`). A customer uses one account across all salons, while salon-specific records, preferences, notes, and branch-specific loyalty remain strictly isolated. Guest booking is fully supported with secure verification tokens and optional post-booking account linking.
- Booking concurrency: Database row-level locking (`SELECT ... FOR UPDATE`) and a temporary 10-minute reservation hold (`slot_reservations` table). Expiration, automatic cleanup, transaction-safe booking confirmation, and automated double-booking/concurrency tests.
- Payment scope: Initial booking engine defaults to "Pay at Salon". Online payment integration is deferred until Phase 8. Before implementation, confirm the appropriate payment gateway for an India-based business (e.g. Razorpay, Cashfree, Stripe India); Stripe sandbox is test tooling only, not a commitment to use Stripe in production.
- Public salon URLs launch as `brand.example/salon/{slug}`.
- Design routing/domain ownership so subdomains and verified custom domains can be added later without changing the tenant's permanent identity.
- Responsive website first. A native mobile app may be considered later; keep core business logic reusable and API-capable without building a full API-first frontend now.
- Owners may manage multiple branches. Branch-level manager/staff permissions will be supported by the authorization model.
- Free/Premium features are controlled by server-side plan entitlements, not by hiding buttons alone.
- Planning and implementation proceed phase by phase. Every phase must pass its defined tests before the next phase begins.

## 3. Product goals

1. Let a salon owner register and set up a business and its first branch.
2. Let customers discover salons and book a valid appointment without being forced to create an account.
3. Prevent cross-tenant and cross-branch data access.
4. Let staff manage online appointments and verified walk-ins.
5. Support reliable appointment statuses, cancellations, rescheduling, and payment status tracking.
6. Provide configurable branch-specific loyalty programs and prevent duplicate visit/reward processing.
7. Support Free and Premium subscriptions with predictable downgrade/expiry behavior.
8. Keep the customer-facing marketplace polished, responsive, and SEO-friendly.
9. Keep salon dashboards minimal, readable, and operationally efficient.
10. Establish automated tests and security checks before features are considered complete.

## 4. Out of scope for initial MVP

- Microservices, Kubernetes, event streaming infrastructure, or per-tenant databases.
- Native iOS/Android apps.
- AI features that are not necessary for core booking and management.
- Complex franchise accounting or full accounting/ERP.
- Automatic payment splitting between platform and every salon until the business/legal/payment-provider model is researched and selected.
- Multiple currencies and countries in the first release.
- Elaborate real-time collaboration unless validated as a requirement.

These may be revisited when real product needs justify them.

## 5. Roles and access model

- **Platform Super Admin:** platform-level tenant, plan, subscription, moderation, and operational administration.
- **Business Owner:** controls a tenant, subscription, branches, business settings, and member access.
- **Business Admin:** owner-authorized administrative tasks.
- **Branch Manager:** can access assigned branch(es) only, according to explicit permissions.
- **Staff:** can access only the features and branch data required for their role.
- **Customer with account:** can view/manage only their own permitted bookings and loyalty information.
- **Guest customer:** can create a booking and manage it only after secure verification, such as a short-lived signed link or OTP.

All access control must be enforced server-side. A client-supplied tenant ID or branch ID is not proof of permission.

## 6. Core customer journeys

### 6.1 Salon registration and onboarding
1. Owner registers and verifies a suitable contact method.
2. System creates a tenant and assigns the owner membership.
3. Owner creates the first branch and profile.
4. Owner configures opening hours, services, prices, staff, and booking rules.
5. Owner previews and publishes the public salon page.
6. Free plan permits only one branch; the UI explains Premium when the owner attempts to add another.

### 6.2 Customer discovery and booking
1. Customer searches by area/location and optionally service/category.
2. Customer opens a salon and selects a branch.
3. Customer selects services, optional preferred staff member, date, and available time.
4. Server rechecks availability and pricing before creating/reserving the booking.
5. Customer enters contact details and completes the configured verification step.
6. Customer chooses an allowed payment method: online or pay at salon, depending on salon/platform policy.
7. Customer receives confirmation and a secure way to view/manage the booking.
8. Account creation is optional and offered without blocking guest booking.

### 6.3 Salon operations
- Calendar/list views for appointments.
- Staff schedules, breaks, time off, and service eligibility.
- Walk-in creation with customer details kept to the minimum needed.
- Status changes recorded in an audit/event history.
- Cancellation, rescheduling, no-show, completion, and refund policies are explicit.

### 6.4 Loyalty
- Owner enables/disables loyalty for each branch and configures qualifying visit threshold and reward rules.
- Only defined qualifying completed appointments or verified walk-ins count.
- Visits are idempotent: one source event cannot award multiple visits.
- Customer progress and reward details clearly identify the branch.
- Redemption is single-use and recorded. Refund/cancellation reversals follow documented rules.

### 6.5 Subscription and downgrade
- Subscription is tenant/business-level.
- Plan entitlements are checked by backend policies/services.
- Free permits one branch; Premium permits multiple branches according to configured entitlement.
- Expiry/downgrade never silently deletes branch data, customer history, or existing appointments.
- If a tenant has more branches than the downgraded plan permits, block new branch creation and apply a reviewed grace-period/read-only/deactivation policy. Existing bookings and paid obligations must be handled safely.

## 7. Proposed architecture

### 7.1 Application shape
A modular Laravel monolith with clear domains, for example:

- Identity & Access
- Tenants & Memberships
- Branches & Domains
- Plans & Subscriptions
- Salon Profiles & Discovery
- Services & Pricing
- Staff & Schedules
- Customers
- Booking & Appointment Lifecycle
- Walk-ins
- Payments
- Loyalty & Rewards
- Notifications
- Reviews (only if included in MVP)
- Platform Administration
- Audit & Security Events

Keep controllers thin. Put business rules in application services/actions and domain-specific classes so future APIs can reuse them. Do not create modules merely for appearance; boundaries should reflect business responsibility.

### 7.2 Tenant and domain resolution
- Tenant has a permanent internal ID independent of its slug or hostname.
- A salon slug is a public route identifier, not the tenant's security boundary.
- Store custom domains/subdomains in a verified domain registry linked to the tenant/branch as decided.
- Normalize and validate hostnames; reject unverified or conflicting domains.
- Trust forwarded host/proxy headers only from configured trusted proxies.
- Resolve tenant context early, then verify user membership and branch permission for every protected operation.
- Clear/reset tenant context for each request and each queued job to avoid context leakage.
- Add tests for slug/domain collisions, unknown hosts, malicious Host headers, tenant switching, and cross-tenant access.

### 7.3 Database approach
Use one MySQL database initially. Tenant-owned records must have explicit ownership relationships and indexes. Use foreign keys where practical, unique constraints for business invariants, and transactions/locking for concurrency-sensitive operations.

Entities:
- `users`: Global authentication and identity accounts (Platform Admin, Salon Owner, Salon Staff, Customer).
- `tenants`: Salon organizations/businesses.
- `tenant_users`: Memberships, business/branch roles and granular permissions.
- `customer_profiles`: Tenant-scoped customer data (salon-specific notes, contact overrides, banned status, total visits at this salon).
- `branches`: Physical salon locations under a tenant.
- `tenant_domains`: Verified custom domains / subdomains.
- `plans`, `subscriptions`, `entitlement_definitions`: Free (1 branch) and Premium (multi-branch) entitlements.
- `salon_profiles`, `service_categories`, `services`, `branch_service_overrides`: Service catalog, duration, and branch pricing overrides.
- `staff_profiles`, `staff_branches`, `staff_services`, `business_hours`, `shifts`, `time_off`: Staff schedules and availability.
- `slot_reservations`: Temporary 10-minute holds (`staff_id`, `branch_id`, `start_time`, `end_time`, `session_token_hash`, `expires_at`).
- `appointments`, `appointment_services`, `appointment_events`: Confirmed booking records with frozen price/duration snapshots and audit history.
- `loyalty_programs`, `loyalty_visits` (unique compound index on `branch_id`, `source_type`, `source_id`), `rewards`, `reward_redemptions`.
- `payments`, `webhook_events`: Payment records decoupled from appointment state.
- `notifications`, `audit_logs`.
- `reviews` (optional, post-MVP).

Do not duplicate data just to make queries convenient without documenting the source of truth. Appointment line items must snapshot booked price and duration so later price changes do not rewrite history.

### 7.4 Customer identity
- Customer identity uses a unified `users` table for authentication across the marketplace, linked to tenant-scoped `customer_profiles` for salon-specific history, notes, and preferences.
- A customer account is optional for booking.
- A guest booking creates/references a guest identity with contact details and generates a high-entropy signed token (`session_token_hash`) for booking management.
- Do not automatically merge accounts/bookings based only on an unverified name or phone number.
- Use verified identity and an explicit linking flow for account conversion.
- Restrict exposure of customer data to the correct tenant/branch and permitted staff roles; Salon A cannot see Customer activity or notes at Salon B.

### 7.5 Appointment and availability invariants
- Appointment status and payment status are separate state machines.
- Availability is calculated on the server from branch opening hours, staff schedules, breaks/time off, service duration, buffers, confirmed appointments, and active temporary slot reservations.
- Concurrency strategy:
  1. A 10-minute temporary reservation hold is recorded in `slot_reservations` when a user enters checkout.
  2. Availability queries exclude overlapping slots held by active reservations (`expires_at > now()`).
  3. At final booking confirmation, a database transaction uses pessimistic row-level locking (`SELECT ... FOR UPDATE`) to guarantee no overlapping confirmed appointments or valid competing reservations exist.
  4. Once confirmed, the appointment is created and the temporary hold is consumed.
  5. Expired holds (`expires_at <= now()`) are ignored by queries and pruned by a background task.
- Revalidate availability and pricing at booking confirmation time.
- Define how multi-service bookings, staff selection, time zones (UTC storage, branch display), cancellation windows, no-shows, and rescheduling work.

### 7.6 Payments
- Phase 1-7 booking engine defaults to "Pay at Salon", decoupling initial booking and operational milestones from payment gateway dependencies.
- Online payment integration is scheduled for Phase 8. Prior to implementation, evaluate and select an appropriate payment gateway for an India-based business (e.g., Razorpay, Cashfree, or Stripe India). Stripe sandbox used in testing is non-binding test tooling only.
- Payment provider integration must use server-side verification and signed webhooks.
- Webhook processing must be idempotent and auditable (`webhook_events` table).
- Never store raw card details.
- Decide platform fees/commission, refund responsibility, settlement ownership, and legal/tax implications before enabling production payments.

### 7.7 Notifications and background work
- Start with Laravel's supported database queue if appropriate; add Redis only when measured needs justify it.
- Use queues for emails, reminders, report generation, and other non-blocking tasks.
- Jobs must be idempotent and carry explicit tenant/branch context.
- Prevent duplicate reminders and ensure retry behavior does not duplicate loyalty or payment effects.
- WhatsApp/SMS provider selection requires cost, consent, template, and regulatory review.

## 8. Security baseline

Security is a release gate, not a final optional phase.

- Authorization checks on every protected route/action.
- Tenant/branch isolation tests for reads, writes, exports, background jobs, and admin operations.
- CSRF protection for browser state-changing requests.
- Context-appropriate output escaping and validation to prevent XSS.
- Parameterized ORM/query usage; avoid unsafe raw SQL.
- Rate limiting for login, OTP, booking, password reset, and public APIs.
- Secure session cookies, session regeneration, logout invalidation, and safe password reset.
- Strong password hashing and verified account flows.
- Secure guest booking management tokens (high entropy, expiry, revocation, minimal scope; store hashes where practical).
- No secrets in source control, logs, prompts, screenshots, or test fixtures.
- Restrictive file upload validation, storage, size limits, and non-executable media handling if uploads are enabled.
- Safe redirects and strict hostname/domain verification.
- Signed/idempotent payment webhooks.
- Audit sensitive actions without logging OTPs, secrets, payment credentials, or unnecessary personal data.
- Dependency vulnerability checks and supported framework/runtime versions.
- Production HTTPS, secure headers, backups, least-privilege database/service accounts, and a documented restore procedure.
- Follow applicable privacy/data-retention and payment-provider requirements before launch.

## 9. Testing strategy

Every feature needs appropriate automated tests and a recorded result. A test passing in one layer does not replace other layers.

- **Unit tests:** pure business rules, price/duration calculations, entitlement checks, loyalty qualification, state transitions.
- **Feature/integration tests:** routes, validation, database persistence, policies, authentication, transactions, notifications, subscription enforcement.
- **Tenant-isolation tests:** tenant A cannot access tenant B's records; branch manager cannot access an unassigned branch.
- **Security tests:** XSS payload handling, CSRF/session behavior, IDOR/BOLA, mass assignment, unsafe redirects, rate limits, file upload validation, hostname spoofing, webhook signature/idempotency.
- **Concurrency tests:** two requests attempting the same staff/time slot; duplicate webhook; duplicate loyalty event; simultaneous branch creation at plan limit.
- **Browser end-to-end tests:** Playwright across registration, onboarding, discovery, guest booking, account booking, salon dashboard, walk-ins, loyalty, plan limits, cancellation, and responsive layouts.
- **Accessibility/usability checks:** keyboard navigation, labels, contrast, form errors, mobile layouts.
- **Regression tests:** every fixed bug gets a reproducing test where feasible.
- **Dependency/static checks:** formatter/linter, static analysis as adopted, dependency audit, secret scanning.

Do not claim security is proven by a few payload tests. Combine automated checks, code review, configuration review, and an appropriate pre-launch security assessment.

## 10. AI-assisted development rules

1. Treat this document and `implementation.md` as project instructions.
2. Work on one small phase/task at a time; do not implement the whole application in one prompt.
3. Before changing files, inspect the existing repository, runtime versions, current tests, and relevant code.
4. State the task scope, expected files, assumptions, and acceptance criteria before editing.
5. Prefer small, reviewable changes. Do not rewrite unrelated code or silently change architecture.
6. Never invent environment values, credentials, test results, or provider capabilities.
7. Never expose or commit secrets. Use environment variables and safe example placeholders.
8. Add/update tests with every feature and bug fix.
9. Run the exact tests and checks available; report command, result, skipped checks, and reason honestly.
10. If a test fails, diagnose and fix it before advancing; do not disable a test merely to get green output.
11. Review migrations for data loss, indexes, constraints, rollback behavior, and production safety.
12. For security-sensitive changes, explicitly review authorization, tenant context, input validation, output escaping, rate limits, logging, and abuse cases.
13. Ask before introducing major dependencies, paid services, architectural changes, destructive data operations, or external side effects.
14. Do not proceed to the next phase until the current phase's acceptance criteria and test gate pass or a blocker is documented and explicitly accepted.
15. Maintain a decision log for unresolved choices and record any change to these planning assumptions.

## 11. Initial phase roadmap

1. **Project foundation:** repository, supported versions, environment, formatting, CI, test framework, secure configuration.
2. **Identity and tenant foundation:** users, owner registration, tenant creation, memberships, authorization, tenant-isolation tests.
3. **Branch and subscription foundation:** branches, plan entitlements, Free one-branch rule, Premium test-mode configuration, downgrade policy.
4. **Salon setup:** profiles, slug URLs, services/pricing, staff, branch hours and schedules.
5. **Availability and booking engine:** availability calculation, slot concurrency, appointment lifecycle and audit history.
6. **Customer flows:** marketplace discovery, guest booking, optional accounts, secure booking management.
7. **Salon operations:** calendar, walk-ins, customer management, staff access.
8. **Loyalty:** branch-specific programs, visit ledger, rewards, redemption and reversal rules.
9. **Payments and notifications:** provider selected after review, sandbox payments, signed webhooks, email/reminders.
10. **Polish and platform operations:** UI/accessibility, platform admin, reports, abuse controls, backups, observability.
11. **Release readiness:** full regression, Playwright, security review, deployment rehearsal, backup restore test, rollback plan.
12. **Future capabilities:** subdomains/custom domains, richer API, mobile app, Redis/scaling only when justified.

## 12. Decisions still open before relevant implementation

- Default currency (INR recommended for initial release) and default timezone (e.g. `Asia/Kolkata`).
- Exact Free/Premium feature matrix, pricing, billing interval, grace period and cancellation behavior (Phase 2).
- Branch limit for Premium (whether Premium is unlimited or capped at N branches) (Phase 2).
- Whether services/prices are shared defaults with branch overrides (Phase 3).
- Staff membership and permission granularity (Phase 1/3).
- Booking slot interval, service buffer duration (clean-up/prep time) (Phase 4).
- Guest verification SMS/OTP provider for India (e.g., MSG91, Twilio, or Fast2SMS) for Phase 5.
- Loyalty threshold/reward types, expiry, refunds, no-show handling and fraud review (Phase 7).
- Payment gateway for India-based operations: Razorpay vs Cashfree vs Stripe India evaluation (Phase 8).
- File storage driver: local public storage during development, S3/R2 compatible object storage for production (Phase 3).
- Production hosting, backups, monitoring, expected budget and data-retention obligations (Phase 10).

Do not block early foundation work on every future integration, but do not implement a dependent feature until its required policy is decided.

## 13. Definition of done

A task is done only when:
- Acceptance criteria are met.
- Appropriate unit and feature tests exist and pass.
- Tenant/branch authorization is tested when relevant.
- XSS/input/output handling is reviewed when relevant.
- Playwright coverage is added for important user journeys when relevant.
- Formatting/static/dependency checks appropriate to the repository pass.
- Migrations and rollback/data safety are reviewed.
- No secrets or sensitive customer data are leaked.
- The AI provides an honest summary of changed files, checks run, results, remaining risks, and the next step.
