<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Schedule Wall-Clock Timezone
    |--------------------------------------------------------------------------
    |
    | The timezone `App\Http\Controllers\Portal\ScheduleController::store()`
    | interprets an incoming production-schedule block's start/end time in,
    | and the timezone resources/js/lib/scheduleTimezone.ts formats it back
    | into for display. Deliberately separate from `config('app.timezone')`
    | (UTC, and left that way — see config/database.php's own note on why
    | the DB session and PHP's default timezone must agree to avoid the
    | class of bug MIGRATION_PLAN.md already documents for JobRun timestamps):
    | a schedule block's "7am" is a physical site's wall-clock time, not a
    | system timestamp, and those two concerns shouldn't share one setting.
    |
    | Every pilot site is in Finland for now, so a single global timezone
    | (rather than a per-Site column) is the deliberately simple MVP choice
    | — revisit with a `sites.timezone` column if/when that stops being true.
    |
    */

    'timezone' => env('SCHEDULE_TIMEZONE', 'Europe/Helsinki'),

];
