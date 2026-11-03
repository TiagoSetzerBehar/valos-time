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

    // Secret of the /valo/cron/{token} URL that sends the reminders on hosts without a scheduler.
    'cron_token' => (string) env('VALO_CRON_TOKEN', ''),

    // Google Apps Script web app in the VALO Gmail that sends the emails (MAIL_MAILER=valo-gmail).
    'mail_relay_url' => (string) env('VALO_MAIL_RELAY_URL', ''),
    'mail_relay_secret' => (string) env('VALO_MAIL_RELAY_SECRET', ''),

];
