<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule cleanup of users that have passed their deletion date
Schedule::command('cleanup:users')
    ->daily()
    ->description('Delete users that have passed their scheduled deletion date');

// Schedule hourly aggregation of playback bandwidth and views from Redis to database
Schedule::command('usage:aggregate-bandwidth')
    ->hourly()
    ->description('Aggregate Redis playback egress and views into daily tenant usage records');
