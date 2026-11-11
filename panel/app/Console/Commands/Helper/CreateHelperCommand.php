<?php

declare(strict_types=1);

namespace App\Console\Commands\Helper;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;

#[\Illuminate\Console\Attributes\Description('Create a new helper file with proper structure and auto-include it in the generator file')]
#[\Illuminate\Console\Attributes\Signature('make:helper
                            {name? : The name of the helper (without Helper suffix)}
                            {--path= : Custom path for the helper file}
                            {--force : Overwrite existing helper file}')]
final class CreateHelperCommand extends Command
{
    /**
     * The configuration for the helper generator.
     */
    private array $config;

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
        $name = $this->getHelperName();
        $path = $this->getHelperPath();

        if ($this->helperExists($name, $path) && ! $this->option('force')) {
            $this->components->error(sprintf("Helper '%s.php' already exists at %s", $name, $path));
            $this->components->info('Use --force to overwrite the existing file.');

            return;
        }

        $this->createHelperFile($name, $path);
        $this->ensureGeneratorFile();
        $this->addToGeneratorFile($name, $path);

        $this->components->info(sprintf("Helper '%s.php' created successfully!", $name));
        $this->components->info(sprintf('Location: %s/%s.php', $path, $name));
        $this->components->info('Added to generator file automatically.');
    }

    /**
     * Get the helper name from argument or ask user.
     */
    private function getHelperName(): string
    {
        $name = $this->argument('name');

        if (empty($name)) {
            $name = $this->ask('What is the name of the helper?');
        }

        if (empty($name)) {
            $this->components->error('Helper name is required!');
            throw new InvalidArgumentException('Helper name is required!');
        }

        // Convert to StudlyCase
        $name = Str::studly($name);

        return $name;
    }

    /**
     * Get the helper path from option or use default.
     */
    private function getHelperPath(): string
    {
        $path = $this->option('path');

        if (empty($path)) {
            $path = $this->config['generator_file_path'];
        }

        return $this->normalizePath($path);
    }

    /**
     * Check if helper file already exists.
     */
    private function helperExists(string $name, string $path): bool
    {
        $filePath = $path.DIRECTORY_SEPARATOR.($name.'.php');

        return File::exists($filePath);
    }

    /**
     * Normalize the provided path.
     */
    private function normalizePath(string $path): string
    {
        // If path is relative, make it absolute from base_path
        if (! str_starts_with($path, '/')) {
            $path = base_path($path);
        }

        return mb_rtrim($path, '/\\');
    }

    /**
     * Create the helper file with proper structure.
     */
    private function createHelperFile(string $name, string $path): void
    {
        $filename = $name.'.php';
        $filePath = $path.DIRECTORY_SEPARATOR.$filename;

        File::ensureDirectoryExists($path);

        $template = $this->getHelperTemplate($name);
        File::put($filePath, $template);
    }

    /**
     * Get the helper file template.
     */
    private function getHelperTemplate(string $name): string
    {
        $studlyName = Str::studly($name);
        $snakeName = Str::snake($name);

        $template = "<?php\n\ndeclare(strict_types=1);\n\n";
        $template .= "if (!function_exists('{$snakeName}_example')) {\n";
        $template .= "    /**\n";
        $template .= "     * Example helper function for {$studlyName}.\n";
        $template .= "     *\n";
        $template .= "     * @param mixed \$value\n";
        $template .= "     * @return mixed\n";
        $template .= "     */\n";
        $template .= "    function {$snakeName}_example(\$value)\n";
        $template .= "    {\n";
        $template .= "        // Add your helper logic here\n";
        $template .= "        return \$value;\n";
        $template .= "    }\n";
        $template .= "}\n\n";

        return $template."// Add more {$snakeName} related helper functions below\n";
    }

    /**
     * Ensure the generator file exists and starts with the PHP opening tag.
     */
    private function ensureGeneratorFile(): void
    {
        $generatorFilePath = $this->config['generator_file_path'].DIRECTORY_SEPARATOR.$this->config['generator_file_name'];

        // Create the generator file if it does not exist
        if (! File::exists($generatorFilePath)) {
            File::put($generatorFilePath, "<?php\n");
        } else {
            // Check if the file starts with <?php
            $content = File::get($generatorFilePath);
            if (! str_starts_with($content, '<?php')) {
                $content = "<?php\n".$content;
                File::put($generatorFilePath, $content);
            }
        }
    }

    /**
     * Add the helper file to the generator include file.
     *
     * @param  string  $name  The name of the helper.
     * @param  string  $path  The path where the helper file was created.
     */
    private function addToGeneratorFile(string $name, string $path): void
    {
        $generatorFilePath = $this->config['generator_file_path'].DIRECTORY_SEPARATOR.$this->config['generator_file_name'];
        $relativePath = $this->getRelativePath(dirname($generatorFilePath), $path.DIRECTORY_SEPARATOR.($name.'.php'));

        $content = File::get($generatorFilePath);
        $requireStatement = "require_once __DIR__ . '/{$relativePath}';\n";

        // Prevent duplicate entries
        if (Str::contains($content, $requireStatement)) {
            $this->info(sprintf("Helper '%s.php' is already included in the generator file.", $name));

            return;
        }

        File::append($generatorFilePath, $requireStatement);
    }

    /**
     * Calculate the relative path from one directory to another file.
     *
     * @param  string  $from  The starting directory.
     * @param  string  $to  The target file path.
     * @return string The relative path.
     */
    private function getRelativePath(string $from, string $to): string
    {
        $fromArr = explode('/', str_replace('\\', '/', mb_rtrim($from, '/')));
        $toArr = explode('/', str_replace('\\', '/', mb_rtrim($to, '/')));
        while ($fromArr && $toArr && $fromArr[0] === $toArr[0]) {
            array_shift($fromArr);
            array_shift($toArr);
        }

        // Build the relative path
        $relativePath = str_repeat('../', count($fromArr)).implode('/', $toArr);

        return preg_replace('/\/+/', '/', $relativePath);
    }
}
