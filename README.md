# GLOUP — Multi-Tenant Salon SaaS Marketplace

GLOUP is a multi-tenant salon SaaS marketplace and salon management system built with Laravel 11/12, MySQL, Blade, Tailwind CSS, Alpine.js, and Pest PHP.

## Architecture & Project Playbooks

- **[planning.md](file:///C:/Users/khans/Downloads/Gloup/planning.md)**: Product specifications, domain models, confirmed architecture decisions, and non-negotiable security rules.
- **[implementation.md](file:///C:/Users/khans/Downloads/Gloup/implementation.md)**: AI implementation playbook, phased test gates, and acceptance checklists.

## Core Design Principles

1. **Multi-Tenancy Model:** 1 Tenant = 1 Salon Business. A tenant owns one or more physical branches.
2. **Subscription Entitlements:** Free Plan (strictly 1 branch), Premium Plan (multi-branch support). Enforced server-side.
3. **Customer Accounts:** Unified marketplace user accounts with tenant-isolated `customer_profiles`. Guest checkout supported without mandatory registration.
4. **Concurrency & Slot Holds:** Database row-level locking (`SELECT ... FOR UPDATE`) paired with a 10-minute temporary reservation hold (`slot_reservations`) to prevent double-booking.
5. **Branch-Specific Loyalty:** Visits and rewards are strictly branch-scoped. 
6. **Payment Scope:** Initial booking engine defaults to "Pay at Salon". Online payment gateway integration (India-compliant) is implemented in Phase 8.

## Getting Started

### Prerequisites

- PHP 8.2+ with `pdo_mysql`, `pdo_sqlite`, `curl`, `mbstring`, `openssl`, `bcmath`
- Composer 2.x
- Node.js 20+ and npm
- MySQL 8.0+ (for local development app server)

### Installation

1. Copy `.env.example` to `.env` and configure your environment:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

2. Install backend dependencies:
   ```bash
   composer install
   ```

3. Install frontend dependencies and build assets:
   ```bash
   npm install
   npm run build
   ```

4. Run database migrations:
   ```bash
   php artisan migrate
   ```

5. Start the local development server:
   ```bash
   php artisan serve
   ```

## Testing & Quality Gates

Run the automated test suite with Pest:
```bash
php artisan test
```

Run code formatting with Laravel Pint:
```bash
./vendor/bin/pint
```

Run security audits:
```bash
composer audit
npm audit
```
