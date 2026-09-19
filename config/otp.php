<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Test mode
    |--------------------------------------------------------------------------
    |
    | With no SMS provider wired up yet, the code is fixed and handed back in
    | the response so the handset can prefill it. That is a convenience for
    | testing and nothing else: while this is true, anyone who knows a phone
    | number can sign in as its owner.
    |
    | Set OTP_DEBUG=false the moment real numbers are involved — the delivery
    | channel is the other half of this and is not built yet.
    |
    */

    'debug' => (bool) env('OTP_DEBUG', true),

    'debug_code' => (string) env('OTP_DEBUG_CODE', '123456'),

    /*
    |--------------------------------------------------------------------------
    | Codes
    |--------------------------------------------------------------------------
    */

    'length' => 6,

    'ttl_minutes' => (int) env('OTP_TTL_MINUTES', 10),

    /** Wrong guesses allowed before the code is burnt and must be resent. */
    'max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5),

    /*
    |--------------------------------------------------------------------------
    | Who may sign in
    |--------------------------------------------------------------------------
    |
    | True lets an unknown number create its own (empty) account. False — the
    | default — means the number has to belong to an account already, so a
    | stranger's handset cannot quietly start its own set of books on your
    | server. Give the owner's account a phone with:
    |
    |     php artisan invoice:admin --email=… --phone=+919876543210
    |
    */

    'allow_registration' => (bool) env('OTP_ALLOW_REGISTRATION', false),

];
