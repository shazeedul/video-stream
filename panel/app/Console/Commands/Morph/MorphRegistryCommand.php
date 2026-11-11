<?php

declare(strict_types=1);

namespace App\Console\Commands\Morph;

use App\Providers\MorphRegistryServiceProvider;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Relations\Relation;

#[\Illuminate\Console\Attributes\Description('List and manage polymorphic type registry')]
#[\Illuminate\Console\Attributes\Signature('morph:registry
                            {--clear-cache : Clear the morph registry cache}
                            {--validate : Validate all morph registry entries}')]
final class MorphRegistryCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->option('clear-cache')) {
            return $this->clearCache();
        }

        if ($this->option('validate')) {
            return $this->validateMappings();
        }

        return $this->listMappings();
    }

    /**
     * List all morph registry entries.
     */
    private function listMappings(): int
    {
        $this->info('Polymorphic Type Registry:');
        $this->newLine();

        $registry = Relation::morphMap();

        if (empty($registry)) {
            $this->warn('No morph registry entries found.');

            return 0;
        }

        $headers = ['Alias', 'Class'];
        $rows = [];

        foreach ($registry as $alias => $class) {
            $rows[] = [$alias, $class];
        }

        $this->table($headers, $rows);
        $this->newLine();
        $this->info('Total registry entries: '.count($registry));

        return 0;
    }

    /**
     * Clear morph registry cache.
     */
    private function clearCache(): int
    {
        MorphRegistryServiceProvider::clearCache();
        $this->info('Morph registry cache cleared successfully.');

        return 0;
    }

    /**
     * Validate all morph registry entries.
     */
    private function validateMappings(): int
    {
        $this->info('Validating morph registry entries...');
        $this->newLine();

        $registry = config('morph_registry.registry', []);
        $validCount = 0;
        $invalidCount = 0;

        foreach ($registry as $alias => $class) {
            if (class_exists($class)) {
                $this->line(sprintf('<fg=green>✓</> %s -> %s', $alias, $class));
                $validCount++;
            } else {
                $this->line(sprintf('<fg=red>✗</> %s -> %s <fg=red>(Class not found)</>', $alias, $class));
                $invalidCount++;
            }
        }

        $this->newLine();

        if ($invalidCount === 0) {
            $this->info(sprintf('All %d registry entries are valid!', $validCount));

            return 0;
        }

        $this->error(sprintf('Found %d invalid registry entries out of ', $invalidCount).count($registry).' total.');

        return 1;
    }
}
