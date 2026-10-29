<?php

declare(strict_types=1);

namespace App\Console\Commands\Valo;

use App\Mail\ValoDailyReminderMail;
use App\Models\ValoReminderSetting;
use App\Service\ValoReminderService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class ValoSendDailyRemindersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'valo:send-daily-reminders '.
        ' { --dry-run : Do not actually send emails, just output what would happen }';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Emails members who have not logged any time today (weekdays, at the organization reminder time).';

    /**
     * Hours after the reminder time in which a missed run may still send the reminder.
     */
    private const int SEND_WINDOW_HOURS = 3;

    public function handle(ValoReminderService $reminderService): int
    {
        $this->comment('Sending daily reminders...');
        $dryRun = (bool) $this->option('dry-run');
        $sentMails = 0;

        $settings = ValoReminderSetting::query()
            ->where('daily_enabled', '=', true)
            ->with('organization')
            ->get();

        foreach ($settings as $setting) {
            [$hour, $minute] = array_map('intval', explode(':', $setting->daily_time));

            foreach ($reminderService->trackableMembers($setting->organization) as $member) {
                $user = $member->user;
                $now = Carbon::now($user->timezone);
                if ($now->isWeekend()) {
                    continue;
                }
                $sendAt = $now->copy()->setTime($hour, $minute);
                if ($now->lt($sendAt) || $now->gt($sendAt->copy()->addHours(self::SEND_WINDOW_HOURS))) {
                    continue;
                }
                $cacheKey = 'valo:daily-reminder:'.$member->getKey().':'.$now->toDateString();
                if (Cache::has($cacheKey)) {
                    continue;
                }

                $seconds = $reminderService->trackedSeconds($member, $now->copy()->startOfDay(), $now->copy()->startOfDay()->addDay());
                if (! $dryRun) {
                    Cache::put($cacheKey, true, Carbon::now()->addDays(2));
                }
                if ($seconds > 0) {
                    continue;
                }

                $this->info('Sending daily reminder to "'.$user->email.'" ('.$user->getKey().')');
                $sentMails++;
                if (! $dryRun) {
                    Mail::to($user->email)->queue(new ValoDailyReminderMail($user));
                }
            }
        }

        $this->comment('Finished sending '.$sentMails.' daily reminders...');

        return self::SUCCESS;
    }
}
