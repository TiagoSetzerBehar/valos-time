<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;
use Throwable;

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

        $commands = [
            'valo:send-daily-reminders',
            'valo:send-weekly-summary',
            'valo:send-member-weekly-reports',
            'time-entry:send-still-running-mails',
        ];
        foreach ($commands as $command) {
            try {
                Artisan::call($command);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return response('ok')->header('Cache-Control', 'no-store');
    }
}
