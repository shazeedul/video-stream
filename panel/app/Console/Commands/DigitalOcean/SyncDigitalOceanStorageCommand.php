<?php

declare(strict_types=1);

namespace App\Console\Commands\DigitalOcean;

use App\Facades\DigitalOceanSpace;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Sleep;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

#[\Illuminate\Console\Attributes\Description('Sync Laravel storage files to DigitalOcean Spaces')]
#[\Illuminate\Console\Attributes\Signature('digitalocean:sync-storage
        {folder? : Specific folder to sync (optional)}
        {--delete : Delete remote files not present locally}
        {--private : Upload files as private instead of public}
        {--force-php : Force PHP implementation instead of rclone}
        {--threads=4 : Number of parallel transfer threads}
        {--bandwidth= : Bandwidth limit (e.g., 10M, 100M, 1G)}
        {--batch-size=20 : Batch size for PHP fallback}
        {--skip-cache : Skip cache purging}
        {--dry-run : Show what would be synced without doing it}
        ')]
final class SyncDigitalOceanStorageCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $folder = $this->argument('folder');
            $targetPath = $this->getTargetPath($folder);
            $remotePath = $folder ?? '';

            $this->displayHeader($targetPath, $remotePath);

            if (! is_dir($targetPath)) {
                $this->error('❌ Directory not found: '.$targetPath);

                return Command::FAILURE;
            }

            if ($this->option('dry-run')) {
                return $this->performDryRun($targetPath, $remotePath);
            }

            // Determine sync method
            if (! $this->option('force-php') && $this->isRcloneAvailable()) {
                $this->info('🚀 Using rclone for optimal performance');

                return $this->syncWithRclone($targetPath, $remotePath);
            }

            $this->warn('⚠️  Using PHP fallback');

            return $this->syncWithPhp($targetPath, $remotePath);

        } catch (Throwable $throwable) {
            $this->error('❌ Sync failed: '.$throwable->getMessage());

            if ($this->option('verbose')) {
                $this->error($throwable->getTraceAsString());
            }

            return Command::FAILURE;
        }
    }

    /**
     * Display command header
     */
    private function displayHeader(string $targetPath, string $remotePath): void
    {
        $this->info('💾 DigitalOcean Spaces Storage Sync');
        $this->line('==================================');
        $this->line('📁 Local: '.$targetPath);
        $this->line('☁️  Remote: '.($remotePath !== '' && $remotePath !== '0' ? $remotePath : 'root'));
        $this->line('🔒 Privacy: '.($this->option('private') ? 'Private' : 'Public'));
        $this->newLine();
    }

    /**
     * Get target path for sync
     */
    private function getTargetPath(?string $folder): string
    {
        $basePath = storage_path('app/public');

        return in_array($folder, [null, '', '0'], true) ? $basePath : $basePath.'/'.mb_ltrim($folder, '/');
    }

    /**
     * Perform dry run
     */
    private function performDryRun(string $targetPath, string $remotePath): int
    {
        $this->info('🔍 Dry Run - Scanning files...');

        $files = $this->scanDirectory($targetPath);
        $fileCount = count($files);

        $this->info(sprintf('📊 Found %d files to sync', $fileCount));

        if ($this->option('verbose') && $fileCount > 0) {
            $this->line('📄 Files to sync:');
            foreach (array_slice($files, 0, 10) as $file) {
                $relativePath = str_replace($targetPath, '', $file);
                $this->line(sprintf('   %s%s', $remotePath, $relativePath));
            }

            if ($fileCount > 10) {
                $this->line('   ... and '.($fileCount - 10).' more files');
            }
        }

        $this->newLine();
        $this->line('🚀 Run without --dry-run to perform actual sync');

        return Command::SUCCESS;
    }

    /**
     * Check if rclone is available
     */
    private function isRcloneAvailable(): bool
    {
        $result = Process::run('which rclone');

        return $result->successful();
    }

    /**
     * Sync with rclone
     */
    private function syncWithRclone(string $targetPath, string $remotePath): int
    {
        $this->setupRcloneConfig();

        try {
            $this->info('📤 Syncing storage with rclone...');

            $command = $this->buildRcloneCommand($targetPath, $remotePath);
            $result = Process::timeout(3600)->run($command);

            if ($result->successful()) {
                $this->components->info('✅ Storage synced successfully with rclone');

                if ($this->option('verbose') && $result->output()) {
                    $this->line($result->output());
                }

                return Command::SUCCESS;
            }

            $this->error('❌ rclone sync failed:');
            $this->error($result->errorOutput());

            return Command::FAILURE;

        } finally {
            $this->cleanupRcloneConfig();
        }
    }

    /**
     * Sync with PHP
     */
    private function syncWithPhp(string $targetPath, string $remotePath): int
    {
        $this->info('📤 Syncing storage with PHP...');

        $digitalOceanSpace = DigitalOceanSpace::create();
        $filePaths = $digitalOceanSpace->scanFileInFolder($targetPath);

        if (empty($filePaths)) {
            $this->warn('📭 No files found to sync');

            return Command::SUCCESS;
        }

        $totalFiles = count($filePaths);
        $this->info(sprintf('📊 Found %d files to upload', $totalFiles));

        $progressBar = $this->output->createProgressBar($totalFiles);
        $progressBar->start();

        $isPublic = ! $this->option('private');
        $failures = 0;
        $batchSize = (int) $this->option('batch-size');
        $batches = array_chunk($filePaths, $batchSize, true);

        foreach ($batches as $batch) {
            foreach ($batch as $relativePath => $absolutePath) {
                try {
                    $destinationPath = $remotePath.$relativePath;
                    $digitalOceanSpace->upload($absolutePath, $destinationPath, $isPublic);
                } catch (Throwable $th) {
                    $failures++;
                    if ($this->option('verbose')) {
                        $this->error(sprintf('Failed: %s - %s', $destinationPath, $th->getMessage()));
                    }
                }

                $progressBar->advance();
            }

            // Small delay between batches
            Sleep::usleep(10000); // 10ms
        }

        $progressBar->finish();
        $this->newLine();

        // Purge cache
        if (! $this->option('skip-cache')) {
            $this->info('🧹 Purging cache...');
            $digitalOceanSpace->purgeCache($remotePath);
        }

        $successCount = $totalFiles - $failures;
        $this->displayResults($successCount, $totalFiles, $failures);

        return $failures === 0 ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * Setup rclone configuration
     */
    private function setupRcloneConfig(): void
    {
        $configPath = storage_path('app/rclone-storage.conf');

        $config = [
            '[dos]',
            'type = s3',
            'provider = DigitalOcean',
            'access_key_id = '.config('filesystems.disks.dos.key'),
            'secret_access_key = '.config('filesystems.disks.dos.secret'),
            'region = '.config('filesystems.disks.dos.region'),
            'endpoint = '.str_replace('https://', '', config('filesystems.disks.dos.endpoint')),
            'acl = '.($this->option('private') ? 'private' : 'public-read'),
            'bucket_acl = '.($this->option('private') ? 'private' : 'public-read'),
        ];

        File::put($configPath, implode("\n", $config));
    }

    /**
     * Build rclone command
     */
    private function buildRcloneCommand(string $sourcePath, string $destPath): string
    {
        $configPath = storage_path('app/rclone-storage.conf');
        $bucket = config('filesystems.disks.dos.bucket');
        $root = config('filesystems.disks.dos.root', '');

        $destination = 'dos:'.$bucket.($root ? '/'.$root : '').($destPath !== '' && $destPath !== '0' ? '/'.$destPath : '');

        $cmd = 'rclone '.($this->option('delete') ? 'sync' : 'copy');
        $cmd .= sprintf(" --config '%s'", $configPath);
        $cmd .= ' --transfers '.$this->option('threads');
        $cmd .= ' --checkers '.($this->option('threads') * 2);

        // Bandwidth limiting
        if ($this->option('bandwidth')) {
            $cmd .= ' --bwlimit '.$this->option('bandwidth');
        }

        // Performance optimizations
        $cmd .= ' --fast-list --check-first --retries 3';
        $cmd .= ' --copy-links'; // Follow symbolic links and copy their content

        if ($this->option('verbose')) {
            $cmd .= ' --progress --stats 5s';
        } else {
            $cmd .= ' --stats 10s --stats-one-line';
        }

        return $cmd.sprintf(" '%s' '%s'", $sourcePath, $destination);
    }

    /**
     * Cleanup rclone config
     */
    private function cleanupRcloneConfig(): void
    {
        $configPath = storage_path('app/rclone-storage.conf');
        if (File::exists($configPath)) {
            File::delete($configPath);
        }
    }

    /**
     * Scan directory for files
     */
    private function scanDirectory(string $dir): array
    {
        if (! is_dir($dir)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS | RecursiveDirectoryIterator::FOLLOW_SYMLINKS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /**
     * Display sync results
     */
    private function displayResults(int $success, int $total, int $failures): void
    {
        $this->newLine();
        $this->info('📊 Sync Results');
        $this->line('===============');
        $this->info('✅ Successful: '.$success);

        if ($failures > 0) {
            $this->error('❌ Failed: '.$failures);
        }

        $successRate = round(($success / $total) * 100, 1);
        $this->info(sprintf('📈 Success Rate: %s%%', $successRate));

        if ($failures === 0) {
            $this->components->info('🎉 All files synced successfully!');
        } else {
            $this->components->warn('⚠️  Sync completed with some failures');
        }
    }
}
