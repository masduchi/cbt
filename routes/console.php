<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('backup:run', ['--keep' => config('backup.retention_days')])
    ->dailyAt(config('backup.schedule_at'))
    ->withoutOverlapping();

// Pemeriksaan ulang memastikan backup terbaru benar-benar dapat dipulihkan.
Schedule::command('backup:verify')
    ->dailyAt(config('backup.verify_at'))
    ->withoutOverlapping();

Schedule::command('cbt:arsip-log')
    ->monthlyOn(1, '02:30')
    ->withoutOverlapping();

Schedule::command('monitor:health')
    ->everyFiveMinutes()
    ->withoutOverlapping();

// Denyut penjadwal: /health menandai sistem tidak sehat bila scheduler mati.
Schedule::call(function (): void {
    Cache::put('monitor:scheduler_heartbeat', now()->getTimestamp(), now()->addMinutes(15));
})->everyMinute()->name('scheduler-heartbeat')->withoutOverlapping();

Schedule::command('cbt:prune')
    ->dailyAt('03:10')
    ->withoutOverlapping();

// Akun yang masih memakai password bawaan wajib mengganti saat login berikutnya.
Schedule::command('app:secure-default-accounts --apply')
    ->dailyAt('03:20')
    ->withoutOverlapping();
