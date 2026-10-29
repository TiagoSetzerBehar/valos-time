<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;

/**
 * VALO's Time: hosts without a scheduler (Render free plan) call this secret URL every few
 * minutes (cron-job.org) to send the forgotten-hours reminders and still-running timer emails.
 */
class ValoCronController extends Controller
{
    public function __invoke(string $token): Response
    {
        $expectedToken = (string) config('valo.cron_token');
        abort_if($expectedToken === '' || ! hash_equals($expectedToken, $token), 404);

        Artisan::call('valo:send-daily-reminders');
        Artisan::call('valo:send-weekly-summary');
        Artisan::call('time-entry:send-still-running-mails');

        return response('ok')->header('Cache-Control', 'no-store');
    }
}
