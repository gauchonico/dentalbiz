<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cash Drawer Business Day
    |--------------------------------------------------------------------------
    |
    | Time of day (24h, in the app timezone) when a new business day begins.
    | Any cash session still open when the day rolls over is auto-closed and
    | flagged for reconciliation. Use e.g. "06:00" for clinics with night shifts.
    |
    */

    'cash_day_starts_at' => env('CASH_DAY_STARTS_AT', '00:00'),

    /*
    |--------------------------------------------------------------------------
    | Public Registration
    |--------------------------------------------------------------------------
    |
    | Staff accounts are created by an admin from the Staff page. Keep this off
    | on any internet-facing deployment so strangers cannot sign up.
    |
    */

    'registration_enabled' => (bool) env('REGISTRATION_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Imaging Storage
    |--------------------------------------------------------------------------
    |
    | Disk for patient X-rays and photos. Must be private. On Laravel Cloud,
    | attach a private bucket and set this to that bucket's disk name.
    |
    */

    'imaging_disk' => env('IMAGING_DISK', 'local'),

];
