<?php

declare(strict_types=1);

namespace App\Console\Commands\Valo;

use App\Enums\TimeEntryType;
use App\Mail\ValoMemberWeeklyReportMail;
use App\Models\TimeEntry;
use App\Models\ValoReminderSetting;
use App\Service\ValoReminderService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class ValoSendMemberWeeklyReportsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'valo:send-member-weekly-reports '.
        ' { --dry-run : Do not actually send emails, just output what would happen }';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Emails every member on Monday morning the hours they logged last week, per project.';

    private const int SEND_FROM_HOUR = 9;

    private const int SEND_UNTIL_HOUR = 12;

    public function handle(ValoReminderService $reminderService): int
    {
        $this->comment('Sending member weekly reports...');
        $dryRun = (bool) $this->option('dry-run');
        $sentMails = 0;

        $settings = ValoReminderSetting::query()
            ->where('member_weekly_enabled', '=', true)
            ->with('organization')
            ->get();

        foreach ($settings as $setting) {
            foreach ($reminderService->trackableMembers($setting->organization) as $member) {
                $user = $member->user;
                $now = Carbon::now($user->timezone);
                if (! $now->isMonday() || $now->hour < self::SEND_FROM_HOUR || $now->hour >= self::SEND_UNTIL_HOUR) {
                    continue;
                }
                $weekStart = $now->copy()->subWeek()->startOfWeek(Carbon::MONDAY);
                $weekEnd = $weekStart->copy()->addWeek();
                $cacheKey = 'valo:member-weekly-report:'.$member->getKey().':'.$weekStart->toDateString();
                if (Cache::has($cacheKey)) {
                    continue;
                }

                $secondsByProject = [];
                $timeEntries = TimeEntry::query()
                    ->where('member_id', '=', $member->getKey())
                    ->where('type', '!=', TimeEntryType::Break->value)
                    ->whereNotNull('end')
                    ->where('start', '>=', $weekStart->copy()->utc())
                    ->where('start', '<', $weekEnd->copy()->utc())
                    ->with('project')
                    ->get();
                foreach ($timeEntries as $timeEntry) {
                    $projectName = $timeEntry->project?->name ?? __('No Project');
                    $seconds = (int) $timeEntry->start->diffInSeconds($timeEntry->end, true);
                    $secondsByProject[$projectName] = ($secondsByProject[$projectName] ?? 0) + $seconds;
                }
                arsort($secondsByProject);
                $rows = [];
                foreach ($secondsByProject as $projectName => $seconds) {
                    $rows[] = ['project' => (string) $projectName, 'hours' => ValoReminderService::formatHours($seconds)];
                }

                $this->info('Sending weekly report to "'.$user->email.'" ('.$user->getKey().'), '.count($rows).' projects');
                $sentMails++;
                if (! $dryRun) {
                    Cache::put($cacheKey, true, Carbon::now()->addDays(8));
                    Mail::to($user->email)->queue(new ValoMemberWeeklyReportMail(
                        $user,
                        $rows,
                        ValoReminderService::formatHours(array_sum($secondsByProject)),
                        $weekStart->format('d/m/Y').' – '.$weekEnd->copy()->subDay()->format('d/m/Y')
                    ));
                }
            }
        }

        $this->comment('Finished sending '.$sentMails.' member weekly reports...');

        return self::SUCCESS;
    }
}
