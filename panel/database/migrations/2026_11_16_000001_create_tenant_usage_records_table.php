<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tenant_usage_records', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->date('date');
            $table->unsignedBigInteger('storage_bytes')->default(0);
            $table->unsignedBigInteger('egress_bytes')->default(0);
            $table->unsignedBigInteger('view_count')->default(0);
            $table->unsignedInteger('video_count')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_usage_records');
    }
};
