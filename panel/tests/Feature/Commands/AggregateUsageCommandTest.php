<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Domain\Tenants\Models\Tenant;
use App\Domain\Usage\Models\TenantUsageRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

final class AggregateUsageCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::connection()->flushdb();
    }

    protected function tearDown(): void
    {
        Redis::connection()->flushdb();
        parent::tearDown();
    }

    public function test_running_aggregation_command_persists_redis_views_and_egress_into_tenant_usage_records(): void
    {
        $tenant = Tenant::factory()->create();
        $date = now()->toDateString();

        Redis::set("tenant:{$tenant->id}:views:{$date}", 42);
        Redis::set("tenant:{$tenant->id}:egress:{$date}", 10485760);

        $this->artisan('usage:aggregate-bandwidth')
            ->assertSuccessful();

        $this->assertDatabaseHas('tenant_usage_records', [
            'tenant_id' => $tenant->id,
            'date' => $date,
            'view_count' => 42,
            'egress_bytes' => 10485760,
        ]);
    }

    public function test_running_aggregation_command_twice_idempotently_updates_existing_record_for_the_date(): void
    {
        $tenant = Tenant::factory()->create();
        $date = now()->toDateString();

        Redis::set("tenant:{$tenant->id}:views:{$date}", 10);
        Redis::set("tenant:{$tenant->id}:egress:{$date}", 1572864);

        $this->artisan('usage:aggregate-bandwidth')
            ->assertSuccessful();

        $this->assertDatabaseCount('tenant_usage_records', 1);

        $record = TenantUsageRecord::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('date', $date)
            ->first();

        $this->assertNotNull($record);
        $this->assertSame(10, $record->view_count);
        $this->assertSame(1572864, $record->egress_bytes);

        // Run second time without changes
        $this->artisan('usage:aggregate-bandwidth')
            ->assertSuccessful();

        $this->assertDatabaseCount('tenant_usage_records', 1);

        $record->refresh();
        $this->assertSame(10, $record->view_count);
        $this->assertSame(1572864, $record->egress_bytes);
    }

    public function test_running_aggregation_command_with_explicit_date_option(): void
    {
        $tenant = Tenant::factory()->create();
        $targetDate = '2026-11-10';

        Redis::set("tenant:{$tenant->id}:views:{$targetDate}", 7);
        Redis::set("tenant:{$tenant->id}:egress:{$targetDate}", 2097152);

        $this->artisan('usage:aggregate-bandwidth', ['--date' => $targetDate])
            ->assertSuccessful();

        $this->assertDatabaseHas('tenant_usage_records', [
            'tenant_id' => $tenant->id,
            'date' => $targetDate,
            'view_count' => 7,
            'egress_bytes' => 2097152,
        ]);
    }
}
