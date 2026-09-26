<?php

return [

    /*
    |--------------------------------------------------------------------------
    | The demo seeding page
    |--------------------------------------------------------------------------
    |
    | `/demo-seed` lays down three sample businesses with a few months of
    | bills behind them, and reports what is already there when it is opened
    | again. It takes no login, because the point of it is to hand somebody a
    | link and have them see a working panel a moment later.
    |
    | That also means anyone who knows the address can open it, which is why
    | it answers 404 unless this is on. Two things keep it from doing damage
    | while it is: it only ever creates what is missing — a business that
    | already has documents against it is left exactly as it is, so a reload
    | changes nothing — and it never deletes or overwrites.
    |
    | Turn it off once this instance holds real books. It follows SEED_DEMO,
    | the same switch the deployment script uses, so there is one thing to
    | set rather than two that can disagree.
    |
    */

    'enabled' => (bool) env('SEED_DEMO', false),

];
