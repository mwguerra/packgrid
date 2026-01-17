<?php

use Illuminate\Support\Facades\Schedule;

// Sync all enabled repositories every 4 hours
Schedule::command('packgrid:sync-repositories')
    ->everyFourHours()
    ->withoutOverlapping()
    ->runInBackground();

// Test all GitHub credentials once a day at 6 AM
Schedule::command('packgrid:test-credentials')
    ->dailyAt('06:00')
    ->withoutOverlapping()
    ->runInBackground();

// Garbage collect unreferenced Docker blobs weekly on Sundays at 3 AM
Schedule::command('packgrid:docker-gc')
    ->weekly()
    ->sundays()
    ->at('03:00')
    ->withoutOverlapping()
    ->runInBackground();
