<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Services\DeviceInventoryService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('device-units:release-expired', function (DeviceInventoryService $inventory) {
    $this->info($inventory->releaseExpiredReservations().' expired device reservation(s) released.');
})->purpose('Return expired exact-device reservations to available inventory');

Schedule::command('device-units:release-expired')->everyMinute()->withoutOverlapping();
