<?php

declare(strict_types=1);

namespace Tests\Unit\Console\Commands\Valo;

use App\Console\Commands\Valo\ValoSendMemberWeeklyReportsCommand;
use App\Enums\Role;
use App\Mail\ValoMemberWeeklyReportMail;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\ValoReminderSetting;
use App\Service\ValoTimeEntryRules;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Laravel\Passport\Passport;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCaseWithDatabase;

#[CoversClass(ValoSendMemberWeeklyReportsCommand::class)]
#[CoversClass(ValoTimeEntryRules::class)]
class ValoBlock4Test extends TestCaseWithDatabase
{
    public function test_finished_time_entry_without_project_is_rejected_when_project_is_required(): void
    {
        // Arrange
        $data = $this->createUserWithRole(Role::Owner);
        ValoReminderSetting::forOrganization($data->organization)->fill(['require_project' => true])->save();
        $project = Project::factory()->forOrganization($data->organization)->create();
        Passport::actingAs($data->user);
        $url = route('api.v1.time-entries.store', [$data->organization->getKey()]);
        $payload = [
            'description' => 'Reunión',
            'billable' => false,
            'start' => Carbon::now()->subHour()->toIso8601ZuluString(),
            'end' => Carbon::now()->toIso8601ZuluString(),
            'tags' => [],
            'member_id' => $data->member->getKey(),
            'project_id' => null,
            'task_id' => null,
        ];

        // Act
        $withoutProject = $this->postJson($url, $payload);
        $running = $this->postJson($url, [...$payload, 'end' => null]);
        $withProject = $this->postJson($url, [...$payload, 'end' => Carbon::now()->subMinutes(30)->toIso8601ZuluString(), 'start' => Carbon::now()->subMinutes(50)->toIso8601ZuluString(), 'project_id' => $project->getKey()]);

        // Assert
        $withoutProject->assertStatus(422)->assertJsonValidationErrors(['project_id']);
        $running->assertSuccessful();
        $withProject->assertSuccessful();
    }

    public function test_member_weekly_report_lists_hours_per_project(): void
    {
        // Arrange: Monday 09:30 in Madrid, last week was 2026-10-19 to 2026-10-25
        $this->travelTo(Carbon::parse('2026-10-26 09:30', 'Europe/Madrid'));
        $data = $this->createUserWithRole(Role::Owner);
        $data->user->update(['timezone' => 'Europe/Madrid']);
        $project = Project::factory()->forOrganization($data->organization)->create(['name' => 'Web Bilbao']);
        TimeEntry::factory()->forMember($data->member)->forProject($project)->create([
            'start' => Carbon::parse('2026-10-20 09:00', 'Europe/Madrid')->utc(),
            'end' => Carbon::parse('2026-10-20 12:15', 'Europe/Madrid')->utc(),
        ]);
        ValoReminderSetting::forOrganization($data->organization)->fill(['member_weekly_enabled' => true])->save();

        // Act
        $this->artisan('valo:send-member-weekly-reports');
        $this->artisan('valo:send-member-weekly-reports');

        // Assert
        Mail::assertQueued(ValoMemberWeeklyReportMail::class, 1);
        Mail::assertQueued(ValoMemberWeeklyReportMail::class, fn (ValoMemberWeeklyReportMail $mail): bool => $mail->hasTo($data->user->email)
            && $mail->rows === [['project' => 'Web Bilbao', 'hours' => '3:15']]
            && $mail->total === '3:15');
    }
}
