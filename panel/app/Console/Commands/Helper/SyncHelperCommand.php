<?php

declare(strict_types=1);

namespace App\Console\Commands\Helper;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;

#[\Illuminate\Console\Attributes\Description('Synchronize and generate helper include file with require statements for all helper files')]
#[\Illuminate\Console\Attributes\Signature('helper:sync
                            {--force : Force regenerate the helper file}
                            {--detailed : Show detailed output during sync}')]
final class SyncHelperCommand extends Command
{
    /**
     * The configuration for the helper generator.
     */
    private array $config = [];

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
        $this->config = Config::get('helpers_generator');
    }

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->displayHeader();

        $phpFiles = $this->collectPhpFiles();

        if ($phpFiles === []) {
            $this->components->warn('No helper files found in the configured directories.');
            $this->displayScanDirectories();

            return;
        }

        if ($this->option('detailed')) {
            $this->displayFoundFiles($phpFiles);
        }

        $this->generateRequireFile($phpFiles);
        $this->displaySummary($phpFiles);
    }

    /**
     * Display command header.
     */
    private function displayHeader(): void
    {
        $this->components->info('Helper Sync Command');
        $this->components->info('==================');
    }

    /**
     * Display scan directories.
     */
    private function displayScanDirectories(): void
    {
        $this->line('');
        $this->components->info('Configured scan directories:');
        foreach ($this->config['scan_directories'] as $directory) {
            $this->line('  - '.$directory);
        }
    }

    /**
     * Display found files.
     */
    private function displayFoundFiles(array $files): void
    {
        $this->line('');
        $this->components->info('Found helper files:');
        foreach ($files as $file) {
            $relativePath = str_replace(base_path().'/', '', $file);
            $this->line('  - '.$relativePath);
        }
    }

    /**
     * Display operation summary.
     */
    private function displaySummary(array $files): void
    {
        $fileCount = count($files);

        $this->line('');
        $this->components->info(sprintf('Successfully processed %d helper file(s) - linked with require statements', $fileCount));
    }

    /**
     * Generate require file with individual helper includes.
     */
    private function generateRequireFile(array $phpFiles): void
    {
        $generatorPath = $this->getGeneratorFilePath();
        $includeDir = dirname($generatorPath);

        // Ensure the directory exists
        File::ensureDirectoryExists($includeDir);

        // Check if file exists and force option
        if (File::exists($generatorPath) && ! $this->option('force') && ! $this->confirm('Generator file already exists. Overwrite?')) {
            $this->components->warn('Operation cancelled.');

            return;
        }

        $this->components->info('Generating require statements...');

        $requireStatements = $this->buildRequireStatements($phpFiles, $includeDir);
        $fileContent = "<?php\n\ndeclare(strict_types=1);\n\n".implode("\n", $requireStatements);

        File::put($generatorPath, $fileContent);

        $this->components->info('Include file generated: '.$generatorPath);
    }

    /**
     * Build require statements for individual files.
     */
    private function buildRequireStatements(array $files, string $baseDir): array
    {
        $statements = [];
        $statements[] = '/**';
        $statements[] = ' * Auto-generated helper include file';
        $statements[] = ' * Generated on: '.date('Y-m-d H:i:s');
        $statements[] = ' * Do not edit manually';
        $statements[] = ' */';
        $statements[] = '';

        foreach ($files as $filePath) {
            $relativePath = $this->getRelativePath($baseDir, $filePath);
            $relativePath = str_replace('\\', '/', $relativePath);
            $statements[] = sprintf("require_once __DIR__ . '/%s';", $relativePath);
        }

        return $statements;
    }

    /**
     * Get the full path to the generator file.
     */
    private function getGeneratorFilePath(): string
    {
        return $this->config['generator_file_path'].DIRECTORY_SEPARATOR.$this->config['generator_file_name'];
    }

    /**
     * Collect PHP files from the configured directories.
     */
    private function collectPhpFiles(): array
    {
        $phpFiles = [];

        foreach ($this->config['scan_directories'] as $directoryPattern) {
            // Handle glob patterns and direct paths
            if (str_contains((string) $directoryPattern, '*')) {
                $directories = glob($directoryPattern, GLOB_ONLYDIR);
            } else {
                $directories = [realpath($directoryPattern)];
            }

            foreach ($directories as $directory) {
                if (in_array($directory, ['', '0', false], true)) {
                    continue;
                }

                if (! is_dir($directory)) {
                    continue;
                }

                $files = File::files($directory);
                foreach ($files as $file) {
                    if ($file->getExtension() === 'php' &&
                        $file->getFilename() !== $this->config['generator_file_name']) {
                        $phpFiles[] = $file->getRealPath();
                    }
                }
            }
        }

        return array_filter($phpFiles);
    }

    /**
     * Calculate the relative path from one directory to another file.
     */
    private function getRelativePath(string $from, string $to): string
    {
        $fromArr = explode('/', str_replace('\\', '/', mb_rtrim($from, '/')));
        $toArr = explode('/', str_replace('\\', '/', mb_rtrim($to, '/')));

        while ($fromArr && $toArr && $fromArr[0] === $toArr[0]) {
            array_shift($fromArr);
            array_shift($toArr);
        }

        $relativePath = str_repeat('../', count($fromArr)).implode('/', $toArr);

        return preg_replace('/\/+/', '/', $relativePath);
    }
}
