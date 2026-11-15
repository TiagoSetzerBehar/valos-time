<?php

declare(strict_types=1);

namespace Tests\Unit\Endpoint\Web;

use App\Enums\Role;
use App\Http\Controllers\Web\ValoWeeklySummaryController;
use App\Models\Member;
use App\Models\TimeEntry;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ValoWeeklySummaryController::class)]
class ValoWeeklySummaryEndpointTest extends EndpointTestAbstract
{
    public function test_the_summary_compares_the_week_and_leaves_running_timers_apart(): void
    {
        // Arrange
        $data = $this->createUserWithRole(Role::Admin);
        $data->user->timezone = 'Europe/Madrid';
        $data->user->save();
        $other = Member::factory()->forOrganization($data->organization)->role(Role::Employee)->create();
        // this week (Mon 9 Nov 2026): 2 h; last week: 1 h
        TimeEntry::factory()->forMember($data->member)->create(['start' => '2026-11-10 08:00:00', 'end' => '2026-11-10 10:00:00']);
        TimeEntry::factory()->forMember($data->member)->create(['start' => '2026-11-03 08:00:00', 'end' => '2026-11-03 09:00:00']);
        // a timer still running does not count
        TimeEntry::factory()->forMember($other)->create(['start' => '2026-11-12 08:00:00', 'end' => null]);

        // Act
        $response = $this->actingAs($data->user)->getJson(route('valo.weekly-summary', [
            $data->organization->getKey(), 'week_start' => '2026-11-09',
        ]));

        // Assert
        $response->assertOk();
        $html = (string) $response->json('html');
        $this->assertStringContainsString('Resumen de la semana', $html);
        $this->assertStringContainsString('2 h', $html);
        $this->assertStringContainsString('▲ 100 %', $html);
        $this->assertStringContainsString('Cronómetro sin parar', $html);
        $this->assertStringContainsString($other->user->name, $html);
        $this->assertSame('resumen-semana-46-2026-11-09.pdf', $response->json('filename'));
    }

    public function test_employees_can_not_get_the_team_summary(): void
    {
        // Arrange
        $data = $this->createUserWithRole(Role::Employee);

        // Act
        $response = $this->actingAs($data->user)->getJson(route('valo.weekly-summary', [
            $data->organization->getKey(), 'week_start' => '2026-11-09',
        ]));

        // Assert
        $response->assertForbidden();
    }
}
