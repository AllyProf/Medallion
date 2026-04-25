<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule automatic biometric attendance sync every 5 minutes
Schedule::command('attendance:sync-biometric')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/attendance-sync.log'));

// Backup schedules - Only run on Production (Live Server)
if (app()->isProduction()) {
    // Daily Database Backup at 20:30 (Saa 2:30 usiku) with SMS Notification
    Schedule::command('backup:run --only-db')
        ->dailyAt('20:30')
        ->withoutOverlapping()
        ->onOneServer()
        ->appendOutputTo(storage_path('logs/backup.log'))
        ->onSuccess(function () {
            $smsService = new \App\Services\SmsService();
            $date = \Carbon\Carbon::now()->timezone('Africa/Dar_es_Salaam')->format('d-M-Y H:i');
            $message = "MEDALLION SYSTEM: Database Backup imekamilika kikamilifu kwa leo ($date). Data ziko salama.";
            $smsService->sendSms('0616775800', $message);
        })
        ->onFailure(function () {
            $smsService = new \App\Services\SmsService();
            $message = "MEDALLION ALERT: Database Backup imeshindwa (FAILED) kufanyika. Tafadhali ingia kwenye mfumo kuangalia tatizo haraka.";
            $smsService->sendSms('0616775800', $message);
        });

    // Cleanup old backups at 21:00 (Saa 3:00 usiku)
    Schedule::command('backup:clean')
        ->dailyAt('21:00')
        ->withoutOverlapping()
        ->onOneServer()
        ->appendOutputTo(storage_path('logs/backup-cleanup.log'));
}
