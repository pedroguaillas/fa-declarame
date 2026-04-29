# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

### Development
```bash
composer dev        # Start all services concurrently: PHP server, queue listener, Pail logs, Vite dev
composer setup      # First-time setup: install deps, generate app key, migrate, build frontend
```

### Build & Frontend
```bash
npm run dev         # Vite dev server with HMR only
npm run build       # Production frontend build
```

### Testing
```bash
composer test                            # Run full PHPUnit test suite
php artisan test --filter=TestClassName  # Run a single test class
php artisan test tests/Feature/SomeTest.php  # Run a specific test file
```

### Database
```bash
php artisan migrate                      # Migrate central database
php artisan tenants:migrate              # Migrate all tenant databases
php artisan tenants:migrate --tenants=ID # Migrate a specific tenant
```

## Architecture Overview

This is a **multi-tenant SaaS** for tax/financial document management in Ecuador, built with Laravel 13 + Inertia.js + Vue 3.

### Multi-tenancy Model

Uses **Stancl Tenancy 3** with database-per-tenant isolation:

- **Central database**: stores `Users`, `Tenants`, `Plans`, `Subscriptions`, `Roles`, `Permissions`
- **Tenant databases** (prefixed `tenant_<uuid>`): isolated stores for `Companies`, `Shops`, `Contacts`, `Employees`, `Orders`, `Retentions`

Tenants are identified by **domain/subdomain**. The middleware chain for tenant requests:
1. `InitializeTenancyByDomain` — resolves tenant from domain
2. `PreventAccessFromCentralDomains` — blocks tenant routes on central domain
3. `CheckSubscription` — requires active plan subscription
4. `auth.tenant` — tenant-scoped authentication
5. `RequireCompanyScope` — enforces company context for scoped operations

### Route Structure

- `routes/web.php` — central/admin routes (login, super_admin-protected management)
- `routes/tenant.php` — tenant-scoped routes (served from tenant domains)

### Code Organization

```
app/
  Http/Controllers/           # Central controllers (Tenants, Users, Plans, etc.)
  Http/Controllers/Tenant/    # Tenant controllers (Companies, Shops, Employees, etc.)
  Models/                     # Central Eloquent models
  Models/Tenant/              # Tenant-scoped models (extend BaseModel with tenant DB connection)
  Services/                   # Business logic layer
    SriResolveNameService     # SRI (Ecuador tax authority) name lookup
    SriSoapService            # SOAP client for SRI integration
    SriXmlParserService       # XML parser for SRI documents
    OrderRetentionImportService  # Complex retention processing
    ShopImportService / OrderImportService  # CSV import pipelines
    TenantSetupService        # Tenant provisioning logic
    SSOTokenService           # SSO token generation/validation
resources/js/
  Pages/                      # Inertia page components (Vue)
  Pages/Tenant/               # Tenant-specific pages
  components/                 # Reusable shadcn-vue/Radix components
  composables/                # Vue 3 composables
```

### Frontend Stack

- **Vue 3** + **Inertia.js** — server-driven SPA (no separate API; controllers return Inertia responses)
- **Tailwind CSS 4** — utility-first styling
- **shadcn-vue + Radix Vue + Reka UI** — component primitives
- **TanStack Vue Table** — data tables
- **Ziggy.js** — type-safe Laravel route helpers in JS

### Domain-Specific Context

- Timezone: `America/Guayaquil` (Ecuador)
- **RUC**: Ecuador company tax ID — validated against SRI via SOAP
- **SRI**: Ecuador's tax authority — integrated via SOAP for document/name resolution
- **Retentions**: Tax retention documents tracked per order/shop

### Key Config Files

| File | Purpose |
|---|---|
| `config/tenancy.php` | Stancl tenancy setup, DB prefix, middleware pipeline |
| `app/Providers/TenancyServiceProvider.php` | Tenancy bootstrappers and job pipeline |
| `phpunit.xml` | Tests use SQLite in-memory; queue driver set to `sync` |
