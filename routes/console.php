<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Background job scheduling (Stage 10). v2 runs these three jobs as Vercel
 * Cron HTTP endpoints (vercel.json's `crons` array), each gated by
 * verifyInternalJobRequest()'s shared-secret check
 * (src/lib/jobs/verify-internal-job-request.ts) — necessary there because
 * Vercel Cron calls the deployed app over the public internet, and without
 * that check the endpoint would be an open door for anyone who found the
 * URL. Laravel's scheduler has no such exposure: `schedule:run` executes
 * these commands in-process on the same box that runs the app, with no
 * HTTP surface at all — so there is nothing here for a shared secret (an
 * ENV\INTERNAL_JOB_SECRET/CRON_SECRET) to protect, and porting one over
 * would just be unused config. The three schedule entries below are the
 * direct Laravel equivalent of vercel.json's `crons` array; the times
 * match exactly.
 *
 * Explicit ->timezone('UTC') even though config/app.php's `timezone` is
 * already 'UTC' — self-documenting against vercel.json's cron expressions,
 * which are UTC by Vercel Cron's own contract, and safe if the app
 * timezone is ever changed for a display-only reason elsewhere.
 */
Schedule::command('jobs:ingest-prices')->dailyAt('05:00')->timezone('UTC');
Schedule::command('jobs:generate-alerts')->dailyAt('06:00')->timezone('UTC');
Schedule::command('jobs:run-verification')->monthlyOn(1, '03:00')->timezone('UTC');
