<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Local testing options
    |--------------------------------------------------------------------------
    |
    | Allows teachers to capture attendance for future sessions while testing
    | locally. This option is intentionally ignored outside APP_ENV=local.
    |
    */
    'allow_future_capture_local' => env('ATTENDANCE_ALLOW_FUTURE_CAPTURE', false),

    /*
    |--------------------------------------------------------------------------
    | Temporary test-cycle attendance editing
    |--------------------------------------------------------------------------
    |
    | Lets teachers reopen closed/past attendance only for specific cycle codes.
    | Keep this list narrow because it bypasses normal capture locks.
    |
    */
    'editable_cycle_codes_for_testing' => array_filter(array_map(
        'trim',
        explode(',', env('ATTENDANCE_EDITABLE_TEST_CYCLES', '26-3,26-27'))
    )),
];
