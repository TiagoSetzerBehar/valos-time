<?php

declare(strict_types=1);

namespace Tests\Unit\Endpoint\Web;

use App\Enums\Role;
use App\Http\Controllers\Web\ValoLongEntryController;
use App\Models\Member;
use App\Models\TimeEntry;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ValoLongEntryController::class)]
class ValoLongEntryEndpointTest extends EndpointTestAbstract
{
    public function test_managers_see_the_forgotten_timers_of_the_week_and_employees_only_their_own(): void
    {
        // Arrange
        Carbon::setTestNow('2026-11-18 10:00:00');
        $data = $this->createUserWithRole(Role::Admin);
        $data->user->timezone = 'Europe/Madrid';
        $data->user->save();
        $other = Member::factory()->forOrganization($data->organization)->role(Role::Employee)->create();
        $other->user->timezone = 'Europe/Madrid';
        $other->user->save();
        // 20 h in a row -> to review; 8 h -> normal; running since yesterday morning -> to review
        $long = TimeEntry::factory()->forMember($other)->create(['start' => '2026-11-12 08:00:00', 'end' => '2026-11-13 04:00:00']);
        TimeEntry::factory()->forMember($other)->create(['start' => '2026-11-11 08:00:00', 'end' => '2026-11-11 16:00:00']);
        $running = TimeEntry::factory()->forMember($data->member)->create(['start' => '2026-11-17 08:00:00', 'end' => null]);

        // Act
        $lastWeek = $this->actingAs($data->user)->getJson(route('valo.long-entries.index', [
            $data->organization->getKey(), 'start' => '2026-11-09', 'end' => '2026-11-15',
        ]));
        $thisWeek = $this->actingAs($data->user)->getJson(route('valo.long-entries.index', [
            $data->organization->getKey(), 'start' => '2026-11-16', 'end' => '2026-11-22',
        ]));
        $asEmployee = $this->actingAs($other->user)->getJson(route('valo.long-entries.index', [
            $data->organization->getKey(), 'start' => '2026-11-16', 'end' => '2026-11-22',
        ]));

        // Assert
        $lastWeek->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $long->getKey())
            ->assertJsonPath('data.0.day', '2026-11-12')
            ->assertJsonPath('data.0.seconds', 20 * 3600)
            ->assertJsonPath('data.0.running', false)
            ->assertJsonPath('data.0.can_fix', true);
        $thisWeek->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $running->getKey())
            ->assertJsonPath('data.0.running', true)
            ->assertJsonPath('data.0.seconds', 26 * 3600);
        $asEmployee->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_the_real_end_of_a_forgotten_timer_can_be_set_by_its_owner_or_a_manager_only(): void
    {
        // Arrange
        Carbon::setTestNow('2026-11-18 10:00:00');
        $data = $this->createUserWithRole(Role::Employee);
        $other = Member::factory()->forOrganization($data->organization)->role(Role::Employee)->create();
        $mine = TimeEntry::factory()->forMember($data->member)->create(['start' => '2026-11-17 08:00:00', 'end' => null]);
        $theirs = TimeEntry::factory()->forMember($other)->create(['start' => '2026-11-12 08:00:00', 'end' => '2026-11-13 04:00:00']);

        // Act
        $ok = $this->actingAs($data->user)->putJson(
            route('valo.long-entries.update', [$data->organization->getKey(), $mine->getKey()]), ['end' => '2026-11-17T16:30:00Z']);
        $beforeStart = $this->actingAs($data->user)->putJson(
            route('valo.long-entries.update', [$data->organization->getKey(), $mine->getKey()]), ['end' => '2026-11-17T07:00:00Z']);
        $notMine = $this->actingAs($data->user)->putJson(
            route('valo.long-entries.update', [$data->organization->getKey(), $theirs->getKey()]), ['end' => '2026-11-12T16:00:00Z']);

        // Assert
        $ok->assertOk();
        $beforeStart->assertStatus(422);
        $notMine->assertForbidden();
        $this->assertSame('2026-11-17 16:30:00', $mine->refresh()->end->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-13 04:00:00', $theirs->refresh()->end->format('Y-m-d H:i:s'));
    }
}
