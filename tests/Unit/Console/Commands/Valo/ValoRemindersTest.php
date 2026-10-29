<?php

declare(strict_types=1);

namespace Tests\Unit\Console\Commands\Valo;

use App\Console\Commands\Valo\ValoSendDailyRemindersCommand;
use App\Console\Commands\Valo\ValoSendWeeklySummaryCommand;
use App\Enums\Role;
use App\Mail\ValoDailyReminderMail;
use App\Mail\ValoWeeklySummaryMail;
use App\Models\Member;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\ValoReminderSetting;
use App\Service\ValoReminderService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCaseWithDatabase;

#[CoversClass(ValoSendDailyRemindersCommand::class)]
#[CoversClass(ValoSendWeeklySummaryCommand::class)]
#[CoversClass(ValoReminderService::class)]
class ValoRemindersTest extends TestCaseWithDatabase
{
    public function test_daily_reminder_is_sent_to_members_without_hours_today(): void
    {
        // Arrange: Wednesday 18:05 in Madrid
        $this->travelTo(Carbon::parse('2026-10-28 18:05', 'Europe/Madrid'));
        $data = $this->createUserWithRole(Role::Owner);
        $data->user->update(['timezone' => 'Europe/Madrid']);
        $memberWithHours = Member::factory()->forOrganization($data->organization)
            ->forUser(User::factory()->create(['timezone' => 'Europe/Madrid']))->create();
        TimeEntry::factory()->forMember($memberWithHours)->create([
            'start' => Carbon::parse('2026-10-28 09:00', 'Europe/Madrid')->utc(),
            'end' => Carbon::parse('2026-10-28 11:00', 'Europe/Madrid')->utc(),
        ]);
        ValoReminderSetting::forOrganization($data->organization)->fill(['daily_enabled' => true, 'daily_time' => '18:00'])->save();

        // Act
        $exitCode = $this->withoutMockingConsoleOutput()->artisan('valo:send-daily-reminders');

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        Mail::assertQueued(ValoDailyReminderMail::class, 1);
        Mail::assertQueued(ValoDailyReminderMail::class, fn (ValoDailyReminderMail $mail): bool => $mail->hasTo($data->user->email));
    }

    public function test_daily_reminder_is_not_sent_before_the_reminder_time_twice_or_on_weekends(): void
    {
        // Arrange
        $data = $this->createUserWithRole(Role::Owner);
        $data->user->update(['timezone' => 'Europe/Madrid']);
        ValoReminderSetting::forOrganization($data->organization)->fill(['daily_enabled' => true, 'daily_time' => '18:00'])->save();

        // Act: before the reminder time, on a Saturday, then twice on a weekday
        $this->travelTo(Carbon::parse('2026-10-28 17:30', 'Europe/Madrid'));
        $this->artisan('valo:send-daily-reminders');
        $this->travelTo(Carbon::parse('2026-10-31 18:30', 'Europe/Madrid'));
        $this->artisan('valo:send-daily-reminders');
        $this->travelTo(Carbon::parse('2026-10-28 18:15', 'Europe/Madrid'));
        $this->artisan('valo:send-daily-reminders');
        $this->artisan('valo:send-daily-reminders');

        // Assert
        Mail::assertQueued(ValoDailyReminderMail::class, 1);
    }

    public function test_daily_reminder_is_not_sent_when_disabled(): void
    {
        // Arrange
        $this->travelTo(Carbon::parse('2026-10-28 18:05', 'Europe/Madrid'));
        $data = $this->createUserWithRole(Role::Owner);
        $data->user->update(['timezone' => 'Europe/Madrid']);
        ValoReminderSetting::forOrganization($data->organization)->fill(['daily_enabled' => false])->save();

        // Act
        $this->artisan('valo:send-daily-reminders');

        // Assert
        Mail::assertNothingQueued();
    }

    public function test_weekly_summary_lists_members_below_expected_hours_to_owners(): void
    {
        // Arrange: Monday 09:30 in Madrid, last week was 2026-10-19 to 2026-10-25
        $this->travelTo(Carbon::parse('2026-10-26 09:30', 'Europe/Madrid'));
        $owner = $this->createUserWithRole(Role::Owner);
        $owner->user->update(['timezone' => 'Europe/Madrid']);
        $hardWorker = Member::factory()->forOrganization($owner->organization)->role(Role::Employee)
            ->forUser(User::factory()->create(['name' => 'Ane', 'timezone' => 'Europe/Madrid']))->create();
        TimeEntry::factory()->forMember($hardWorker)->create([
            'start' => Carbon::parse('2026-10-20 08:00', 'Europe/Madrid')->utc(),
            'end' => Carbon::parse('2026-10-20 20:00', 'Europe/Madrid')->utc(),
        ]);
        $slacker = Member::factory()->forOrganization($owner->organization)->role(Role::Employee)
            ->forUser(User::factory()->create(['name' => 'Iker', 'timezone' => 'Europe/Madrid']))->create();
        TimeEntry::factory()->forMember($slacker)->create([
            'start' => Carbon::parse('2026-10-21 09:00', 'Europe/Madrid')->utc(),
            'end' => Carbon::parse('2026-10-21 11:30', 'Europe/Madrid')->utc(),
        ]);
        ValoReminderSetting::forOrganization($owner->organization)
            ->fill(['weekly_enabled' => true, 'expected_weekly_hours' => 10])->save();

        // Act
        $this->artisan('valo:send-weekly-summary');
        $this->artisan('valo:send-weekly-summary');

        // Assert: only the owner gets it, once, listing Iker (2:30) and the owner (0:00) but not Ane (12:00)
        Mail::assertQueued(ValoWeeklySummaryMail::class, 1);
        Mail::assertQueued(ValoWeeklySummaryMail::class, function (ValoWeeklySummaryMail $mail) use ($owner): bool {
            $names = array_column($mail->belowExpected, 'name');

            return $mail->hasTo($owner->user->email)
                && in_array('Iker', $names, true)
                && ! in_array('Ane', $names, true)
                && in_array(['name' => 'Iker', 'hours' => '2:30'], $mail->belowExpected, true)
                && $mail->expectedHours === 10;
        });
    }

    public function test_reminder_settings_can_only_be_changed_by_owners_and_admins(): void
    {
        // Arrange
        $owner = $this->createUserWithRole(Role::Owner);
        $employee = Member::factory()->forOrganization($owner->organization)->role(Role::Employee)->create();
        $url = route('valo.reminders.update', $owner->organization->getKey());
        $payload = ['daily_enabled' => true, 'daily_time' => '17:30', 'weekly_enabled' => true, 'expected_weekly_hours' => 25];

        // Act
        $forbidden = $this->actingAs($employee->user)->putJson($url, $payload);
        $allowed = $this->actingAs($owner->user)->putJson($url, $payload);

        // Assert
        $forbidden->assertForbidden();
        $allowed->assertOk()->assertJson($payload);
        $this->assertTrue(ValoReminderSetting::forOrganization($owner->organization)->daily_enabled);
    }
}
