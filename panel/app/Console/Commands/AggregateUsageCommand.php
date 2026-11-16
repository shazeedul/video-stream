<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Usage\Contracts\TenantUsageServiceInterface;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('Aggregate Redis playback egress and views into daily tenant usage records')]
#[Signature('usage:aggregate-bandwidth {--date= : The date to aggregate (YYYY-MM-DD)}')]
final class AggregateUsageCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'usage:aggregate-bandwidth {--date= : The date to aggregate (YYYY-MM-DD)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Aggregate Redis playback egress and views into daily tenant usage records';

    /**
     * Execute the console command.
     */
    public function handle(TenantUsageServiceInterface $usageService): int
    {
        /** @var string|null $date */
        $date = $this->option('date');
        $targetDate = is_string($date) && $date !== '' ? $date : null;

        $processed = $usageService->aggregateHourlyUsage($targetDate);

        $this->info("Successfully aggregated usage for {$processed} active tenant(s).");

        return self::SUCCESS;
    }
}
