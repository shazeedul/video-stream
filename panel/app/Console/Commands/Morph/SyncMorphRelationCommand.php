<?php

declare(strict_types=1);

namespace App\Console\Commands\Morph;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Console\Helper\ProgressBar;

#[\Illuminate\Console\Attributes\Description('Migrate existing polymorphic data from full namespaces to registry aliases')]
#[\Illuminate\Console\Attributes\Signature('morph:sync
                            {--dry-run : Show what would be changed without making changes}
                            {--table= : Migrate specific table only}
                            {--batch-size=1000 : Number of records to process per batch}')]
final class SyncMorphRelationCommand extends Command
{
    /**
     * Tables with polymorphic relationships.
     * Each table can have multiple polymorphic type columns.
     */
    private array $polymorphicTables = [
        'notifications' => ['notifiable_type'],
        'model_has_roles' => ['model_type'],
        'model_has_permissions' => ['model_type'],
        'personal_access_tokens' => ['tokenable_type'],
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $specificTable = $this->option('table');
        $this->option('batch-size');

        if ($isDryRun) {
            $this->info('DRY RUN MODE - No changes will be made');
        }

        $tablesToProcess = $specificTable ? [$specificTable] : array_keys($this->polymorphicTables);

        // Filter existing tables
        $existingTables = array_filter($tablesToProcess, Schema::hasTable(...));

        if ($existingTables === []) {
            $this->info('No tables found to migrate.');

            return 0;
        }

        // Count total morph types to migrate
        $totalMorphTypes = $this->countTotalMorphTypes($existingTables);

        if ($totalMorphTypes === 0) {
            $this->info('No morph types found to migrate.');

            return 0;
        }

        $this->info(sprintf('Found %s morph types to migrate across ', $totalMorphTypes).count($existingTables).' tables');
        $this->newLine();

        $totalChanges = 0;

        foreach ($existingTables as $table) {
            $this->info('Processing table: '.$table);
            $changes = $this->migrateTable($table, $isDryRun);
            $totalChanges += $changes;
            $this->newLine();
        }

        if ($isDryRun) {
            $this->info(sprintf('Dry run completed. Would have updated %d records.', $totalChanges));
        } else {
            $this->info(sprintf('Migration completed. Updated %d records.', $totalChanges));
        }

        return 0;
    }

    /**
     * Get mappings from config (reversed for migration).
     */
    private function getMappings(): array
    {
        $registry = config('morph_registry.registry', []);

        // Reverse the registry to get old namespace -> alias mapping
        $mappings = [];
        foreach ($registry as $alias => $class) {
            $mappings[$class] = $alias;
        }

        // SELECT DISTINCT serviceable_type FROM service_histories WHERE serviceable_type LIKE '%\\\\%';

        $mappings = [
            ...$mappings,
            'App\Models\NidServerCopy' => 'NidServerCopy',
            'App\Models\DoubleVoterApplication' => 'DoubleVoterApplication',
            'App\Models\ActiveDeadVoter' => 'ActiveDeadVoter',
            'Modules\ServiceManagement\Models\ServiceCheckout' => 'ServiceCheckout',
            'Modules\SlipToNidNo\Entities\SlipToNidNo' => 'SlipToNidNo',
            'Modules\NidFee\Entities\NidFee' => 'NidFee',
            'Modules\DataMinutePack\Entities\DataMinutePackRequest' => 'DataMinutePackRequest',
            'Modules\NidFee\Models\NidFee' => 'NidFee',
        ];

        return $mappings;
    }

    /**
     * Count total morph types to migrate.
     */
    private function countTotalMorphTypes(array $tables): int
    {
        $mappings = $this->getMappings();
        $totalCount = 0;

        foreach ($tables as $table) {
            $typeColumns = $this->polymorphicTables[$table] ?? [];

            foreach ($typeColumns as $typeColumn) {
                $oldTypes = DB::table($table)
                    ->whereNotNull($typeColumn)
                    ->whereIn($typeColumn, array_keys($mappings))
                    ->distinct()
                    ->pluck($typeColumn)
                    ->toArray();

                $totalCount += count($oldTypes);
            }
        }

        return $totalCount;
    }

    /**
     * Migrate a specific table.
     */
    private function migrateTable(string $table, bool $isDryRun): int
    {
        $typeColumns = $this->polymorphicTables[$table] ?? null;
        if (! $typeColumns || empty($typeColumns)) {
            return 0;
        }

        // Get mappings from config
        $mappings = $this->getMappings();
        $totalChanges = 0;

        // Count morph types for this table
        $tableMorphTypes = 0;
        foreach ($typeColumns as $typeColumn) {
            $oldTypes = DB::table($table)
                ->whereNotNull($typeColumn)
                ->whereIn($typeColumn, array_keys($mappings))
                ->distinct()
                ->pluck($typeColumn)
                ->toArray();
            $tableMorphTypes += count($oldTypes);
        }

        if ($tableMorphTypes === 0) {
            $this->info('  No morph types found in '.$table);

            return 0;
        }

        // Create progress bar for this table
        $progressBar = $this->createProgressBar($tableMorphTypes, 'Migrating '.$table);
        $processedTypes = 0;

        // Process each polymorphic column in the table
        foreach ($typeColumns as $typeColumn) {
            // Get all unique type values that need migration for this column
            $oldTypes = DB::table($table)
                ->whereNotNull($typeColumn)
                ->whereIn($typeColumn, array_keys($mappings))
                ->distinct()
                ->pluck($typeColumn)
                ->toArray();

            if (empty($oldTypes)) {
                continue;
            }

            // Process each old type
            foreach ($oldTypes as $oldType) {
                $newType = $mappings[$oldType];
                $totalCount = DB::table($table)->where($typeColumn, $oldType)->count();

                if ($isDryRun) {
                    $totalChanges += $totalCount;
                } elseif ($totalCount > 0) {
                    // Simple bulk update for each morph type
                    $changes = $this->migrateWithChunking($table, $typeColumn, $oldType, $newType);
                    $totalChanges += $changes;
                }

                // Update progress bar
                $processedTypes++;
                $this->updateProgress($progressBar);
            }
        }

        $progressBar->finish();
        $this->info(sprintf('  ✅ %s: Updated %d records', $table, $totalChanges));

        return $totalChanges;
    }

    /**
     * Migrate data with bulk update.
     */
    private function migrateWithChunking(string $table, string $typeColumn, string $oldType, string $newType): int
    {
        // Simple bulk update - much faster!
        $updated = DB::table($table)
            ->where($typeColumn, $oldType)
            ->update([
                $typeColumn => DB::raw(sprintf("'%s'", $newType)),
            ]);

        return $updated;
    }

    /**
     * Create a progress bar with custom format
     */
    private function createProgressBar(int $total, string $label): ProgressBar
    {
        $progressBar = $this->output->createProgressBar($total);
        $progressBar->setFormat(
            '%current%/%max% [%bar%] %percent:3s%% - Elapsed: %elapsed% / Estimated: %estimated%'
        );
        $progressBar->setMessage($label, 'label');

        return $progressBar;
    }

    /**
     * Update progress and show status
     */
    private function updateProgress(ProgressBar $progressBar): void
    {
        $progressBar->advance();
    }
}
