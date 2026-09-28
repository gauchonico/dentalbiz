<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Mark overdue invoices daily
Schedule::command('invoices:mark-overdue')->dailyAt('01:00');

// Generate end-of-day payments report
Schedule::command('payments:eod-report --email=admins')->dailyAt('23:55');

// Auto-close cash sessions when the business day rolls over
Schedule::command('cash-sessions:auto-close')->dailyAt(config('clinic.cash_day_starts_at', '00:00'));
