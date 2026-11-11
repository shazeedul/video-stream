<?php

declare(strict_types=1);

namespace App\Console\Commands\Authorization;

use App\Models\Role;
use App\Models\User;
use App\Services\RolePermissionSyncService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\multiselect;

#[\Illuminate\Console\Attributes\Description('Synchronize permissions and roles from definition files to database')]
#[\Illuminate\Console\Attributes\Signature('auth:permissions-sync
        {--force : Force sync without confirmation in production}
        {--dry-run : Show what would be changed without making any changes}
        {--no-user : Skip user creation after sync}
        {--detailed : Show detailed sync logs}')]
final class SyncAccessControlCommand extends Command
{
    public function __construct(
        private readonly RolePermissionSyncService $aclSyncService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Safety check for production environment
        if (app()->environment('production') && ! $this->option('force') && ! $this->option('dry-run') && ! $this->confirm('This will sync permissions and roles in PRODUCTION. Are you sure?')) {
            $this->info('Sync cancelled.');

            return Command::SUCCESS;
        }

        $isDryRun = (bool) $this->option('dry-run');
        $isDetailed = (bool) $this->option('detailed');

        if ($isDryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made to the database');
            $this->newLine();
        }

        $this->info('Starting permissions and roles sync...');
        $this->newLine();

        // Run the sync
        $result = $this->aclSyncService
            ->dryRun($isDryRun)
            ->sync();

        // Display detailed logs if --detailed flag is set
        if ($isDetailed && $result['logs'] !== []) {
            $this->info('📋 Sync Details:');
            foreach ($result['logs'] as $log) {
                $this->line('  '.$log);
            }

            $this->newLine();
        }

        // Display summary
        $this->displaySummary($result['stats']);

        if ($isDryRun) {
            $this->newLine();
            $this->warn('🔍 DRY RUN COMPLETE - Run without --dry-run to apply changes');
        } else {
            $this->newLine();
            $this->info('✅ Permissions and roles sync completed successfully!');

            // Ask for user creation if --no-user flag is not set
            if (! $this->option('no-user')) {
                $this->handleUserCreation();
            }
        }

        return Command::SUCCESS;
    }

    /**
     * Display sync summary in a table.
     *
     * @param  array<string, int>  $stats
     */
    private function displaySummary(array $stats): void
    {
        $this->info('📊 Sync Summary:');

        $this->table(
            ['Operation', 'Count'],
            [
                ['Roles Created', $stats['roles_created']],
                ['Roles Updated', $stats['roles_updated']],
                ['Roles Deleted', $stats['roles_deleted']],
                ['Permissions Created', $stats['permissions_created']],
                ['Permissions Updated', $stats['permissions_updated']],
                ['Permissions Deleted', $stats['permissions_deleted']],
                ['Role Assignments Changed', $stats['role_assignments_synced']],
            ]
        );
    }

    private function handleUserCreation(): void
    {
        $this->newLine();

        do {
            if ($this->confirm('Do you want to create a new admin user?', false)) {
                if ($this->createUser()) {
                    $this->info('✅ User created successfully!');
                } else {
                    $this->error('❌ Failed to create user. Please check the errors above.');
                }
            } else {
                $this->info('👋 Permissions and roles sync completed!');

                return;
            }
        } while ($this->confirm('Do you want to create another user?', false));

        $this->info('👋 All done!');
    }

    private function createUser(): bool
    {
        try {
            // Get user details
            $name = $this->ask('Enter user name');
            $email = $this->ask('Enter user email');
            $password = $this->secret('Enter user password');

            // Check if user already exists
            $existingUser = User::query()->where('email', $email)->first();

            if ($existingUser) {
                if ($this->confirm(sprintf("User with email '%s' already exists. Do you want to update their password and roles?", $email), true)) {
                    return $this->updateExistingUser($existingUser, $name, $password);
                }

                $this->info('Skipping user update.');

                return false;
            }

            // Validate input for new user
            $validator = Validator::make([
                'name' => $name,
                'email' => $email,
                'password' => $password,
            ], [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
                'password' => ['required', 'string', 'min:8'],
            ]);

            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $error) {
                    $this->error($error);
                }

                return false;
            }

            // Get selected roles
            $selectedRoleNames = $this->selectUserRoles();
            if ($selectedRoleNames === false) {
                return false;
            }

            // Create user
            $user = User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'email_verified_at' => now(), // Mark as verified
            ]);

            // Assign roles
            if ($selectedRoleNames !== []) {
                $user->assignRole($selectedRoleNames);
                $this->line('✓ Assigned roles: '.implode(', ', $selectedRoleNames));
            }

            $this->info(sprintf("✅ User '%s' created successfully!", $name));

            return true;
        } catch (Exception $exception) {
            $this->error('Error creating user: '.$exception->getMessage());

            return false;
        }
    }

    private function updateExistingUser(User $user, string $name, string $password): bool
    {
        try {
            // Validate input for existing user (no unique email constraint)
            $validator = Validator::make([
                'name' => $name,
                'password' => $password,
            ], [
                'name' => ['required', 'string', 'max:255'],
                'password' => ['required', 'string', 'min:8'],
            ]);

            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $error) {
                    $this->error($error);
                }

                return false;
            }

            // Get selected roles
            $selectedRoleNames = $this->selectUserRoles();
            if ($selectedRoleNames === false) {
                return false;
            }

            // Update user details
            $user->update([
                'name' => $name,
                'password' => Hash::make($password),
            ]);

            // Sync roles (this will remove old roles and add new ones)
            $user->syncRoles($selectedRoleNames);

            $this->line('✓ Updated password');
            $this->line('✓ Synced roles: '.implode(', ', $selectedRoleNames));
            $this->info(sprintf("✅ User '%s' updated successfully!", $name));

            return true;
        } catch (Exception $exception) {
            $this->error('Error updating user: '.$exception->getMessage());

            return false;
        }
    }

    private function selectUserRoles(): array|false
    {
        // Get available roles (only global roles)
        $availableRoles = Role::query()
            ->orderBy('name')
            ->get();

        if ($availableRoles->isEmpty()) {
            $this->error('No roles available. Please run the sync first.');

            return false;
        }

        // Select roles using interactive multiselect
        $roleOptions = $availableRoles->mapWithKeys(fn ($role): array => [$role->name => sprintf('%s (%s)', $role->display_name, $role->description ?? 'No description')])->all();

        $selectedRoleNames = multiselect(
            label: 'Select roles for the user:',
            options: $roleOptions,
            required: true,
            hint: 'Use arrow keys to navigate, space to select, enter to confirm'
        );

        if ($selectedRoleNames === []) {
            $this->error('At least one role must be selected.');

            return false;
        }

        return $selectedRoleNames;
    }
}
