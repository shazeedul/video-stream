# Development Rules

> **Purpose:** Mandatory rules for all contributors and AI assistants.
> **Stack:** Laravel 13 · Inertia v2 · React 19 · Wayfinder · Tailwind v4 · Pest v4 · MySQL. Follow the latest Laravel / PHP / React / Inertia conventions.

## 00. Engineering Standard — Think First, Build the Idiomatic Way (MANDATORY, NON-NEGOTIABLE)

> **This rule governs EVERY task.** The developer will NOT remind you to "optimize", "be DRY", "use a scope", or "follow Laravel conventions" on each request. Do it by default, every time, unasked. A copy-paste / non-idiomatic / unmaintainable solution is a failed task even if it "works".

**Before writing or editing any code, stop and decide the correct design.** Do not pattern-match on whatever the surrounding file already does — the existing code may itself be wrong. Ask, in order:

1. **Is this repeated?** If the same query/condition/string/permission/rule appears (or will appear) more than once — extract it. Never copy-paste a `where(...)`, a status check, a validation block, or a computed value into a second place. One source of truth.
2. **Where does this logic belong in Laravel?** Choose the idiomatic home before coding:
   - **Query scopes** (`scopeX`) for reusable query constraints (`->active()`, `->approved()`, `->forProject()`). Never inline the same `where()` across controllers.
   - **Accessors / Attribute casts** for derived reads (`is_active`, `full_name`, formatted money/dates); **mutators** for write normalisation. Never recompute in controllers or on the React side.
   - **Enum casts** for status columns — compare enum-to-enum, never enum-to-`->value` string.
   - **Observers** (`#[ObservedBy([...])]`) for model lifecycle side-effects, not duplicated controller code.
   - **Form Requests** for validation; **Policies** for authorization; **Service / Action classes** for business logic. Controllers stay slim.
   - **Traits / base classes** for behaviour shared across sibling models or controllers.
   - **Config / Enum / constant** for magic values — never hardcode strings or numbers inline.
3. **Is it correct AND maintainable?** Prefer the uniform, optimal refactor over a minimal patch that leaves duplication. Eager-load to kill N+1. Push filtering into the query, not PHP/JS collections. Design for SaaS scale — this app carries millions of rows across many tenants/users, so paginate, index, and constrain every query; never load unbounded sets into memory.
4. **Does a skill already define the pattern?** If a `SKILL.md` covers this domain, its documented pattern wins (see Section 0). Use it exactly.

**Apply the fix everywhere, not just the spot asked about.** If you find a duplicated/wrong pattern while doing a task, fix all occurrences in the same change and note it — never leave siblings inconsistent.

**When the right approach differs from existing code**, follow the idiomatic approach and (if non-trivial) say one line about why. Leaving bad code "to match the file" is not acceptable.

Treat "implement X" / "fix Y" / "change Z" as "do it **the idiomatic, DRY, maintainable, Laravel-convention way**". The developer should never have to spell that out.

## 01. Think First, Clarify Before You Build (MANDATORY)

**Think before you code. Never start typing on a guess.** For any request — a feature, a fix, a decision — reason through the design first.

**If you are not 100% confident, or anything is ambiguous, STOP and ask — do not assume.** Guessing wrong wastes a round-trip; one good question is cheaper than a wrong implementation.

- **Ask via the interview/question tool** (`AskUserQuestion`), not buried in prose. One tool call can carry multiple questions.
- **Every question must offer concrete answer options** — never an open "what do you want?". Give the real choices you're deciding between.
- **Mark your recommended option first and label it `(Recommended)`**, with a one-line reason, so the developer can decide at a glance.
- Ask when: requirements are vague, multiple valid approaches exist, the change is destructive/hard to reverse, business logic is unclear, or a decision has trade-offs the developer owns.
- Do **not** ask about things you can verify yourself (read the code, check the schema, run `search-docs`/`context7`). Ask only for genuine decisions that are the developer's to make.
- When you proceed without asking, it's because you *are* confident — state the assumption in one line so it can be corrected.

## 0. Skills-First Development (MANDATORY)

This project ships with domain-specific skills that contain authoritative, version-locked guidance. Each skill lives as a `SKILL.md` file inside its own folder under the project's skills directories (e.g., `.claude/skills/`, `.agents/skills/`, `.github/skills/`, `.cursor/skills/`). Skills may be added, removed, or updated at any time.

**You MUST discover and use all available skills.** Before starting any task, read the `SKILL.md` frontmatter (`name` and `description`) of every skill in the skills directory. Each skill's `description` and "When to use this skill" section tells you exactly when it applies. If your task overlaps with any skill's domain — activate it and follow its rules strictly.

### How to discover skills

- Skills live in the skills directories (e.g., `.claude/skills/*/SKILL.md`)
- Each `SKILL.md` has YAML frontmatter (`name`, `description`) and a "When to use this skill" section
- Scan these before every task — new skills may have been added since your last session

### Rules

1. **Activate early, not late.** Activate all relevant skills at the start of the task, not after you get stuck.
2. **Skills are the source of truth.** If a skill provides a pattern, API, convention, or command, use it exactly as documented — even if you "know" a different approach. Skills are tailored to this project's exact package versions.
3. **Activate every skill that applies.** A single task often spans multiple skills — adding an admin page may need React pages, styling, routing, translations, permissions, and testing skills at once.
4. **Every user-facing string must be localized.** If a localization skill exists and you write any text a user sees — labels, buttons, headings, flash/validation messages, placeholders — activate and follow it. No hardcoded UI strings.
5. **Every enum must use the enum skill.** If an enum skill exists and any PHP enum is involved (created, modified, cast, compared, displayed) — activate and follow it. No hardcoded enum string values.
6. **Every change must be tested.** If a testing skill exists, activate it so tests follow project conventions.
7. **Search docs before coding.** Use `search-docs` for version-specific Laravel/ecosystem documentation before implementing. Skills complement docs — use both.
8. **Do not duplicate skill content in this file.** If a domain is covered by a skill, that skill is the single reference.

## 1. Docs-First (MANDATORY)

Before using any library, framework, or SDK feature, fetch **current** documentation — never code integrations from training-data memory.

- Laravel & installed PHP/JS packages → `search-docs` (Laravel Boost, version-aware) first.
- Anything else (React, Inertia, Wayfinder, Tailwind, external SDKs/APIs) → `context7`:
  ```bash
  npx ctx7@latest library "<name>" "<question>"   # resolve the /org/project id
  npx ctx7@latest docs <libraryId> "<question>"   # fetch version-specific docs
  ```
- Verify APIs, signatures, and config against docs even for well-known libraries — training data may be stale.
- When you pull fresh docs for a recurring concept, save the distilled result to project memory so the next session starts current.

## 2. UI Patterns

- Match existing admin UI: design, filters, tables, sheets, pagination
- Reference patterns from `resources/js/pages/admin/system/roles/*`
- File naming: lowercase with dashes (`privacy-policy.tsx`)
- Reuse existing components before writing new ones; check for a shared component first

## 3. Permissions

```php
// Source of truth: app/Support/Authorization/RolePermissions.php
// Format: module.resource.action
```

1. Check if the permission exists in `RolePermissions.php`
2. If missing, create it with the same naming style
3. Attach permissions at **route level only** (not in controllers)
4. Gate the frontend with `<Can permission="">`
5. Permission strings must match between routes and UI

```tsx
import { Can } from '@devwizard/laravel-react-permissions';
<Can permission="students.create">
    <Button>Add</Button>
</Can>;
```

## 4. Localization (JSON Only)

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
- Request/validation messages must be localized too

## 5. Routes (Wayfinder Only)

```tsx
// ✅ CORRECT - Named routes
import { index, show } from '@/routes/admin/system/roles';
<Link href={index().url}>Roles</Link>;

// ✅ CORRECT - Controller actions (named imports, tree-shakable)
import { store } from '@/actions/App/Http/Controllers/Admin/System/RoleController';

// ❌ WRONG - Never use Ziggy
import { route } from 'ziggy-js';
```

- Always name backend routes; reference them through Wayfinder-generated functions
- Run `wayfinder:generate` after adding/changing routes

## 6. Layout Consistency

- Admin and user sections must use the existing layout wrappers and page containers
- Match spacing and component usage from the system users/roles screens

## 7. End-to-End Feature Shape

- Implement controller, service/action, and Form Request validation on the backend
- Backend routes with permissions attached at route level
- Frontend uses `<Can>` and localization for all UI
- Validation messages localized

## 8. Package Manager

- Use `pnpm` exclusively (not npm): `pnpm install`, `pnpm run build`, `pnpm run dev`

## 9. Code Style

- Run `vendor/bin/pint --dirty --format agent` before finalizing any PHP change
- PHP 8 constructor property promotion
- Explicit return type declarations and typed parameters
- Prefer Eloquent over raw queries
- Curly braces for all control structures, even single-line bodies

## 10. Database

- MySQL (not PostgreSQL)
- Use migrations for all schema changes
- Use factories and seeders for test data
- Update the model's `$fillable` when adding columns

## 11. Forms

- Use the Inertia `<Form>` component or `useForm` helper
- Create Form Request classes for validation
- Check sibling requests for array vs string rule conventions

## 12. Controllers

- Create with `php artisan make:controller --no-interaction`
- Validation in Form Request classes; use `$request->validated()`
- Return Inertia responses (or JSON where the endpoint is an API)
- Single-method controllers use `__invoke()`; multi-method RESTful controllers use `Route::resource()->only([])`
- Keep controllers slim — push business logic into Service/Action classes

## 13. Import Order (Auto-Remove Protection)

When editing React/PHP files with auto-remove-unused-imports on save:

1. **Use the component/class FIRST** in your code
2. **Import it SECOND** at the top of the file

```tsx
// Step 1: use it in JSX
<div><MyComponent /></div>;
// Step 2: then import at the top
import MyComponent from './MyComponent';
```

**Why:** importing first makes auto-remove treat it as unused and delete it on save.

## 14. Comments & Dead Code (CRITICAL)

- **No comments above obvious code.** Add a comment only when the *why* is non-obvious (hidden constraint, workaround, non-obvious invariant). Don't restate what the code already says.
- **No docblocks as decoration.** Only add one where the *why* genuinely isn't clear from name + types (a non-obvious contract, edge case, external constraint). A docblock on every method/class/DTO buries the signal.
- **No docblock comments for variable declarations** unless instructed (e.g. `/** @var \App\Models\User $user */`).
- **No dead code.** Delete unused imports, variables, functions, methods, fields, and commented-out blocks — never "just in case". Git history is the backup.
- **No unreachable or unused branches** — no `if (false)`, no scaffolding for a flag that doesn't exist, no `// eslint-disable` to silence a real issue instead of fixing it.
- Don't add comments, wrappers, or config just to look thorough. Every line must earn its place by making the code more correct or maintainable.

## 15. PHP Instructions

- Use `match` over `switch` whenever possible
- PHP 8 constructor property promotion; no empty zero-parameter `__construct()`
- Return types on all functions; import classes with `use` and reference short names — no fully-qualified names inline
- Generate Enums in `app/Enums` (or a domain subdirectory), never loose in `app/`
- If a PHP Enum exists for a concept, use its cases everywhere instead of raw strings
- Don't create a temp variable used only once (avoid `$user = auth()->user()` if used once)
- Prefer PHPDoc array-shape types over inline comments for complex arrays

## 16. Laravel Instructions

- **Services in controllers:** used in ONE method → inject into that method; used in MULTIPLE → inject via constructor
- Pivot tables use alphabetical order (`project_role`, not `role_project`); add `withTimestamps()` on `BelongsToMany` when the pivot has `timestamps()`
- Register Eloquent Observers via attribute: `#[ObservedBy([UserObserver::class])]`
- Use Laravel helpers over facades (`auth()->id()`, not `Auth::id()`)
- Don't use `whereKey()`/`whereKeyNot()` — use explicit fields like `id`
- Don't add `::query()` to `create()` (`User::create()`, not `User::query()->create()`)
- Don't create a one-method controller that only returns a view — use `Route::view()` (or an Inertia render) directly
- Use `php artisan make:` for all new files (migrations, models, controllers, requests, etc.) with `--no-interaction`
- Create useful factories and seeders alongside new models

## 17. Testing

- Use Pest (not PHPUnit). Create tests with `php artisan make:test --pest {name}`
- Every change must be programmatically tested; new features MUST ship Pest tests
- Run in **parallel** with a compact report and a filter: `php artisan test -p --compact --filter=TestName`
- Run the minimum set needed for speed
- Prefer `Illuminate\Foundation\Testing\LazilyRefreshDatabase` over `RefreshDatabase` — only resets the DB when a test actually mutates it
- If regenerating a test-DB schema snapshot with `schema:dump`, **NEVER pass `--prune`** — it deletes the migration files
- Structure every test **Arrange–Act–Assert**

**Arrange:** use factories, but give meaningful values/names when they aid debugging.
- Bad: `$user1 = User::factory()->create();`
- Better: `$adminUser = User::factory()->create(['email' => 'admin@admin.com']);`

**Assert (as applicable):**
- Status: `assertStatus()`, `assertSuccessful()`, `assertForbidden()`, `assertNotFound()`
- Data: `assertInertia()`, `assertSee()`, `assertDontSee()`, `assertJsonContains()`
- Redirects/flash: `assertRedirect()`, `assertSessionHas()`
- DB: `assertDatabaseHas()`, `assertDatabaseMissing()`, `expect($x)->toBe()`

## 18. Task Tracking & Memory (MANDATORY)

- **Keep the todo list current.** The moment a task starts, break it into tracked todos. Any feedback/correction/new instruction mid-task becomes a todo immediately — don't hold it only in your head.
- Write each todo with **enough standalone context to act on after a context compaction** — what to do, which file/area, and *why* (the user's actual reason) — never a bare label like "fix bug".
- Mark items done as soon as they're done; don't batch.
- Anything that should persist **beyond this session** (a standing rule, a preference, a fact about ongoing work) goes to **project memory**, not just the todo list — todos die with the session.

## 19. Git & Commits (STRICT)

- **Never commit unless the developer explicitly asks.**
- **Never mention any AI tool** in commit messages, PR titles, or PR bodies — no Claude, Anthropic, Copilot, Gemini, ChatGPT, or any other reference. No exceptions, regardless of generic tooling defaults.
- **Never add a `Co-Authored-By` trailer** of any kind. Commits must read as written by the developer alone.
- **Never bypass `.gitignore`.** If a path is ignored, do NOT force-add it (`git add -f`/`--force`, `git update-index --add --force`). If it seems needed in VCS, stop and ask — the default is "leave it untracked".
- **Never commit planning/debug docs** — `docs/todo/`, `docs/superpowers/`, `docs/debug/`, `.superpowers/`, `**/PLAN*.md`, `**/TODO*.md`, `**/DEBUG*.md`, `**/BRAINSTORM*.md`. Local only; these are gitignored at repo root — exclude silently and note the exclusion.
- **Never disable hooks or signing** — no `--no-verify`, `--no-gpg-sign`, `-c commit.gpgsign=false`. If a hook fails, fix the cause and make a new commit.
- **Never amend, force-push, `reset --hard`, or rewrite history** without an explicit per-operation instruction from the developer.
- Commit message format: `<type>: <description>` — types: `feat`, `fix`, `refactor`, `docs`, `test`, `chore`, `perf`, `ci`.
