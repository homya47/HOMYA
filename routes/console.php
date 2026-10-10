<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Run schedule:work locally or schedule:run every minute on the verified host.
Schedule::command('homya:expire-bank-transfers --apply')
    ->everyMinute()->withoutOverlapping(5)->when(fn () => config('commerce.inventory_enabled') === true);
