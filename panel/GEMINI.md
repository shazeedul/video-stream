<laravel-boost-guidelines>
=== .ai/RULES rules ===

# SMS-ERP Development Rules

> **Purpose:** Mandatory rules for all contributors and AI assistants.

## 0. Skills-First Development (MANDATORY)

This project ships with domain-specific skills that contain authoritative, version-locked guidance. Each skill lives as a `SKILL.md` file inside its own folder under the project's skills directories (e.g., `.claude/skills/`, `.agents/skills/`, `.github/skills/`, `.cursor/skills/`). Skills may be added, removed, or updated at any time as new packages are installed or conventions change.

**You MUST discover and use all available skills.** Before starting any task, read the `SKILL.md` frontmatter (`name` and `description` fields) of every skill in the skills directory to understand what is available. Each skill's `description` and "When to use this skill" section tells you exactly when it applies. If your task overlaps with any skill's domain — activate it and follow its rules strictly.

### How to discover skills

- Skills are located in the skills directories (e.g., `.claude/skills/*/SKILL.md`)
- Each `SKILL.md` has a YAML frontmatter with `name` and `description`, and a "When to use this skill" section listing exact trigger conditions
- Scan these before every task — new skills may have been added since your last session

### Rules

1. **Activate early, not late.** Activate all relevant skills at the start of the task, not after you get stuck. Read the skill's "When to use this skill" section and match it against what you are about to do.
2. **Skills are the source of truth.** If a skill provides a specific pattern, API, convention, or command, use it exactly as documented — even if you "know" a different approach. The skills are tailored to this project's exact package versions and architecture.
3. **Activate every skill that applies.** A single task often spans multiple skills. For example, adding a new admin page may require skills for React pages, styling, routing, translations, permissions, and testing — activate all of them simultaneously.
4. **Every user-facing string must be localized.** If a localization skill exists and you are writing any text that a user will see — labels, buttons, headings, flash messages, validation messages, placeholders — you must activate and follow that skill. No hardcoded UI strings.
5. **Every enum must use the enum skill.** If an enum skill exists and any PHP enum is involved — created, modified, cast, compared, or displayed — you must activate and follow that skill. No hardcoded enum string values.
6. **Every change must be tested.** If a testing skill exists, activate it to ensure tests follow project conventions.
7. **Search docs before coding.** Use `search-docs` for version-specific documentation of Laravel and all ecosystem packages before implementing. Skills complement docs — use both.
8. **Do not duplicate skill content in this file.** If a domain is covered by a skill, that skill is the single reference. This rules file only contains project-wide conventions that are not covered by any skill.

## 1. UI Patterns

- Match existing admin UI: design, filters, tables, sheets, pagination
- Reference patterns from `resources/js/pages/admin/system/roles/*`
- File naming: lowercase with dashes (`privacy-policy.tsx`)

## 2. Permissions

```php
// Source of truth: app/Support/Authorization/RolePermissions.php
// Format: module.resource.action
```

1. Check if permission exists in `RolePermissions.php`
2. If missing, create with same naming style
3. Attach permissions at **route level only** (not in controllers)
4. Use `<Can permission="">` for frontend gating
5. Permission strings must match between routes and UI

```tsx
import { Can } from '@devwizard/laravel-react-permissions';
<Can permission="students.create">
    <Button>Add</Button>
</Can>;
```

## 3. Localization (JSON Only)

```tsx
import { useLocalizer } from '@devwizard/laravel-localizer-react';
const { __ } = useLocalizer();

// ✅ CORRECT
<h1>{__('Dashboard')}</h1>

// ❌ WRONG - No dot notation
<h1>{__('admin.dashboard')}</h1>
```

- Files: `lang/en.json`, `lang/bn.json`
- NO PHP files for UI strings
- NO dot notation in keys
- NO hardcoded UI strings in templates, components, or JS files

## 4. Routes (Wayfinder Only)

```tsx
// ✅ CORRECT - Named routes
import { index, show } from '@/routes/admin/system/roles';
<Link href={index().url}>Roles</Link>;

// ✅ CORRECT - Controller routes (named imports, tree-shakable)
import { store } from '@/actions/App/Http/Controllers/Admin/System/RoleController';

// ❌ WRONG - Never use Ziggy
import { route } from 'ziggy-js';
```

## 5. Layout Consistency

- Admin and user sections must use existing layout wrappers and page containers
- Match spacing and component usage from system users/roles screens

## 6. Theme Management (End-to-End)

- Implement controller, service, and request validation
- Backend routes with permissions at route level
- Frontend uses `<Can>` and localization for all UI
- Request validation messages must be localized

## 7. Package Manager

- Use `pnpm` exclusively (not npm)
- `pnpm install`, `pnpm run build`, `pnpm run dev`

## 8. Testing

- Every change must be tested
- Use Pest: `php artisan test --filter=TestName`
- Run minimum tests needed

## 9. Code Style

- Run `vendor/bin/pint --dirty` before commits
- Use PHP 8 constructor property promotion
- Explicit return type declarations
- Prefer Eloquent over raw queries

## 10. Database

- MySQL (not PostgreSQL)
- Use migrations for all schema changes
- Use factories and seeders for test data

## 11. Forms

- Use Inertia `<Form>` component or `useForm` helper
- Create Form Request classes for validation
- Check sibling requests for array vs string rules

## 12. Controllers

- Use `php artisan make:controller --no-interaction`
- Validation in Form Request classes
- Return Inertia responses

## 13. Import Order (Auto-Remove Protection)

When working with React and PHP files that have auto-remove unused imports on save:

1. **Use the component/class FIRST** in your code
2. **Import it SECOND** at the top of the file

```tsx
// Step 1: Use the component in JSX first
<div>
    <MyComponent />
</div>;

// Step 2: Then add the import at the top
import MyComponent from './MyComponent';
```

**Why:** If you import first, auto-remove sees it as "unused" and removes it on save.

## 14. General Code Instructions

- Don't generate code comments above methods or code blocks if they are obvious
- Don't add docblock comments when defining variables, unless instructed (e.g., `/** @var \App\Models\User $currentUser */`)
- Generate comments only for something that needs extra explanation for the reasons why that code was written
- For new features, you MUST generate Pest automated tests

## 15. PHP Instructions

- Use `match` operator over `switch` whenever possible
- Use PHP 8 constructor property promotion; don't create an empty Constructor method if it doesn't have any parameters
- Use return types in functions whenever possible, adding the full path to classname in the `use` section
- Generate Enums always in the folder `app/Enums`, not in the main `app/` folder
- Import all classes with `use` and reference only their short names; no fully-qualified class names in code
- Don't create temporary variables if that variable is used only one time (e.g., avoid `$currentUser = auth()->user()` if used once)

## 16. Laravel Instructions

- **Services in Controllers:** If Service class is used only in ONE method of Controller, inject it directly into that method. If used in MULTIPLE methods, initialize it in Constructor
- For DB pivot tables, use correct alphabetical order (e.g., `project_role` instead of `role_project`)
- **Eloquent Observers** should be registered in Models with PHP Attributes: `#[ObservedBy([UserObserver::class])]`
- When generating Controllers, put validation in Form Request classes and use `$request->validated()`
- Aim for "slim" Controllers and put larger logic pieces in Service classes
- Use Laravel helpers instead of `use` section classes (e.g., `auth()->id()` instead of `Auth::id()`)
- Don't use `whereKey()` or `whereKeyNot()`, use specific fields like `id`
- Don't add `::query()` when running Eloquent `create()` statements (use `User::create()`, not `User::query()->create()`)
- When creating pivot tables with `timestamps()`, add `withTimestamps()` to `BelongsToMany` relationships
- When adding columns in a migration, update the model's `$fillable` array
- **Enums:** If a PHP Enum exists for a domain concept, always use its cases instead of raw strings everywhere
- **Controllers:** Single-method Controllers should use `__invoke()`; multi-method RESTful controllers should use `Route::resource()->only([])`
- Don't create Controllers with just one method that returns `view()`. Instead, use `Route::view()` with Blade file directly

## 17. Testing Instructions

Use Pest and not PHPUnit. Run tests with `php artisan test`.

Every test method should be structured with **Arrange-Act-Assert**.

**Arrange phase:**

- Use Laravel factories but add meaningful column values and variable names if they help understand failed tests better
- Bad example: `$user1 = User::factory()->create();`
- Better example: `$adminUser = User::factory()->create(['email' => 'admin@admin.com'])`;

**Assert phase (when applicable):**

- HTTP status code: `assertStatus()`, `assertSuccessful()`, `assertForbidden()`, `assertNotFound()`
- Structure/data returned: `assertViewHas()`, `assertSee()`, `assertDontSee()`, `assertJsonContains()`
- Redirect assertions: `assertRedirect()` and `assertSessionHas()` for Flash session values
- DB changes: `assertDatabaseHas()`, `assertDatabaseMissing()`, `expect($variable)->toBe()`

=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4
- inertiajs/inertia-laravel (INERTIA_LARAVEL) - v2
- laravel/cashier (CASHIER) - v16
- laravel/framework (LARAVEL) - v12
- laravel/horizon (HORIZON) - v5
- laravel/octane (OCTANE) - v2
- laravel/prompts (PROMPTS) - v0
- laravel/reverb (REVERB) - v1
- laravel/sanctum (SANCTUM) - v4
- laravel/scout (SCOUT) - v10
- laravel/socialite (SOCIALITE) - v5
- laravel/wayfinder (WAYFINDER) - v0
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- rector/rector (RECTOR) - v2
- \@inertiajs/react (INERTIA_REACT) - v2
- react (REACT) - v19
- tailwindcss (TAILWINDCSS) - v4
- \@laravel/echo-react (ECHO_REACT) - v2
- \@laravel/vite-plugin-wayfinder (WAYFINDER_VITE) - v0
- eslint (ESLINT) - v9
- laravel-echo (ECHO) - v2
- prettier (PRETTIER) - v3

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `pnpm run build`, `pnpm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Follow existing application Enum naming conventions.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== herd rules ===

# Laravel Herd

- The application is served by Laravel Herd at `https?://[kebab-case-project-dir].test`. Use the `get-absolute-url` tool to generate valid URLs. Never run commands to serve the site. It is always available.
- Use the `herd` CLI to manage services, PHP versions, and sites (e.g. `herd sites`, `herd services:start <service>`, `herd php:list`). Run `herd list` to discover all available commands.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-react-development` when working with Inertia client-side patterns.

# Inertia v2

- Use all Inertia features from v1 and v2. Check the documentation before making changes to ensure the correct approach.
- New features: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `pnpm run build` or ask the user to run `pnpm run dev` or `composer run dev`.

=== laravel/v12 rules ===

# Laravel 12

- CRITICAL: ALWAYS use `search-docs` tool for version-specific Laravel documentation and updated code examples.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

## Laravel 12 Structure

- In Laravel 12, middleware are no longer registered in `app/Http/Kernel.php`.
- Middleware are configured declaratively in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- The `app/Console/Kernel.php` file no longer exists; use `bootstrap/app.php` or `routes/console.php` for console configuration.
- Console commands in `app/Console/Commands/` are automatically available and do not require manual registration.

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== octane/core rules ===

# Octane

- Octane boots the application once and reuses it across requests, so singletons persist between requests.
- The Laravel container's `scoped` method may be used as a safe alternative to `singleton`.
- Never inject the container, request, or config repository into a singleton's constructor; use a resolver closure or `bind()` instead:

```php
// Bad
$this->app->singleton(Service::class, fn (Application $app) => new Service($app['request']));

// Good
$this->app->singleton(Service::class, fn () => new Service(fn () => request()));
```

- Never append to static properties, as they accumulate in memory across requests.

=== wayfinder/core rules ===

# Laravel Wayfinder

Use Wayfinder to generate TypeScript functions for Laravel routes. Import from `@/actions/` (controllers) or `@/routes/` (named routes).

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- The `{name}` argument should not include the test suite directory. Use `php artisan make:test --pest SomeFeatureTest` instead of `php artisan make:test --pest Feature/SomeFeatureTest`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

=== inertia-react/core rules ===

# Inertia + React

- IMPORTANT: Activate `inertia-react-development` when working with Inertia React client-side patterns.

</laravel-boost-guidelines>
