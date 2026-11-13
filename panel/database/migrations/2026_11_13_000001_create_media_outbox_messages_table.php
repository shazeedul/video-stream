<?php

declare(strict_types=1);

use App\Domain\Processing\Enums\OutboxStatus;
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
        Schema::create('media_outbox_messages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('event_type');
            $table->string('aggregate_type');
            $table->uuid('aggregate_id')->index();
            $table->jsonb('payload');
            $table->string('status')->default(OutboxStatus::PENDING->value)->index();
            $table->integer('attempts')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_outbox_messages');
    }
};
