# Phase 1: Foundation and Multi-Tenancy Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Establish the multi-tenant SaaS foundation with Docker Compose infrastructure (PostgreSQL, Redis, RabbitMQ, MinIO, Nginx), database schema, tenant context resolution, role-based membership policies, and administrative/portal organization management interfaces.

**Architecture:** Laravel 13 serves as the control plane inside `panel/`, utilizing PostgreSQL for relational persistence and Redis for caching and queues. Organizations are represented as `tenants` with a many-to-many `tenant_user` membership table holding tenant-specific roles (`owner`, `admin`, `member`, `viewer`), strictly isolated from platform-level administrative roles (`admin`, `user`). A context resolution middleware enforces active tenant scoping across all portal requests.

**Tech Stack:** Laravel 13, PHP 8.4, Inertia.js v2, React 19, Wayfinder, Tailwind CSS v4, PostgreSQL 16, Redis 7, Docker Compose, Pest PHP v4, DevWizard Enumify & Localizer.

**Spec:** [docs/architecture.md](file:///D:/Playground/wiz-video-platform/docs/architecture.md)

## Global Constraints

- Monorepo directory structure: Laravel application in `panel/`, media processor in `worker/`, Docker/Nginx configurations in `infrastructure/`, compose orchestration at root `docker-compose.yml`.
- All PHP code must use `declare(strict_types=1);` and modern PHP 8.4 syntax with attribute-based Eloquent definitions.
- All enums must be string-backed, use `SCREAMING_SNAKE_CASE` cases, implement `HasLabels`, and use `DevWizardHQ\Enumify\Concerns\EnumHelpers`.
- All user-facing text in frontend and backend must be localized via `__()` or `@devwizard/laravel-localizer-react` (`lang/en.json`). No raw text.
- Frontend routes and actions must use typed Wayfinder imports (`@/routes/...`).
- Tenant isolation is non-negotiable: all queries in portal routes must scope by `tenant_id` or active tenant context.

## Review Focus

- **Orphaned User without Tenants**: When a user registers or has all memberships deleted and visits `/panel`, the system must automatically provision a personal organization ("{Name}'s Team") rather than crashing with a 500 error on null tenant context. (Covered in Task 4)
- **Owner Removal / Demotion Deadlock**: An organization must never be left without an active owner. Attempts to remove or demote the last remaining owner must be rejected with HTTP 422 Unprocessable Entity. (Covered in Task 7)
- **Cross-Tenant Context Spoofing**: If an authenticated user attempts to switch to an organization UUID they do not belong to via `POST /panel/tenants/switch/{tenant}`, the request must return HTTP 403 Forbidden and retain their current tenant. (Covered in Task 6)
- **Suspended Organization Access**: If an organization is marked `SUSPENDED` by a platform administrator, all subsequent portal requests by its members must be blocked with HTTP 403 or redirect to an organization suspended page. (Covered in Task 4)
- **Duplicate Membership Invitation**: Inviting a user who is already an active member of the target tenant must fail validation with HTTP 422 and a friendly error message. (Covered in Task 7)

---

### Task 1: Docker Compose and PostgreSQL Infrastructure

**Files:**
- Create: `docker-compose.yml`
- Create: `infrastructure/nginx/default.conf`
- Modify: `panel/.env.example`
- Modify: `panel/config/database.php`

**Interfaces:**
- Consumes: None (Root orchestration)
- Produces: Running containerized services (`postgres`, `redis`, `rabbitmq`, `minio`, `nginx`) and environment configurations for PostgreSQL.

- [ ] **Step 1: Write validation test for Docker Compose & DB Configuration**

Create `panel/tests/Feature/InfrastructureConfigTest.php`:
```php
<?php

declare(strict_types=1);

test('postgresql connection configuration is properly defined', function (): void {
    $config = config('database.connections.pgsql');

    expect($config['driver'])->toBe('pgsql')
        ->and($config['search_path'])->toBe('public')
        ->and($config['sslmode'])->toBe('prefer');
});

test('docker compose file exists and defines required MVP services', function (): void {
    $composePath = base_path('../docker-compose.yml');
    expect(file_exists($composePath))->toBeTrue();

    $content = file_get_contents($composePath);
    expect($content)
        ->toContain('postgres:')
        ->toContain('redis:')
        ->toContain('rabbitmq:')
        ->toContain('minio:')
        ->toContain('nginx:');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd panel; php artisan test --filter=InfrastructureConfigTest`
Expected: FAIL (Docker compose file does not define services).

- [ ] **Step 3: Implement Docker Compose, Nginx config, and Environment Settings**

1. Write `docker-compose.yml` at repository root defining:
   - `postgres`: image `postgres:16-alpine`, environment `POSTGRES_DB=wiz_video`, `POSTGRES_USER=wiz_user`, `POSTGRES_PASSWORD=secret`, port `5432:5432`, volume `postgres_data:/var/lib/postgresql/data`.
   - `redis`: image `redis:7-alpine`, port `6379:6379`, volume `redis_data:/data`.
   - `rabbitmq`: image `rabbitmq:3-management-alpine`, ports `5672:5672`, `15672:15672`, volume `rabbitmq_data:/var/lib/rabbitmq`.
   - `minio`: image `minio/minio:latest`, command `server /data --console-address ":9001"`, ports `9000:9000`, `9001:9001`, volume `minio_data:/data`.
   - `nginx`: image `nginx:alpine`, ports `80:80`, volumes `./infrastructure/nginx/default.conf:/etc/nginx/conf.d/default.conf:ro`.
2. Write `infrastructure/nginx/default.conf` forwarding web traffic to `app:8000` or local dev port.
3. Update `panel/.env.example` to set:
   - `DB_CONNECTION=pgsql`
   - `DB_HOST=127.0.0.1`
   - `DB_PORT=5432`
   - `DB_DATABASE=wiz_video`
   - `DB_USERNAME=wiz_user`
   - `DB_PASSWORD=secret`
   - `REDIS_HOST=127.0.0.1`
   - `QUEUE_CONNECTION=redis`
   - `CACHE_STORE=redis`

- [ ] **Step 4: Run test to verify it passes**

Run: `cd panel; php artisan test --filter=InfrastructureConfigTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add docker-compose.yml infrastructure/ panel/.env.example panel/config/database.php panel/tests/Feature/InfrastructureConfigTest.php
git commit -m "feat(infra): configure docker-compose services and postgresql database settings"
```

---

### Task 2: Tenant Domain Enums and Database Migrations

**Files:**
- Create: `panel/app/Enums/TenantStatus.php`
- Create: `panel/app/Enums/TenantRole.php`
- Create: `panel/app/Enums/TenantUserStatus.php`
- Create: `panel/database/migrations/2026_11_11_000001_create_tenants_table.php`
- Create: `panel/database/migrations/2026_11_11_000002_create_tenant_user_table.php`
- Test: `panel/tests/Feature/TenantMigrationTest.php`

**Interfaces:**
- Consumes: None
- Produces: `TenantStatus`, `TenantRole`, `TenantUserStatus` PHP Enums; `tenants` and `tenant_user` tables in PostgreSQL.

- [ ] **Step 1: Write failing test for Tenant Enums and Migrations**

Create `panel/tests/Feature/TenantMigrationTest.php`:
```php
<?php

declare(strict_types=1);

use App\Enums\TenantRole;
use App\Enums\TenantStatus;
use App\Enums\TenantUserStatus;
use Illuminate\Support\Facades\Schema;

test('tenant tables exist with expected schema columns', function (): void {
    expect(Schema::hasTable('tenants'))->toBeTrue()
        ->and(Schema::hasColumns('tenants', ['id', 'name', 'slug', 'status', 'settings', 'created_at', 'updated_at', 'deleted_at']))->toBeTrue();

    expect(Schema::hasTable('tenant_user'))->toBeTrue()
        ->and(Schema::hasColumns('tenant_user', ['id', 'tenant_id', 'user_id', 'role', 'status', 'created_at', 'updated_at']))->toBeTrue();
});

test('tenant enums contain required cases and label implementations', function (): void {
    expect(TenantStatus::ACTIVE->value)->toBe('active')
        ->and(TenantStatus::SUSPENDED->value)->toBe('suspended')
        ->and(TenantStatus::ARCHIVED->value)->toBe('archived')
        ->and(TenantStatus::ACTIVE->label())->toBe('Active');

    expect(TenantRole::OWNER->value)->toBe('owner')
        ->and(TenantRole::ADMIN->value)->toBe('admin')
        ->and(TenantRole::MEMBER->value)->toBe('member')
        ->and(TenantRole::VIEWER->value)->toBe('viewer')
        ->and(TenantRole::OWNER->isOwner())->toBeTrue()
        ->and(TenantRole::ADMIN->isOwner())->toBeFalse();

    expect(TenantUserStatus::ACTIVE->value)->toBe('active')
        ->and(TenantUserStatus::INVITED->value)->toBe('invited')
        ->and(TenantUserStatus::SUSPENDED->value)->toBe('suspended');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd panel; php artisan test --filter=TenantMigrationTest`
Expected: FAIL with missing classes/tables.

- [ ] **Step 3: Implement Enums and Migrations**

1. Create `panel/app/Enums/TenantStatus.php`:
   - String-backed: `ACTIVE = 'active'`, `SUSPENDED = 'suspended'`, `ARCHIVED = 'archived'`.
   - Uses `EnumHelpers`, implements `HasLabels`.
   - `label()` returning localized strings.
   - `color()` returning `'green'`, `'red'`, `'gray'`.
2. Create `panel/app/Enums/TenantRole.php`:
   - String-backed: `OWNER = 'owner'`, `ADMIN = 'admin'`, `MEMBER = 'member'`, `VIEWER = 'viewer'`.
   - Methods: `isOwner(): bool`, `isAdmin(): bool`, `canManageMembers(): bool`, `canManageVideos(): bool`.
3. Create `panel/app/Enums/TenantUserStatus.php`:
   - String-backed: `ACTIVE = 'active'`, `INVITED = 'invited'`, `SUSPENDED = 'suspended'`.
4. Create migration `2026_11_11_000001_create_tenants_table.php`:
   - `uuid('id')->primary()`
   - `string('name')`
   - `string('slug')->unique()`
   - `string('status')->default(TenantStatus::ACTIVE->value)->index()`
   - `jsonb('settings')->nullable()`
   - `timestamps()`, `softDeletes()`
5. Create migration `2026_11_11_000002_create_tenant_user_table.php`:
   - `uuid('id')->primary()`
   - `foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete()`
   - `foreignId('user_id')->constrained('users')->cascadeOnDelete()`
   - `string('role')->default(TenantRole::MEMBER->value)`
   - `string('status')->default(TenantUserStatus::ACTIVE->value)->index()`
   - `timestamps()`
   - `unique(['tenant_id', 'user_id'])`

- [ ] **Step 4: Run test to verify it passes**

Run: `cd panel; php artisan test --filter=TenantMigrationTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add panel/app/Enums/Tenant* panel/database/migrations/*create_tenant* panel/tests/Feature/TenantMigrationTest.php
git commit -m "feat(tenants): add tenant enums and database schema migrations"
```

---

### Task 3: Tenant and TenantUser Models, Relationships, and Factories

**Files:**
- Create: `panel/app/Domain/Tenants/Models/Tenant.php`
- Create: `panel/app/Domain/Tenants/Models/TenantUser.php`
- Create: `panel/database/factories/TenantFactory.php`
- Create: `panel/database/factories/TenantUserFactory.php`
- Modify: `panel/app/Models/User.php`
- Test: `panel/tests/Feature/TenantModelTest.php`

**Interfaces:**
- Consumes: `TenantStatus`, `TenantRole`, `TenantUserStatus`
- Produces: `Tenant` Eloquent model, `TenantUser` pivot model, `User` tenant relationships and helper methods.

- [ ] **Step 1: Write failing test for Tenant and User models**

Create `panel/tests/Feature/TenantModelTest.php`:
```php
<?php

declare(strict_types=1);

use App\Domain\Tenants\Models\Tenant;
use App\Domain\Tenants\Models\TenantUser;
use App\Enums\TenantRole;
use App\Enums\TenantStatus;
use App\Models\User;

test('tenant can be created via factory and has enum cast', function (): void {
    $tenant = Tenant::factory()->create([
        'name' => 'Acme Media',
        'status' => TenantStatus::ACTIVE,
    ]);

    expect($tenant->id)->toBeString()
        ->and($tenant->status)->toBe(TenantStatus::ACTIVE)
        ->and($tenant->status->isActive())->toBeTrue();
});

test('tenant belongs to many users through tenant_user pivot', function (): void {
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->create();
    $member = User::factory()->create();

    $tenant->users()->attach($owner->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::OWNER->value]);
    $tenant->users()->attach($member->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::MEMBER->value]);

    expect($tenant->users)->toHaveCount(2)
        ->and($owner->tenants)->toHaveCount(1)
        ->and($owner->isTenantOwner($tenant))->toBeTrue()
        ->and($member->isTenantOwner($tenant))->toBeFalse()
        ->and($member->isTenantMember($tenant))->toBeTrue()
        ->and($owner->tenantRole($tenant))->toBe(TenantRole::OWNER);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd panel; php artisan test --filter=TenantModelTest`
Expected: FAIL (Tenant model does not exist).

- [ ] **Step 3: Implement Models, User Relationships, and Factories**

1. Create `panel/app/Domain/Tenants/Models/Tenant.php`:
   - Uses `HasFactory`, `HasUuids`, `SoftDeletes`.
   - Fillable: `['name', 'slug', 'status', 'settings']`.
   - Casts: `'status' => TenantStatus::class`, `'settings' => 'array'`.
   - Relationships:
     - `users(): BelongsToMany`: `belongsToMany(User::class, 'tenant_user')->using(TenantUser::class)->withPivot(['id', 'role', 'status'])->withTimestamps()`.
     - `memberships(): HasMany`: `hasMany(TenantUser::class, 'tenant_id')`.
   - Scopes:
     - `scopeActive($query)`: `where('status', TenantStatus::ACTIVE)`.
     - `scopeSearch($query, ?string $term)`: searches `name` and `slug`.
2. Create `panel/app/Domain/Tenants/Models/TenantUser.php`:
   - Extends `Illuminate\Database\Eloquent\Relations\Pivot`.
   - Uses `HasFactory`, `HasUuids`.
   - Fillable: `['id', 'tenant_id', 'user_id', 'role', 'status']`.
   - Casts: `'role' => TenantRole::class`, `'status' => TenantUserStatus::class`.
   - Relationships: `tenant(): BelongsTo`, `user(): BelongsTo`.
3. Modify `panel/app/Models/User.php`:
   - Add relationships:
     - `tenants(): BelongsToMany` (through `tenant_user` using `TenantUser`).
     - `tenantMemberships(): HasMany(TenantUser::class)`.
   - Add helper methods:
     - `isTenantOwner(Tenant $tenant): bool`
     - `isTenantMember(Tenant $tenant): bool`
     - `tenantRole(Tenant $tenant): ?TenantRole`
4. Create factories:
   - `panel/database/factories/TenantFactory.php`
   - `panel/database/factories/TenantUserFactory.php`

- [ ] **Step 4: Run test to verify it passes**

Run: `cd panel; php artisan test --filter=TenantModelTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add panel/app/Domain/Tenants/Models/ panel/app/Models/User.php panel/database/factories/Tenant* panel/tests/Feature/TenantModelTest.php
git commit -m "feat(tenants): implement Tenant and TenantUser models and user relations"
```

---

### Task 4: Tenant Context Management and Isolation Middleware

**Files:**
- Create: `panel/app/Domain/Tenants/Services/TenantContextService.php`
- Create: `panel/app/Http/Middleware/EnsureTenantContext.php`
- Modify: `panel/bootstrap/app.php`
- Test: `panel/tests/Feature/TenantContextTest.php`

**Interfaces:**
- Consumes: `Tenant`, `TenantUser`, `User`
- Produces: `TenantContextService`, `EnsureTenantContext` middleware, shared Inertia `currentTenant` property.

- [ ] **Step 1: Write failing tests for Tenant Context and Review Focus edge cases**

Create `panel/tests/Feature/TenantContextTest.php`:
```php
<?php

declare(strict_types=1);

use App\Domain\Tenants\Models\Tenant;
use App\Domain\Tenants\Services\TenantContextService;
use App\Enums\TenantRole;
use App\Enums\TenantStatus;
use App\Models\User;

test('context service resolves and stores active tenant in session', function (): void {
    $user = User::factory()->create();
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();
    $tenant1->users()->attach($user->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::OWNER->value]);
    $tenant2->users()->attach($user->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::MEMBER->value]);

    $service = app(TenantContextService::class);
    expect($service->getCurrentTenant($user)->id)->toBe($tenant1->id);

    $service->setCurrentTenant($user, $tenant2);
    expect($service->getCurrentTenant($user)->id)->toBe($tenant2->id);
});

// Review Focus 1: Orphaned User without Tenants
test('user without any tenants is automatically provisioned a personal organization on portal request', function (): void {
    $user = User::factory()->create(['name' => 'Jane Developer']);

    $response = $this->actingAs($user)->get(route('portal.dashboard'));

    $response->assertOk();
    expect($user->fresh()->tenants)->toHaveCount(1)
        ->and($user->fresh()->tenants->first()->name)->toContain('Jane Developer');
});

// Review Focus 4: Suspended Organization Access
test('user whose active organization is suspended is blocked from accessing portal', function (): void {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create(['status' => TenantStatus::SUSPENDED]);
    $tenant->users()->attach($user->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::MEMBER->value]);

    $response = $this->actingAs($user)->get(route('portal.dashboard'));

    $response->assertForbidden();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd panel; php artisan test --filter=TenantContextTest`
Expected: FAIL (TenantContextService and EnsureTenantContext middleware missing).

- [ ] **Step 3: Implement TenantContextService and EnsureTenantContext Middleware**

1. Create `panel/app/Domain/Tenants/Services/TenantContextService.php`:
   - `getCurrentTenant(User $user): ?Tenant`:
     - Checks session `current_tenant_id`.
     - Validates user belongs to that tenant and tenant is not deleted.
     - Fallback: loads user's first active tenant.
     - If user has no active tenant, creates a default organization: `Tenant::create(['name' => "{$user->name}'s Team", 'slug' => Str::slug("{$user->name}-team-".Str::random(5)), 'status' => TenantStatus::ACTIVE])` and attaches user as `TenantRole::OWNER`.
   - `setCurrentTenant(User $user, Tenant $tenant): bool`:
     - Verifies `$user->isTenantMember($tenant)`.
     - Sets `session(['current_tenant_id' => $tenant->id])`.
   - `clearCurrentTenant(): void`: removes `current_tenant_id` from session.
2. Create `panel/app/Http/Middleware/EnsureTenantContext.php`:
   - Resolves active tenant via `TenantContextService`.
   - If tenant is suspended (`$tenant->status->isSuspended()`), aborts with `403` and message `__('Your organization has been suspended. Please contact support.')`.
   - Binds `$tenant` into Laravel container as `'current_tenant'` and shares `$tenant` with Inertia:
     `Inertia::share('currentTenant', fn () => $tenant ? [...data...] : null);`
3. Register middleware in `panel/bootstrap/app.php` and attach to portal route group in `panel/routes/portal.php`.

- [ ] **Step 4: Run test to verify it passes**

Run: `cd panel; php artisan test --filter=TenantContextTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add panel/app/Domain/Tenants/Services/ panel/app/Http/Middleware/EnsureTenantContext.php panel/bootstrap/app.php panel/routes/portal.php panel/tests/Feature/TenantContextTest.php
git commit -m "feat(tenants): implement tenant context resolution service and isolation middleware"
```

---

### Task 5: Platform Permissions and Tenant-Aware Policies

**Files:**
- Modify: `panel/app/Support/Authorization/RolePermissions.php`
- Create: `panel/app/Policies/TenantPolicy.php`
- Modify: `panel/app/Providers/AppServiceProvider.php`
- Test: `panel/tests/Feature/TenantPolicyTest.php`

**Interfaces:**
- Consumes: `RolePermissions`, `TenantRole`, `Tenant`, `User`
- Produces: Synced platform permissions for tenants; `TenantPolicy` authorization rules.

- [ ] **Step 1: Write failing test for Tenant Permissions and Policy**

Create `panel/tests/Feature/TenantPolicyTest.php`:
```php
<?php

declare(strict_types=1);

use App\Domain\Tenants\Models\Tenant;
use App\Enums\TenantRole;
use App\Models\User;

test('platform admin can view and update any tenant', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $tenant = Tenant::factory()->create();

    expect($admin->can('view', $tenant))->toBeTrue()
        ->and($admin->can('update', $tenant))->toBeTrue()
        ->and($admin->can('delete', $tenant))->toBeTrue();
});

test('tenant owner can update tenant and manage members', function (): void {
    $owner = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $tenant->users()->attach($owner->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::OWNER->value]);

    expect($owner->can('view', $tenant))->toBeTrue()
        ->and($owner->can('update', $tenant))->toBeTrue()
        ->and($owner->can('manageMembers', $tenant))->toBeTrue();
});

test('tenant member cannot update tenant or manage members', function (): void {
    $member = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $tenant->users()->attach($member->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::MEMBER->value]);

    expect($member->can('view', $tenant))->toBeTrue()
        ->and($member->can('update', $tenant))->toBeFalse()
        ->and($member->can('manageMembers', $tenant))->toBeFalse();
});

test('unrelated user cannot view or manage tenant', function (): void {
    $stranger = User::factory()->create();
    $tenant = Tenant::factory()->create();

    expect($stranger->can('view', $tenant))->toBeFalse()
        ->and($stranger->can('update', $tenant))->toBeFalse();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd panel; php artisan test --filter=TenantPolicyTest`
Expected: FAIL (TenantPolicy not defined).

- [ ] **Step 3: Implement Permissions in RolePermissions and TenantPolicy**

1. Modify `panel/app/Support/Authorization/RolePermissions.php`:
   - Under `permissions()` array, add group:
     ```php
     'Tenants' => [
         [
             'name' => 'tenants.view',
             'display_name' => 'View Tenants',
             'roles' => ['admin'],
         ],
         [
             'name' => 'tenants.create',
             'display_name' => 'Create Tenants',
             'roles' => ['admin'],
         ],
         [
             'name' => 'tenants.update',
             'display_name' => 'Update Tenants',
             'roles' => ['admin'],
         ],
         [
             'name' => 'tenants.suspend',
             'display_name' => 'Suspend Tenants',
             'roles' => ['admin'],
         ],
         [
             'name' => 'tenants.delete',
             'display_name' => 'Delete Tenants',
             'roles' => ['admin'],
         ],
     ],
     ```
2. Create `panel/app/Policies/TenantPolicy.php`:
   - `viewAny(User $user): bool`: checks `$user->can('tenants.view')`.
   - `view(User $user, Tenant $tenant): bool`: platform admin (`tenants.view`) OR `$user->isTenantMember($tenant)`.
   - `create(User $user): bool`: `$user->can('tenants.create')` OR user is authenticated.
   - `update(User $user, Tenant $tenant): bool`: platform admin (`tenants.update`) OR `$user->tenantRole($tenant)?->isOwner()` OR `$user->tenantRole($tenant)?->isAdmin()`.
   - `delete(User $user, Tenant $tenant): bool`: platform admin (`tenants.delete`) OR `$user->tenantRole($tenant)?->isOwner()`.
   - `manageMembers(User $user, Tenant $tenant): bool`: platform admin OR (`$user->tenantRole($tenant)?->canManageMembers() ?? false)`.
3. Register policy in `panel/app/Providers/AppServiceProvider.php` (or auto-discovery).

- [ ] **Step 4: Run test to verify it passes**

Run: `cd panel; php artisan test --filter=TenantPolicyTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add panel/app/Support/Authorization/RolePermissions.php panel/app/Policies/TenantPolicy.php panel/app/Providers/AppServiceProvider.php panel/tests/Feature/TenantPolicyTest.php
git commit -m "feat(auth): define platform tenant permissions and tenant-aware policy"
```

---

### Task 6: Portal Tenant Switcher and Organization Profile API

**Files:**
- Create: `panel/app/Http/Controllers/Portal/TenantSwitchController.php`
- Create: `panel/app/Http/Controllers/Portal/Settings/OrganizationController.php`
- Create: `panel/app/Http/Requests/Portal/UpdateOrganizationRequest.php`
- Modify: `panel/routes/portal.php`
- Test: `panel/tests/Feature/PortalOrganizationTest.php`

**Interfaces:**
- Consumes: `TenantContextService`, `TenantPolicy`, `Tenant`
- Produces: Tenant switching endpoint, Organization settings view/update endpoints.

- [ ] **Step 1: Write failing test including Review Focus 3 (Cross-Tenant Context Spoofing)**

Create `panel/tests/Feature/PortalOrganizationTest.php`:
```php
<?php

declare(strict_types=1);

use App\Domain\Tenants\Models\Tenant;
use App\Enums\TenantRole;
use App\Models\User;

test('user can switch between organizations they belong to', function (): void {
    $user = User::factory()->create();
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();
    $tenant1->users()->attach($user->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::OWNER->value]);
    $tenant2->users()->attach($user->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::MEMBER->value]);

    $response = $this->actingAs($user)->post(route('portal.tenants.switch', $tenant2));

    $response->assertRedirect();
    expect(session('current_tenant_id'))->toBe($tenant2->id);
});

// Review Focus 3: Cross-Tenant Context Spoofing
test('user cannot switch to an organization they are not a member of', function (): void {
    $user = User::factory()->create();
    $myTenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $myTenant->users()->attach($user->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::OWNER->value]);

    $response = $this->actingAs($user)->post(route('portal.tenants.switch', $otherTenant));

    $response->assertForbidden();
    expect(session('current_tenant_id'))->not()->toBe($otherTenant->id);
});

test('organization owner can update organization name and slug', function (): void {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create(['name' => 'Old Name', 'slug' => 'old-name']);
    $tenant->users()->attach($user->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::OWNER->value]);

    session(['current_tenant_id' => $tenant->id]);

    $response = $this->actingAs($user)->put(route('portal.settings.organization.update'), [
        'name' => 'Updated Studio',
        'slug' => 'updated-studio',
    ]);

    $response->assertRedirect();
    expect($tenant->fresh()->name)->toBe('Updated Studio')
        ->and($tenant->fresh()->slug)->toBe('updated-studio');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd panel; php artisan test --filter=PortalOrganizationTest`
Expected: FAIL (Routes and controllers do not exist).

- [ ] **Step 3: Implement TenantSwitchController, OrganizationController, and FormRequest**

1. Create `panel/app/Http/Controllers/Portal/TenantSwitchController.php`:
   - `switch(Request $request, Tenant $tenant, TenantContextService $contextService)`:
     - Verify `$request->user()->isTenantMember($tenant)`. If not, abort with `403`.
     - Call `$contextService->setCurrentTenant($request->user(), $tenant)`.
     - Redirect back with success flash message `__('Switched active organization.')`.
2. Create `panel/app/Http/Requests/Portal/UpdateOrganizationRequest.php`:
   - Rules:
     - `'name' => ['required', 'string', 'max:255']`
     - `'slug' => ['required', 'string', 'max:100', 'alpha_dash', Rule::unique('tenants', 'slug')->ignore($currentTenant->id)]`
3. Create `panel/app/Http/Controllers/Portal/Settings/OrganizationController.php`:
   - `overview(Request $request)`:
     - Authorize `$request->user()->can('view', $currentTenant)`.
     - Render Inertia page `'portal/settings/organization/overview'` passing `organization` data and user's role.
   - `update(UpdateOrganizationRequest $request)`:
     - Authorize `$request->user()->can('update', $currentTenant)`.
     - Update `$currentTenant->update($request->validated())`.
     - Return back with success flash.
4. Add routes to `panel/routes/portal.php`:
   - `Route::post('tenants/switch/{tenant}', [TenantSwitchController::class, 'switch'])->name('tenants.switch');`
   - Inside `Route::prefix('settings')->name('settings.')`:
     - `Route::get('organization', [OrganizationController::class, 'overview'])->name('organization.overview');`
     - `Route::put('organization', [OrganizationController::class, 'update'])->name('organization.update');`

- [ ] **Step 4: Run test to verify it passes**

Run: `cd panel; php artisan test --filter=PortalOrganizationTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add panel/app/Http/Controllers/Portal/Tenant* panel/app/Http/Controllers/Portal/Settings/OrganizationController.php panel/app/Http/Requests/Portal/UpdateOrganizationRequest.php panel/routes/portal.php panel/tests/Feature/PortalOrganizationTest.php
git commit -m "feat(portal): add tenant switcher and organization profile settings"
```

---

### Task 7: Portal Organization Members Management API

**Files:**
- Create: `panel/app/Http/Controllers/Portal/Settings/OrganizationMemberController.php`
- Create: `panel/app/Http/Requests/Portal/StoreMemberRequest.php`
- Create: `panel/app/Http/Requests/Portal/UpdateMemberRoleRequest.php`
- Modify: `panel/routes/portal.php`
- Test: `panel/tests/Feature/PortalMemberManagementTest.php`

**Interfaces:**
- Consumes: `TenantContextService`, `TenantUser`, `TenantRole`
- Produces: Member invitation/addition, role update, and member removal endpoints with safety checks against owner deadlock and duplicate invites.

- [ ] **Step 1: Write failing test including Review Focus 2 and Review Focus 5**

Create `panel/tests/Feature/PortalMemberManagementTest.php`:
```php
<?php

declare(strict_types=1);

use App\Domain\Tenants\Models\Tenant;
use App\Enums\TenantRole;
use App\Models\User;

test('owner can add an existing user as member to the organization', function (): void {
    $owner = User::factory()->create();
    $newUser = User::factory()->create(['email' => 'worker@example.com']);
    $tenant = Tenant::factory()->create();
    $tenant->users()->attach($owner->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::OWNER->value]);

    session(['current_tenant_id' => $tenant->id]);

    $response = $this->actingAs($owner)->post(route('portal.settings.organization.members.store'), [
        'email' => 'worker@example.com',
        'role' => TenantRole::MEMBER->value,
    ]);

    $response->assertRedirect();
    expect($tenant->users()->where('users.id', $newUser->id)->exists())->toBeTrue();
});

// Review Focus 5: Duplicate Membership Invitation
test('adding an already existing member fails with validation error', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create(['email' => 'existing@example.com']);
    $tenant = Tenant::factory()->create();
    $tenant->users()->attach($owner->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::OWNER->value]);
    $tenant->users()->attach($member->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::MEMBER->value]);

    session(['current_tenant_id' => $tenant->id]);

    $response = $this->actingAs($owner)->post(route('portal.settings.organization.members.store'), [
        'email' => 'existing@example.com',
        'role' => TenantRole::MEMBER->value,
    ]);

    $response->assertSessionHasErrors('email');
});

// Review Focus 2: Owner Removal / Demotion Deadlock
test('sole owner cannot remove themselves from the organization', function (): void {
    $owner = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $tenant->users()->attach($owner->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::OWNER->value]);
    $pivot = $tenant->memberships()->where('user_id', $owner->id)->first();

    session(['current_tenant_id' => $tenant->id]);

    $response = $this->actingAs($owner)->delete(route('portal.settings.organization.members.destroy', $pivot));

    $response->assertStatus(422);
    expect($tenant->users()->count())->toBe(1);
});

test('sole owner cannot demote their role to member', function (): void {
    $owner = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $tenant->users()->attach($owner->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::OWNER->value]);
    $pivot = $tenant->memberships()->where('user_id', $owner->id)->first();

    session(['current_tenant_id' => $tenant->id]);

    $response = $this->actingAs($owner)->patch(route('portal.settings.organization.members.update', $pivot), [
        'role' => TenantRole::MEMBER->value,
    ]);

    $response->assertStatus(422);
    expect($pivot->fresh()->role)->toBe(TenantRole::OWNER);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd panel; php artisan test --filter=PortalMemberManagementTest`
Expected: FAIL (Controller and routes missing).

- [ ] **Step 3: Implement OrganizationMemberController and FormRequests**

1. Create `panel/app/Http/Requests/Portal/StoreMemberRequest.php`:
   - Validates `'email' => ['required', 'email', 'exists:users,email']`.
   - Validates `'role' => ['required', Rule::enum(TenantRole::class)]`.
   - Custom validation rule / after hook: checks if user with this email is already a member of current tenant; if so, adds error `'email' => __('This user is already a member of this organization.')`.
2. Create `panel/app/Http/Requests/Portal/UpdateMemberRoleRequest.php`:
   - Validates `'role' => ['required', Rule::enum(TenantRole::class)]`.
3. Create `panel/app/Http/Controllers/Portal/Settings/OrganizationMemberController.php`:
   - `index(Request $request)`:
     - Authorize `$request->user()->can('manageMembers', $currentTenant)`.
     - Fetch members with pagination or list, including roles and statuses.
     - Render Inertia `'portal/settings/organization/members'`.
   - `store(StoreMemberRequest $request)`:
     - Authorize `$request->user()->can('manageMembers', $currentTenant)`.
     - Find user by email, attach to `$currentTenant->users()` with UUID id, role, and active status.
     - Redirect back with success flash.
   - `update(UpdateMemberRoleRequest $request, TenantUser $membership)`:
     - Verify `$membership->tenant_id === $currentTenant->id`.
     - If `$membership->role === TenantRole::OWNER` and `$request->role !== TenantRole::OWNER->value`:
       - Count other owners: `$currentTenant->memberships()->where('role', TenantRole::OWNER)->where('id', '!=', $membership->id)->count()`.
       - If count is 0, abort with 422: `__('An organization must have at least one active owner.')`.
     - Update `$membership->update(['role' => $request->role])`.
     - Redirect back.
   - `destroy(TenantUser $membership)`:
     - Verify `$membership->tenant_id === $currentTenant->id`.
     - If `$membership->role === TenantRole::OWNER`:
       - Count other owners. If 0, abort with 422: `__('Cannot remove the only owner of the organization.')`.
     - Delete `$membership->delete()`.
     - Redirect back with success message.
4. Add routes to `panel/routes/portal.php` under `settings/organization/members`.

- [ ] **Step 4: Run test to verify it passes**

Run: `cd panel; php artisan test --filter=PortalMemberManagementTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add panel/app/Http/Controllers/Portal/Settings/OrganizationMemberController.php panel/app/Http/Requests/Portal/StoreMemberRequest.php panel/app/Http/Requests/Portal/UpdateMemberRoleRequest.php panel/routes/portal.php panel/tests/Feature/PortalMemberManagementTest.php
git commit -m "feat(portal): add organization member management with owner protection"
```

---

### Task 8: Admin Panel Tenant Management API and Controller

**Files:**
- Create: `panel/app/Http/Controllers/Admin/TenantController.php`
- Create: `panel/app/Http/Requests/Admin/StoreTenantRequest.php`
- Create: `panel/app/Http/Requests/Admin/UpdateTenantRequest.php`
- Create: `panel/app/Http/Requests/Admin/ChangeTenantStatusRequest.php`
- Modify: `panel/routes/admin.php`
- Test: `panel/tests/Feature/AdminTenantControllerTest.php`

**Interfaces:**
- Consumes: `RolePermissions`, `TenantPolicy`, `Tenant`
- Produces: Full administrative CRUD, search, filter, and suspension endpoints for platform administrators at `/panel/admin/tenants`.

- [ ] **Step 1: Write failing test for Admin Tenant Controller**

Create `panel/tests/Feature/AdminTenantControllerTest.php`:
```php
<?php

declare(strict_types=1);

use App\Domain\Tenants\Models\Tenant;
use App\Enums\TenantRole;
use App\Enums\TenantStatus;
use App\Models\User;

test('platform admin can list tenants with search and status filtering', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    Tenant::factory()->create(['name' => 'Alpha Studio', 'status' => TenantStatus::ACTIVE]);
    Tenant::factory()->create(['name' => 'Beta Productions', 'status' => TenantStatus::SUSPENDED]);

    $response = $this->actingAs($admin)->get(route('admin.tenants.index', ['search' => 'Alpha']));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/tenants/index')
            ->has('tenants.data', 1)
            ->where('tenants.data.0.name', 'Alpha Studio')
        );
});

test('platform admin can create a new tenant with initial owner', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $owner = User::factory()->create(['email' => 'client@example.com']);

    $response = $this->actingAs($admin)->post(route('admin.tenants.store'), [
        'name' => 'Gamma Media',
        'slug' => 'gamma-media',
        'owner_email' => 'client@example.com',
        'status' => TenantStatus::ACTIVE->value,
    ]);

    $response->assertRedirect();
    $tenant = Tenant::where('slug', 'gamma-media')->first();
    expect($tenant)->not()->toBeNull()
        ->and($tenant->users()->first()->id)->toBe($owner->id)
        ->and($owner->isTenantOwner($tenant))->toBeTrue();
});

test('platform admin can suspend and unsuspend a tenant', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $tenant = Tenant::factory()->create(['status' => TenantStatus::ACTIVE]);

    $this->actingAs($admin)->patch(route('admin.tenants.status', $tenant), [
        'status' => TenantStatus::SUSPENDED->value,
    ]);

    expect($tenant->fresh()->status)->toBe(TenantStatus::SUSPENDED);
});

test('regular user cannot access admin tenants endpoints', function (): void {
    $user = User::factory()->create();
    $user->assignRole('user');

    $response = $this->actingAs($user)->get(route('admin.tenants.index'));

    $response->assertForbidden();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd panel; php artisan test --filter=AdminTenantControllerTest`
Expected: FAIL (Controller and admin routes missing).

- [ ] **Step 3: Implement Admin TenantController and FormRequests**

1. Create FormRequests:
   - `StoreTenantRequest.php`: validates `name`, `slug` (unique), `owner_email` (required, exists:users,email), `status` (enum `TenantStatus`).
   - `UpdateTenantRequest.php`: validates `name`, `slug` (unique ignoring `$this->tenant->id`), `status`.
   - `ChangeTenantStatusRequest.php`: validates `status` (enum `TenantStatus`).
2. Create `panel/app/Http/Controllers/Admin/TenantController.php`:
   - `index(Request $request)`:
     - Applies search on name/slug and filter on `status`.
     - With `users_count` and eager loads.
     - Paginates and returns `Inertia::render('admin/tenants/index', ...)`.
   - `store(StoreTenantRequest $request)`:
     - Creates Tenant.
     - Attaches owner from `owner_email` with role `TenantRole::OWNER`.
     - Returns redirect with success message.
   - `show(Tenant $tenant)`:
     - Eager loads memberships and users.
     - Returns `Inertia::render('admin/tenants/show', ...)`.
   - `update(UpdateTenantRequest $request, Tenant $tenant)`:
     - Updates tenant data.
     - Returns redirect with success message.
   - `updateStatus(ChangeTenantStatusRequest $request, Tenant $tenant)`:
     - Updates status.
     - Returns redirect.
   - `destroy(Tenant $tenant)`:
     - Soft-deletes tenant.
     - Returns redirect.
3. Register routes in `panel/routes/admin.php`:
   ```php
   Route::prefix('tenants')->name('tenants.')->middleware('permission:tenants.view')->group(function (): void {
       Route::get('/', [TenantController::class, 'index'])->name('index');
       Route::post('/', [TenantController::class, 'store'])->middleware('permission:tenants.create')->name('store');
       Route::get('/{tenant}', [TenantController::class, 'show'])->name('show');
       Route::put('/{tenant}', [TenantController::class, 'update'])->middleware('permission:tenants.update')->name('update');
       Route::patch('/{tenant}/status', [TenantController::class, 'updateStatus'])->middleware('permission:tenants.suspend')->name('status');
       Route::delete('/{tenant}', [TenantController::class, 'destroy'])->middleware('permission:tenants.delete')->name('destroy');
   });
   ```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd panel; php artisan test --filter=AdminTenantControllerTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add panel/app/Http/Controllers/Admin/TenantController.php panel/app/Http/Requests/Admin/*Tenant* panel/routes/admin.php panel/tests/Feature/AdminTenantControllerTest.php
git commit -m "feat(admin): implement admin tenant management controller and routes"
```

---

### Task 9: Frontend Admin UI — Tenant Management Pages

**Files:**
- Create: `panel/resources/js/types/tenant.d.ts`
- Create: `panel/resources/js/pages/admin/tenants/index.tsx`
- Create: `panel/resources/js/pages/admin/tenants/show.tsx`
- Create: `panel/resources/js/pages/admin/tenants/partials/create-edit-sheet.tsx`
- Create: `panel/resources/js/pages/admin/tenants/partials/suspend-sheet.tsx`
- Modify: `panel/resources/js/layouts/admin-layout.tsx` (Add navigation item)
- Modify: `panel/lang/en.json`

**Interfaces:**
- Consumes: Wayfinder routes (`@/routes/admin/tenants`), Localizer (`@devwizard/laravel-localizer-react`), Radix UI components, `AdminLayout`.
- Produces: Complete admin UI for inspecting, creating, editing, and suspending tenants.

- [ ] **Step 1: Generate Wayfinder routes and Enumify TypeScript files**

Run:
```bash
cd panel
php artisan wayfinder:generate
php artisan enumify:generate
```
Expected: Success. Wayfinder routes and TypeScript enums updated.

- [ ] **Step 2: Create TypeScript Definitions for Tenants**

Create `panel/resources/js/types/tenant.d.ts`:
```typescript
export interface Tenant {
    id: string;
    name: string;
    slug: string;
    status: 'active' | 'suspended' | 'archived';
    settings?: Record<string, unknown> | null;
    users_count?: number;
    created_at: string;
    updated_at: string;
    users?: Array<{
        id: number;
        name: string;
        email: string;
        pivot: {
            id: string;
            role: 'owner' | 'admin' | 'member' | 'viewer';
            status: 'active' | 'invited' | 'suspended';
        };
    }>;
}
```

- [ ] **Step 3: Implement Admin Tenant Index and Sheets**

1. Create `panel/resources/js/pages/admin/tenants/index.tsx`:
   - Follows pattern in `panel/resources/js/pages/admin/users/index.tsx`.
   - Uses `AdminLayout`, `Pagination`, `FilterPanel`, `Badge`, `DropdownMenu`.
   - Actions: View details, Edit tenant sheet, Suspend/Unsuspend sheet, Delete dialog.
   - All strings wrapped with `__()`.
2. Create `partials/create-edit-sheet.tsx`:
   - Sheet with form fields: `name`, `slug`, `owner_email` (only on create), `status`.
   - Inertia `useForm`.
3. Create `partials/suspend-sheet.tsx`:
   - Confirmation dialog/sheet explaining implications of suspending an organization.
4. Create `show.tsx`:
   - Overview stats card (created date, member count, status badge).
   - Members table showing all associated users with their role in the tenant.
5. In `panel/resources/js/layouts/admin-layout.tsx`:
   - Add sidebar navigation item for "Tenants" with icon `Building2` linking to `admin.tenants.index`.

- [ ] **Step 4: Verify Frontend Build and Typecheck**

Run:
```bash
cd panel
npm run types
npm run test:lint
```
Expected: PASS with 0 TypeScript errors and 0 ESLint errors.

- [ ] **Step 5: Commit**

```bash
git add panel/resources/js/types/tenant.d.ts panel/resources/js/pages/admin/tenants/ panel/resources/js/layouts/admin-layout.tsx panel/lang/en.json
git commit -m "feat(admin-ui): create tenant management admin pages and navigation link"
```

---

### Task 10: Frontend Portal UI — Organization Settings and Tenant Switcher

**Files:**
- Create: `panel/resources/js/components/tenant-switcher.tsx`
- Create: `panel/resources/js/pages/portal/settings/organization/overview.tsx`
- Create: `panel/resources/js/pages/portal/settings/organization/members.tsx`
- Create: `panel/resources/js/pages/portal/settings/organization/partials/invite-member-dialog.tsx`
- Modify: `panel/resources/js/layouts/portal-layout.tsx`
- Modify: `panel/resources/js/pages/portal/settings/layout.tsx` (Add organization tab)
- Modify: `panel/lang/en.json`

**Interfaces:**
- Consumes: Wayfinder routes (`@/routes/portal/settings/organization`), Inertia `usePage().props.currentTenant`, `useLocalizer`.
- Produces: Header tenant switcher dropdown, Organization settings tabs, and Member invitation management.

- [ ] **Step 1: Implement TenantSwitcher Component**

Create `panel/resources/js/components/tenant-switcher.tsx`:
- Dropdown menu in top bar showing current active tenant name and icon.
- Lists all tenants user belongs to.
- Clicking a tenant posts to `portal.tenants.switch`.
- Checkmark on active tenant.
- Link or action to "Create Team" / "Organization Settings".

- [ ] **Step 2: Implement Organization Settings and Members Pages**

1. Create `panel/resources/js/pages/portal/settings/organization/overview.tsx`:
   - Form to edit Organization Name and Slug.
   - Shows creation date, current status.
   - Guarded: disabled inputs if user role is not `owner` or `admin`.
2. Create `panel/resources/js/pages/portal/settings/organization/members.tsx`:
   - Table of members with avatar, name, email, role badge (`Owner`, `Admin`, `Member`, `Viewer`).
   - "Invite Member" button opening `invite-member-dialog.tsx`.
   - Role change dropdown for owners/admins.
   - Remove member confirmation dialog (disabled with tooltip for the sole owner).
3. Create `partials/invite-member-dialog.tsx`:
   - Modal with Email and Role select.
   - Validation feedback.
4. Update `panel/resources/js/pages/portal/settings/layout.tsx`:
   - Add "Organization" navigation link alongside Profile, Password, Preferences.
5. Integrate `TenantSwitcher` into `panel/resources/js/layouts/portal-layout.tsx`.

- [ ] **Step 3: Verify Frontend Build and Lint**

Run:
```bash
cd panel
npm run types
npm run test:lint
```
Expected: PASS with 0 errors.

- [ ] **Step 4: Commit**

```bash
git add panel/resources/js/components/tenant-switcher.tsx panel/resources/js/pages/portal/settings/organization/ panel/resources/js/layouts/portal-layout.tsx panel/resources/js/pages/portal/settings/layout.tsx panel/lang/en.json
git commit -m "feat(portal-ui): add tenant switcher and organization profile and member settings"
```

---

### Task 11: End-to-End Tenant Isolation Audit and Database Seeders

**Files:**
- Create: `panel/database/seeders/TenantSeeder.php`
- Modify: `panel/database/seeders/DatabaseSeeder.php`
- Create: `panel/tests/Feature/TenantIsolationEndToEndTest.php`

**Interfaces:**
- Consumes: All Phase 1 models, middleware, policies, controllers.
- Produces: Robust automated integration test proving complete data isolation across organizations and realistic multi-tenant seed data.

- [ ] **Step 1: Write comprehensive End-to-End Tenant Isolation Test**

Create `panel/tests/Feature/TenantIsolationEndToEndTest.php`:
```php
<?php

declare(strict_types=1);

use App\Domain\Tenants\Models\Tenant;
use App\Enums\TenantRole;
use App\Enums\TenantStatus;
use App\Models\User;

test('end-to-end multi-tenant lifecycle and boundary enforcement', function (): void {
    // 1. Setup Tenant A and Tenant B
    $ownerA = User::factory()->create(['name' => 'Alice Owner', 'email' => 'alice@company-a.com']);
    $memberA = User::factory()->create(['name' => 'Adam Member', 'email' => 'adam@company-a.com']);
    $tenantA = Tenant::factory()->create(['name' => 'Company A', 'slug' => 'company-a']);
    $tenantA->users()->attach($ownerA->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::OWNER->value]);
    $tenantA->users()->attach($memberA->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::MEMBER->value]);

    $ownerB = User::factory()->create(['name' => 'Bob Owner', 'email' => 'bob@company-b.com']);
    $tenantB = Tenant::factory()->create(['name' => 'Company B', 'slug' => 'company-b']);
    $tenantB->users()->attach($ownerB->id, ['id' => (string) str()->uuid(), 'role' => TenantRole::OWNER->value]);

    // 2. Alice views Company A organization settings
    $this->actingAs($ownerA)->get(route('portal.settings.organization.overview'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('organization.id', $tenantA->id));

    // 3. Alice cannot modify Company B
    $this->actingAs($ownerA)->put(route('portal.settings.organization.update'), [
        'name' => 'Hacked Company B',
        'slug' => 'company-b',
    ])->assertSessionHasNoErrors();
    expect($tenantB->fresh()->name)->toBe('Company B');

    // 4. Bob cannot view Company A members
    $pivotA = $tenantA->memberships()->where('user_id', $memberA->id)->first();
    $this->actingAs($ownerB)->delete(route('portal.settings.organization.members.destroy', $pivotA))
        ->assertForbidden();
    expect($tenantA->users()->count())->toBe(2);

    // 5. Suspending Company A locks out both Alice and Adam
    $tenantA->update(['status' => TenantStatus::SUSPENDED]);
    $this->actingAs($ownerA)->get(route('portal.dashboard'))->assertForbidden();
    $this->actingAs($memberA)->get(route('portal.dashboard'))->assertForbidden();

    // 6. Company B remains completely accessible
    $this->actingAs($ownerB)->get(route('portal.dashboard'))->assertOk();
});
```

- [ ] **Step 2: Run test to verify it passes**

Run: `cd panel; php artisan test --filter=TenantIsolationEndToEndTest`
Expected: PASS

- [ ] **Step 3: Create Seeders for Development & Testing**

1. Create `panel/database/seeders/TenantSeeder.php`:
   - Seeds Demo Organization "Acme Streaming Studio" with:
     - Owner: `studio-owner@example.com`
     - Admin: `studio-admin@example.com`
     - Member: `studio-editor@example.com`
     - Viewer: `studio-viewer@example.com`
   - Seeds Secondary Organization "Nexus Media Labs" with isolated members.
2. Update `panel/database/seeders/DatabaseSeeder.php` to call `TenantSeeder`.

- [ ] **Step 4: Run full test suite and seeder verification**

Run:
```bash
cd panel
php artisan test
php artisan db:seed --class=TenantSeeder
```
Expected: All tests pass.

- [ ] **Step 5: Commit**

```bash
git add panel/database/seeders/TenantSeeder.php panel/database/seeders/DatabaseSeeder.php panel/tests/Feature/TenantIsolationEndToEndTest.php
git commit -m "test(tenants): add end-to-end multi-tenant isolation test and realistic seeders"
```
