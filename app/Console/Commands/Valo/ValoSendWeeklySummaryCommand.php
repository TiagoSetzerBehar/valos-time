<?php

declare(strict_types=1);

namespace App\Console\Commands\Valo;

use App\Enums\Role;
use App\Mail\ValoWeeklySummaryMail;
use App\Models\Member;
use App\Models\ValoReminderSetting;
use App\Service\ValoReminderService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class ValoSendWeeklySummaryCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'valo:send-weekly-summary '.
        ' { --dry-run : Do not actually send emails, just output what would happen }';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Emails owners and admins on Monday morning which members logged fewer hours than expected last week.';

    private const int SEND_FROM_HOUR = 9;

    private const int SEND_UNTIL_HOUR = 12;

    public function handle(ValoReminderService $reminderService): int
    {
        $this->comment('Sending weekly summaries...');
        $dryRun = (bool) $this->option('dry-run');
        $sentMails = 0;

        $settings = ValoReminderSetting::query()
            ->where('weekly_enabled', '=', true)
            ->with('organization')
            ->get();

        foreach ($settings as $setting) {
            $members = $reminderService->trackableMembers($setting->organization);
            $recipients = $members->filter(
                fn (Member $member): bool => in_array($member->role, [Role::Owner->value, Role::Admin->value], true)
            );
            $expectedSeconds = $setting->expected_weekly_hours * 3600;

            foreach ($recipients as $recipient) {
                $user = $recipient->user;
                $now = Carbon::now($user->timezone);
                if (! $now->isMonday() || $now->hour < self::SEND_FROM_HOUR || $now->hour >= self::SEND_UNTIL_HOUR) {
                    continue;
                }
                $weekStart = $now->copy()->subWeek()->startOfWeek(Carbon::MONDAY);
                $weekEnd = $weekStart->copy()->addWeek();
                $cacheKey = 'valo:weekly-summary:'.$recipient->getKey().':'.$weekStart->toDateString();
                if (Cache::has($cacheKey)) {
                    continue;
                }

                $belowExpected = $members
                    ->map(fn (Member $member): array => [
                        'name' => $member->user->name,
                        'seconds' => $reminderService->trackedSeconds($member, $weekStart, $weekEnd),
                    ])
                    ->filter(fn (array $row): bool => $row['seconds'] < $expectedSeconds)
                    ->sortBy('seconds')
                    ->map(fn (array $row): array => [
                        'name' => $row['name'],
                        'hours' => ValoReminderService::formatHours($row['seconds']),
                    ])
                    ->values()
                    ->all();

                $this->info('Sending weekly summary to "'.$user->email.'" ('.$user->getKey().'), '.count($belowExpected).' members below expected hours');
                $sentMails++;
                if (! $dryRun) {
                    Cache::put($cacheKey, true, Carbon::now()->addDays(8));
                    Mail::to($user->email)->queue(new ValoWeeklySummaryMail(
                        $user,
                        $belowExpected,
                        $setting->expected_weekly_hours,
                        $weekStart->format('d/m/Y').' – '.$weekEnd->copy()->subDay()->format('d/m/Y'),
                        $members->count()
                    ));
                }
            }
        }

        $this->comment('Finished sending '.$sentMails.' weekly summaries...');

        return self::SUCCESS;
    }
}
