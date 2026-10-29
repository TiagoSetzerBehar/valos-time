<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | VALO's Time
    |--------------------------------------------------------------------------
    |
    | Customizations of this solidtime fork. With show_money disabled the UI
    | hides billable rates, costs and currency (the billable flag stays).
    |
    */

    'show_money' => (bool) env('VALO_SHOW_MONEY', false),

];
