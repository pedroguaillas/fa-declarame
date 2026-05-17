# Declarame — AGENTS.md

Multi-tenant SaaS for Ecuadorian tax document management (purchases, sales, retentions). Built with Laravel 13 + Inertia v3 + Vue 3 + Stancl Tenancy 3.

## Stack versions (non-obvious)

| Package | Version | Quirk |
|---|---|---|
| PHP | 8.5 (runtime), 8.3 (prod) | Sail uses 8.5 image, prod runs `php8.3` |
| Laravel | 13 | No API routes; all Inertia |
| Stancl Tenancy | 3 | Database-per-tenant isolation |
| Tailwind CSS | 4 | NO `tailwind.config.js` or `postcss.config.js` — config in `resources/css/app.css` via `@theme` |
| shadcn-vue | 2 | New York style, Inter font, Lucide icons |
| Turbo | 8 | Vite 8 + `@tailwindcss/vite` plugin |
| Vue | 3.5 | All components use `<script setup lang="ts">` |

## Development commands

```bash
# First time setup
composer setup

# Start dev (4 processes concurrently: PHP server, queue, logs, Vite)
composer dev

# Run all tests
composer test

# Run a specific test class
php artisan test --compact --filter=TestClassName

# Run a single test file
php artisan test --compact tests/Feature/SomeTest.php

# Format PHP before finalizing
vendor/bin/pint --dirty --format agent

# Migrate central DB
php artisan migrate

# Migrate all tenant DBs
php artisan tenants:migrate

# Migrate one tenant
php artisan tenants:migrate --tenants=<uuid>

# Add a shadcn-vue component
npx shadcn-vue@latest add button

# Vite cache issues
rm -rf node_modules/.vite && npm run dev
```

## Architecture

### Multi-tenancy (Stancl 3)

- **Central DB**: users, tenants, plans, subscriptions, roles, permissions
- **Tenant DBs** (prefixed `tenant_<uuid>`): companies, shops, contacts, orders, retentions
- Tenant resolved by subdomain/domain via `InitializeTenancyByDomain` middleware

### Route structure

| File | Scope | Access |
|---|---|---|
| `routes/web.php` | Central/admin routes | Guest + auth + `central.only` |
| `routes/tenant.php` | Tenant-scoped routes | Tenant domains + `auth.tenant` |

### Middleware pipeline (central)
`auth` → `check.active` → `central.only` → `role:super_admin` (for admin routes)

### Middleware pipeline (tenant)
`auth.tenant` → `check.tenant.subscription` → `RequireCompanyScope`

### Custom middleware aliases (from `bootstrap/app.php`)
- `auth.tenant` — tenant-scoped auth guard
- `check.tenant.subscription` — tenant subscription validity
- `tenant.role:admin` — tenant role check
- `check.active` — user must be active
- `central.only` — only central users allowed
- `role:super_admin` — role-based gate

### Key directories

```
app/
  Http/Controllers/Central/     # Central (Tenants, Users, Plans, etc.)
  Http/Controllers/Tenant/      # Tenant (Companies, Shops, Orders, etc.)
  Models/Central/                # Central Eloquent models (User, Role, Plan, etc.)
  Models/Tenant/                 # Tenant models (extend BaseModel)
  Services/Central/              # Biz logic per central model (UserService, RoleService, etc.)
  Services/                      # Other biz logic: SRI SOAP/XML, CSV import, etc.
  Jobs/                          # Queue jobs
  Exports/                       # Maatwebsite Excel exports
  Imports/                       # Maatwebsite Excel imports
resources/js/
  Pages/Central/                 # Central-specific Inertia page components (Vue)
  Pages/Tenant/                  # Tenant-specific pages
  components/ui/                 # shadcn-vue components
  layouts/                       # AppLayout.vue (sidebar layout)
  composables/                   # useNavigation, usePermissions, useTheme
  utils/                         # permissions.ts (can()), utils.ts (cn)
  types/                         # Global TS interfaces, ziggy types
```

### Dashboard
`DashboardController` is invokable — uses `match()` on user role to render the correct Inertia page:
- `super_admin` → `Pages/Dashboard/SuperAdmin.vue`
- `admin` → `Pages/Dashboard/Admin.vue`
- `employee` → `Pages/Dashboard/Employee.vue`

## Patterns: FormRequest + Service + Controller

### FormRequest

- **Base class** (`app/Http/Requests/Central/{Model}/Base{Model}Request.php`) extends `FormRequest`, defines shared rules.
- **Child classes** (`Store{Model}Request`, `Update{Model}Request`) extend Base, combine with `array_merge(parent::rules(), [...])`.
- **Cross-field validation** uses `withValidator()` + `$validator->after()`.
- **No `authorize()`** — permissions handled by middleware (`role:super_admin`).

```php
class StoreUserRequest extends BaseUserRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'email' => 'required|email|max:255|unique:users,email',
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            // cross-field validation (e.g. tenant_id only for admin role)
        });
    }
}
```

### Service

- **One per model** in `app/Services/Central/{Model}Service.php`.
- Works **exclusively with its own model** (no cross-model queries).
- Methods: `paginate()`, `all()`, `count*()`, `findOrFail()`, `create()`, `update()`.
- **No validation** — that's the FormRequest's job.
- `create()` / `update()` receive `array $data` (already validated) and return the model.

### Controller

- **Constructor DI** with typed services (`private readonly` promoted properties).
- **FormRequest type-hinted** in `store()`/`update()` — Laravel injects + validates automatically.
- **Orchestration**: `$request->validated()` → service methods → `Inertia::render()` or redirect with flash.
- **Simple guards** (e.g. `if ($user->isSuperAdmin())`) stay inline — too simple for a service method.
- **Extra actions** (`create`, `edit`, `toggleActive`) follow the same pattern.

```php
class UserController extends Controller
{
    public function __construct(
        private readonly UserService $userSvc,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Central/Users/Index', [
            'users' => $this->userSvc->paginate(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->userSvc->create($request->validated());
        return back()->with('success', 'Usuario creado correctamente.');
    }
}
```

### CRUD flow

```
Route → Middleware (auth, central.only, role:super_admin) → Controller
  index():   Service::paginate() → Inertia::render()
  store(StoreRequest):  $request->validated() → Service::create() → redirect
  update(UpdateRequest, $id):  $request->validated() → Service::update() → redirect
  destroy($id):  guard → $model->delete() → redirect
```

## Frontend permissions

### Backend shares permissions

`HandleInertiaRequests` adds `permissions` to `auth.user`:
- Super_admin → `[{ permission: '*', model: '*' }]`
- Other roles → array of `{ permission: slug, model: slug }` from `role.modelPermissions`

### Checking permissions

```ts
import { can } from "@/utils/permissions";
// or in a Vue component:
import { usePermissions } from "@/composables/usePermissions";
const { can } = usePermissions();
```

Usage in templates: `v-if="can('create', 'users')"`, `v-if="can('edit', 'plans')"`, etc.

### Navigation filtering

`useNavigation(user: Ref<User | null>, hasTenant: Ref<boolean>)`:
- `hasTenant = true` → tenant nav (all visible)
- `hasTenant = false` → central nav filtered by `can()` via `filterNav()`

## Frontend conventions

- **No static Tailwind colors** — use shadcn CSS variables (`bg-background`, `text-foreground`, etc.)
- **CRUD pattern**: table + dialog (not inline forms) — except Roles/Form.vue (permission matrix)
- **`<Link>` from Inertia** — never `<a href>` for internal routes
- **`Switch`**: use `:model-value` + `@update:model-value`
- **`SelectTrigger`**: always `class="w-full"`
- **Forms**: `useForm()` from `@inertiajs/vue3`
- **Ziggy `route()`** is global — no import needed
- **Alias**: `@/` → `resources/js/`
- **Flash messages**: `return back()->with('success'|'error', 'message')`
- **DatePicker**: `@internationalized/date`, emits `yyyy-MM-dd`
- **Date formatting** in tables: `date-fns` with `es` locale
- **Currency**: `Intl.NumberFormat('es-EC', { style: 'currency', currency: 'USD' })`

## Testing quirks

- `phpunit.xml` uses SQLite in-memory, `QUEUE_CONNECTION=sync`
- Tests use `php artisan make:test --phpunit` (Pest is NOT used — convert if found)
- No CI workflows exist
- SRI/SSO integrations are not mocked by default — test carefully

## Environment & config

- **Timezone**: `America/Guayaquil`
- **Database**: PostgreSQL (central DB + per-tenant DBs)
- **Cache/Queue**: Redis
- **Central domains**: configured via `.env` `CENTRAL_DOMAINS` (default `localhost,127.0.0.1`)
- **SRI API**: `.env` keys `SRI_CATASTRO_URL`, `SRI_CAPTCHA_API_KEY`
- Custom autoloaded files: `app/Config/Constants.php`, `app/Helpers/helpers.php`
- `config/tenancy.php` — central domains, DB prefix, middleware pipeline

## Deployment (bare metal, no Docker in prod)

Production server runs Ubuntu + nginx + php8.3-fpm + supervisor.
Deployment via `deployment/deploy.sh` (git pull, composer --no-dev, npm ci + build, migrate, config cache, reload php-fpm + supervisor).

## Skills (don't skip)

Load these skills from `.agents/skills/` when the task matches:
- `laravel-best-practices` — optimizing Eloquent, N+1, caching, validation, policies
- `inertia-vue-development` — Inertia + Vue forms, navigation, deferred props
- `tailwindcss-development` — Tailwind v4 styling, responsive layouts, dark mode

## Miscellaneous

- `boost.json` enables guidelines for `claude_code`, `cursor`, `opencode`
- Laravel Boost MCP is configured in `opencode.json` — prefer its tools (`database-query`, `database-schema`, `get-absolute-url`, `browser-logs`) over raw shell
- SSO: `GET /auth/sso` — tenant authentication via SsoController
- Protected system slugs (disable delete in UI): `super_admin`, `admin`, `employee` (roles); `permissions`, `models`, `roles`, `users`, `plans`, `subscriptions` (modules)
