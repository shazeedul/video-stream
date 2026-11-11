<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Process;
use ReflectionClass;
use Throwable;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\note;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

/**
 * One-time scaffolding tool: removes the portal panel from this multi-panel
 * starter kit, leaving admin as the single panel. Since a single-panel app
 * has no other panel to gate access between, this also removes the
 * AdminPanelAccess/DefaultPanelAccess middleware pair and the
 * 'admin.panel.view' permission entirely — Roles/Users/System Settings stay
 * protected by their own resource-specific `permission:x.y` middleware.
 * Intended to be run once on a fresh clone, then optionally deleted (see the
 * self-destruct prompt at the end).
 */
#[\Illuminate\Console\Attributes\Description('Remove the portal panel from this starter kit, leaving admin as the single panel')]
#[\Illuminate\Console\Attributes\Signature('panel:strip
        {--force : Skip confirmation prompts}')]
final class StripPanelCommand extends Command
{
    /** @var array<int, string> */
    private array $warnings = [];

    public function __construct(private readonly Filesystem $files)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        intro('Panel Strip — remove the portal panel to create a single-panel (admin) app');

        if (app()->environment('production')) {
            error('Refusing to run in production. This is a one-time scaffolding tool for local/dev use.');

            return self::FAILURE;
        }

        if (! $this->files->exists(base_path('routes/portal.php'))) {
            info('Portal panel is already gone — this project is already single-panel. Nothing to strip.');

            $this->maybeSelfDestruct();

            return self::SUCCESS;
        }

        if (! $this->option('force') && $this->hasUncommittedChanges()) {
            warning('Uncommitted git changes detected. Commit or stash first so this is easy to undo.');

            if (! confirm('Continue anyway?', default: false)) {
                info('Aborted.');

                return self::SUCCESS;
            }
        }

        if (! $this->option('force') && ! confirm(
            label: 'This permanently deletes the portal panel, the panel-access middleware pair, and the admin.panel.view permission. Continue?',
            default: false,
        )) {
            info('Aborted.');

            return self::SUCCESS;
        }

        try {
            $deleted = $this->deletePortalFiles();
            $edited = $this->rewriteSharedFiles();
        } catch (Throwable $throwable) {
            error('Strip failed partway through: '.$throwable->getMessage());
            error('The working tree may be partially modified — check `git status` / `git diff` before continuing.');

            return self::FAILURE;
        }

        $this->tidyUp();

        table(['Deleted'], array_map(fn (string $path): array => [$path], $deleted));
        table(['Edited'], array_map(fn (string $path): array => [$path], $edited));

        note('Single-panel (admin) strip complete.');

        $this->printFollowUps();

        $this->maybeSelfDestruct();

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function deletePortalFiles(): array
    {
        $deleted = [];

        $paths = [
            'routes/portal.php',
            'app/Http/Controllers/Portal',
            'resources/js/pages/portal',
            'resources/js/layouts/portal-layout.tsx',
            'resources/js/components/portal',
            'resources/js/config/usePortalNavigation.ts',
            'resources/js/hooks/use-portal-notifications.ts',
            'resources/js/routes/portal',
            'resources/js/actions/App/Http/Controllers/Portal',
            'tests/Feature/Portal',
            'app/Http/Middleware/DefaultPanelAccess.php',
            // A single panel doesn't need a panel-access gate at all — Roles,
            // Users, and System Settings each already carry their own
            // permission:x.y middleware, independent of this one.
            'app/Http/Middleware/AdminPanelAccess.php',
        ];

        foreach ($paths as $relative) {
            $full = base_path($relative);

            if ($this->files->isDirectory($full)) {
                $this->files->deleteDirectory($full);
                $deleted[] = $relative.'/';
            } elseif ($this->files->exists($full)) {
                $this->files->delete($full);
                $deleted[] = $relative;
            }
        }

        return $deleted;
    }

    /**
     * @return array<int, string>
     */
    private function rewriteSharedFiles(): array
    {
        $edited = [];

        if ($this->stripBootstrapRegistration()) {
            $edited[] = 'bootstrap/app.php';
        }

        if ($this->stripAdminPanelMiddlewareFromRoutes()) {
            $edited[] = 'routes/admin.php';
        }

        if ($this->trimAdminPanelAccessPermission()) {
            $edited[] = 'app/Support/Authorization/RolePermissions.php';
        }

        array_push($edited, ...$this->fixDashboardRedirectHardcodes());

        if ($this->fixMiddlewareTest()) {
            $edited[] = 'tests/Feature/Middleware/MiddlewareTest.php';
        }

        array_push($edited, ...$this->fixAdminPanelDenialTests());

        return $edited;
    }

    /**
     * Removes both AdminPanelAccess and DefaultPanelAccess: their imports,
     * their middleware aliases, and the portal route group registration
     * (admin's own registration line survives).
     */
    private function stripBootstrapRegistration(): bool
    {
        $path = base_path('bootstrap/app.php');
        $contents = $this->files->get($path);
        $original = $contents;

        $contents = str_replace("use App\\Http\\Middleware\\AdminPanelAccess;\n", '', $contents);
        $contents = str_replace("use App\\Http\\Middleware\\DefaultPanelAccess;\n", '', $contents);
        $contents = str_replace("            'admin.panel' => AdminPanelAccess::class,\n", '', $contents);
        $contents = str_replace("            'default.panel' => DefaultPanelAccess::class,\n", '', $contents);

        $both = "            Route::middleware('web')\n                ->group(base_path('routes/admin.php'));\n\n".
            "            Route::middleware('web')\n                ->group(base_path('routes/portal.php'));\n";
        $adminOnly = "            Route::middleware('web')\n                ->group(base_path('routes/admin.php'));\n";

        $contents = str_replace($both, $adminOnly, $contents);

        if ($contents === $original) {
            $this->warnings[] = 'Could not automatically update bootstrap/app.php — expected patterns not found. Remove the portal route registration, both middleware aliases, and both imports manually.';

            return false;
        }

        $this->files->put($path, $contents);

        return true;
    }

    private function stripAdminPanelMiddlewareFromRoutes(): bool
    {
        return $this->replaceOrWarn(
            'routes/admin.php',
            "->middleware(['auth', 'verified', 'admin.panel'])",
            "->middleware(['auth', 'verified'])",
            "the admin.panel middleware in routes/admin.php's route group",
        );
    }

    /**
     * Removes just the 'Admin Panel Access' permission group — Users, Roles,
     * System Settings, DevTools, and Settings & Profile all stay, since
     * admin survives with all of its own features intact.
     */
    private function trimAdminPanelAccessPermission(): bool
    {
        $path = app_path('Support/Authorization/RolePermissions.php');
        $contents = $this->files->get($path);

        $start = mb_strpos($contents, '            // Admin Panel Access');
        $end = mb_strpos($contents, "            'Users' => [");

        if ($start === false || $end === false || $end <= $start) {
            $this->warnings[] = 'Could not automatically remove the "Admin Panel Access" permission group from RolePermissions.php — remove it manually, then run `php artisan auth:permissions-sync`.';

            return false;
        }

        $this->files->put($path, mb_substr($contents, 0, $start).mb_substr($contents, $end));

        return true;
    }

    /**
     * RegisteredUserController and helpers.php's homeRoute() hardcode
     * 'portal.dashboard' as the post-registration/login redirect target.
     *
     * @return array<int, string>
     */
    private function fixDashboardRedirectHardcodes(): array
    {
        $edited = [];

        if ($this->replaceOrWarn(
            'app/Http/Controllers/Auth/RegisteredUserController.php',
            "route('portal.dashboard', absolute: false)",
            "route('admin.dashboard', absolute: false)",
            'the dashboard redirect in RegisteredUserController.php',
        )) {
            $edited[] = 'app/Http/Controllers/Auth/RegisteredUserController.php';
        }

        if ($this->replaceOrWarn(
            'tests/Feature/Auth/RegisteredUserControllerTest.php',
            "route('portal.dashboard')",
            "route('admin.dashboard')",
            'the expected redirect in RegisteredUserControllerTest.php',
        )) {
            $edited[] = 'tests/Feature/Auth/RegisteredUserControllerTest.php';
        }

        // helpers.php's homeRoute() checks a stale 'admin_panel_access'
        // permission name that never matches the real 'admin.panel.view', so
        // it always falls through to its final `return route('portal.dashboard')`
        // — used by 5 shared Auth controllers (login, email verification,
        // social login, password confirmation) as their post-action redirect.
        if ($this->replaceOrWarn(
            'app/Helpers/helpers.php',
            "return route('portal.dashboard');",
            "return route('admin.dashboard');",
            'the homeRoute() fallback in app/Helpers/helpers.php',
        )) {
            $edited[] = 'app/Helpers/helpers.php';
        }

        return $edited;
    }

    /**
     * tests/Feature/Middleware/MiddlewareTest.php exercises both middleware
     * classes directly via synthetic routes in its beforeEach — both are now
     * gone, so both "X Tests" sections, their route registrations, and their
     * imports are removed.
     */
    private function fixMiddlewareTest(): bool
    {
        $path = base_path('tests/Feature/Middleware/MiddlewareTest.php');

        if (! $this->files->exists($path)) {
            return false;
        }

        $contents = $this->files->get($path);
        $original = $contents;

        foreach (['AdminPanelAccess', 'DefaultPanelAccess'] as $middlewareClass) {
            $contents = str_replace("use App\\Http\\Middleware\\{$middlewareClass};\n", '', $contents);
        }

        foreach (['/test-admin-access' => 'AdminPanelAccess', '/test-default-access' => 'DefaultPanelAccess'] as $routeUri => $middlewareClass) {
            $contents = str_replace(
                "\n    Route::get('{$routeUri}', function () {\n        return response('ok');\n    })->middleware({$middlewareClass}::class);\n",
                '',
                $contents,
            );
        }

        foreach (['AdminPanelAccess Tests', 'DefaultPanelAccess Tests'] as $sectionHeading) {
            $sectionPos = mb_strpos($contents, '| '.$sectionHeading);

            if ($sectionPos === false) {
                continue;
            }

            $blockStart = mb_strrpos(mb_substr($contents, 0, $sectionPos), '/*');
            $blockStart = $blockStart === false ? $sectionPos : $blockStart;

            $nextBlock = mb_strpos($contents, '/*', $blockStart + 2);
            $contents = $nextBlock === false
                ? mb_rtrim(mb_substr($contents, 0, $blockStart))."\n"
                : mb_substr($contents, 0, $blockStart).mb_substr($contents, $nextBlock);
        }

        if ($contents === $original) {
            $this->warnings[] = 'Could not automatically trim tests/Feature/Middleware/MiddlewareTest.php — check it manually, it will fail.';

            return false;
        }

        $this->files->put($path, $contents);

        return true;
    }

    /**
     * Dashboard and GlobalSearch had no permission middleware of their own —
     * they were only ever protected by the now-deleted outer gate, so their
     * "denies access to unauthorized users" tests no longer hold: any
     * authenticated, verified user can reach them. Roles/Users/System
     * Settings keep their own permission:x.y middleware regardless, so their
     * denial tests still hold — just via a 403 from Spatie's middleware
     * instead of a redirect from the deleted gate.
     *
     * @return array<int, string>
     */
    private function fixAdminPanelDenialTests(): array
    {
        $edited = [];

        foreach ([
            'tests/Feature/Admin/DashboardControllerTest.php' => "it('denies dashboard access for unauthorized users'",
            'tests/Feature/Admin/GlobalSearchControllerTest.php' => "it('denies search access for unauthorized users'",
        ] as $relative => $marker) {
            $path = base_path($relative);

            if (! $this->files->exists($path)) {
                continue;
            }

            $withoutTest = $this->removeItBlock($this->files->get($path), $marker);

            if ($withoutTest === null) {
                $this->warnings[] = sprintf('Could not remove the now-invalid access-denial test from %s — check it manually, it will fail (dashboard/search have no permission gate left).', $relative);

                continue;
            }

            $this->files->put($path, $withoutTest);
            $edited[] = $relative;
        }

        foreach ([
            'tests/Feature/Admin/RoleControllerTest.php',
            'tests/Feature/Admin/UserControllerTest.php',
            'tests/Feature/Admin/SystemSettingControllerTest.php',
        ] as $relative) {
            if ($this->fixDenialAssertions($relative)) {
                $edited[] = $relative;
            }
        }

        return $edited;
    }

    /**
     * The "// Portal users are redirected by admin.panel middleware" comment
     * marks each denial test, but its distance from the `->assertRedirect();`
     * it precedes varies (some chain straight into it, some have a
     * multi-line request payload in between) — so each occurrence is
     * resolved independently: flip the *next* assertRedirect() after each
     * comment, not a single fixed-distance pattern.
     */
    private function fixDenialAssertions(string $relative): bool
    {
        $path = base_path($relative);

        if (! $this->files->exists($path)) {
            return false;
        }

        $contents = $this->files->get($path);
        $marker = '// Portal users are redirected by admin.panel middleware';
        $replacementComment = '// Portal users lack the resource-specific permission';
        $oldAssertion = '->assertRedirect();';
        $newAssertion = '->assertForbidden();';

        $changed = false;
        $searchFrom = 0;

        while (($markerPos = mb_strpos($contents, $marker, $searchFrom)) !== false) {
            $contents = substr_replace($contents, $replacementComment, $markerPos, mb_strlen($marker));
            $searchFrom = $markerPos + mb_strlen($replacementComment);

            $assertPos = mb_strpos($contents, $oldAssertion, $searchFrom);

            if ($assertPos !== false) {
                $contents = substr_replace($contents, $newAssertion, $assertPos, mb_strlen($oldAssertion));
                $changed = true;
            }
        }

        if (! $changed) {
            $this->warnings[] = sprintf('Could not automatically update the permission-denial assertions in %s — check manually.', $relative);

            return false;
        }

        $this->files->put($path, $contents);

        return true;
    }

    /**
     * Removes a Pest `it('...', function () { ... });` block starting at
     * $startMarker, up to and including the first `});` that follows it.
     */
    private function removeItBlock(string $contents, string $startMarker): ?string
    {
        $start = mb_strpos($contents, $startMarker);

        if ($start === false) {
            return null;
        }

        $end = mb_strpos($contents, "});\n", $start);

        if ($end === false) {
            return null;
        }

        $end += mb_strlen("});\n");

        return mb_substr($contents, 0, $start).mb_substr($contents, $end);
    }

    private function replaceOrWarn(string $relative, string $search, string $replace, string $label): bool
    {
        $path = base_path($relative);

        if (! $this->files->exists($path)) {
            return false;
        }

        $contents = $this->files->get($path);
        $updated = str_replace($search, $replace, $contents);

        if ($updated === $contents) {
            $this->warnings[] = sprintf('Could not automatically update %s — pattern not found. Check manually.', $label);

            return false;
        }

        $this->files->put($path, $updated);

        return true;
    }

    private function hasUncommittedChanges(): bool
    {
        if (! $this->files->isDirectory(base_path('.git'))) {
            return false;
        }

        $result = Process::path(base_path())->run('git status --porcelain');

        return $result->successful() && mb_trim($result->output()) !== '';
    }

    private function tidyUp(): void
    {
        if ($this->files->exists(base_path('vendor/bin/pint'))) {
            Process::path(base_path())->run(['vendor/bin/pint', '--dirty', '--quiet']);
        }

        Process::path(base_path())->run(['composer', 'dump-autoload', '-q']);
    }

    private function printFollowUps(): void
    {
        if ($this->warnings !== []) {
            warning('Some automatic edits were skipped:');

            foreach ($this->warnings as $message) {
                $this->line('  - '.$message);
            }
        }

        note('Manual follow-ups:');
        $this->line('  - Run `pnpm run build` (or `pnpm run dev`) to regenerate Wayfinder routes/actions and catch stale TS imports.');
        $this->line('  - Run `php artisan auth:permissions-sync --force` to sync the trimmed permission set to the database.');
        $this->line('  - Run `composer test` to confirm the suite is green.');
        $this->line('  - Update README.md / CLAUDE.md — they still describe a multi-panel app.');
        $this->line('  - Dashboard, global search, and notifications now have no permission gate — any authenticated user can reach them. Add `permission:` middleware to routes/admin.php if that\'s not what you want.');
    }

    private function maybeSelfDestruct(): void
    {
        if (! confirm(
            label: 'Delete this panel:strip command now that its job is done?',
            default: false,
            hint: 'Choosing No leaves it in app/Console/Commands — safe to rerun, it will just report the app is already single-panel.',
        )) {
            info('Keeping panel:strip in the project.');

            return;
        }

        $path = (new ReflectionClass(self::class))->getFileName();

        if ($path === false || ! $this->files->exists($path)) {
            error('Could not locate this command file to self-destruct.');

            return;
        }

        $this->files->delete($path);
        info('panel:strip has removed itself.');
    }
}
