<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('trading:dispatch --scope=evaluate')
    ->name('trading:evaluate-markets')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('trading:dispatch --scope=monitor')
    ->name('trading:monitor-orders')
    ->everyTenSeconds()
    ->withoutOverlapping();

Schedule::command('trading:dispatch --scope=exit')
    ->name('trading:manage-exits')
    ->everyThirtySeconds()
    ->withoutOverlapping();
