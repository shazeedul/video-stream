<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

#[\Illuminate\Console\Attributes\Description('Delete users that have passed their scheduled deletion date')]
#[\Illuminate\Console\Attributes\Signature('cleanup:users {--dry-run : Display users to be deleted without actually deleting them}')]
final class CleanupScheduledUsers extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting cleanup of scheduled users...');

        // Get users that are scheduled for deletion and past their deletion date
        $users = User::query()->whereNotNull('scheduled_deletion_at')
            ->where('scheduled_deletion_at', '<=', now())
            ->get();

        if ($users->isEmpty()) {
            $this->info('No users found that need to be deleted.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Found %d user(s) scheduled for deletion.', $users->count()));

        if ($this->option('dry-run')) {
            $this->warn('Running in dry-run mode. No users will be deleted.');
            $this->newLine();

            $this->table(
                ['ID', 'Name', 'Email', 'Scheduled For', 'Days Overdue'],
                $users->map(fn (User $user): array => [
                    $user->id,
                    $user->name,
                    $user->email,
                    $user->scheduled_deletion_at->format('Y-m-d H:i:s'),
                    now()->diffInDays($user->scheduled_deletion_at, false),
                ])->all()
            );

            return self::SUCCESS;
        }

        $deletedCount = 0;

        foreach ($users as $user) {
            $userName = $user->name;
            $userId = $user->id;
            $userEmail = $user->email;

            try {
                DB::transaction(function () use ($user): void {
                    // Delete profile photo if exists
                    if ($user->profile_photo_path) {
                        Storage::delete($user->profile_photo_path);
                    }

                    // Force delete the user (this will also delete related records via cascade)
                    $user->forceDelete();
                });

                $this->info(sprintf('✓ Deleted user: %s (%s) (ID: %s)', $userName, $userEmail, $userId));
                $deletedCount++;
            } catch (Exception $e) {
                $this->error(sprintf('✗ Failed to delete user: %s (%s) (ID: %s)', $userName, $userEmail, $userId));
                $this->error('  Error: '.$e->getMessage());
            }
        }

        $this->newLine();
        $this->info(sprintf('Successfully deleted %d of %d user(s).', $deletedCount, $users->count()));

        return self::SUCCESS;
    }
}
