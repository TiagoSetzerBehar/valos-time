<?php

declare(strict_types=1);

namespace Tests\Unit\Endpoint\Web;

use App\Enums\Role;
use App\Http\Controllers\Web\ValoMeetingController;
use App\Models\Member;
use App\Models\ValoMeeting;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ValoMeetingController::class)]
class ValoRoutineEndpointTest extends EndpointTestAbstract
{
    public function test_a_member_creates_a_routine_that_only_appears_in_their_own_calendar(): void
    {
        // Arrange
        $data = $this->createUserWithRole(Role::Employee);
        $data->user->timezone = 'Europe/Madrid';
        $data->user->save();
        $other = $this->createUserWithRole(Role::Employee);
        $otherMember = Member::factory()->forOrganization($data->organization)->create(['user_id' => $other->user->getKey()]);

        // Act
        $created = $this->actingAs($data->user)->postJson(route('valo.routines.store', [$data->organization->getKey()]), [
            'name' => 'Gym', 'weekday' => 1, 'start_time' => '08:00', 'end_time' => '09:00', 'tag_ids' => [],
        ]);
        $mine = $this->actingAs($data->user)->getJson(route('valo.meetings.occurrences', [
            $data->organization->getKey(), 'start' => '2026-11-09', 'end' => '2026-11-15',
        ]));
        $list = $this->actingAs($data->user)->getJson(route('valo.routines.index', [$data->organization->getKey()]));

        // Assert
        $created->assertCreated();
        $created->assertJsonPath('data.name', 'Gym');
        /** @var ValoMeeting $routine */
        $routine = ValoMeeting::query()->firstOrFail();
        $this->assertSame($data->member->getKey(), $routine->created_by_member_id);
        $this->assertSame([$data->member->getKey()], $routine->member_ids);
        $this->assertFalse($routine->isForMember($otherMember));
        $mine->assertOk();
        $this->assertCount(1, $mine->json('data'));
        $mine->assertJsonPath('data.0.name', 'Gym');
        $list->assertOk();
        $this->assertCount(1, $list->json('data'));
    }

    public function test_a_member_can_not_change_or_delete_a_routine_of_someone_else_or_a_team_meeting(): void
    {
        // Arrange
        $data = $this->createUserWithRole(Role::Employee);
        $otherMember = Member::factory()->forOrganization($data->organization)->create();
        $theirs = ValoMeeting::create([
            'organization_id' => $data->organization->getKey(), 'name' => 'Their routine', 'weekday' => 2,
            'start_time' => '08:00', 'end_time' => '09:00', 'tag_ids' => [], 'member_ids' => [$otherMember->getKey()],
            'created_by_member_id' => $otherMember->getKey(),
        ]);
        $team = ValoMeeting::create([
            'organization_id' => $data->organization->getKey(), 'name' => 'TSA Tech', 'weekday' => 1,
            'start_time' => '16:00', 'end_time' => '17:00', 'tag_ids' => [], 'member_ids' => null,
        ]);
        $body = ['name' => 'Changed', 'weekday' => 2, 'start_time' => '08:00', 'end_time' => '09:00', 'tag_ids' => []];

        // Act
        $updateTheirs = $this->actingAs($data->user)->putJson(route('valo.routines.update', [$data->organization->getKey(), $theirs->getKey()]), $body);
        $deleteTeam = $this->actingAs($data->user)->deleteJson(route('valo.routines.destroy', [$data->organization->getKey(), $team->getKey()]));
        $list = $this->actingAs($data->user)->getJson(route('valo.routines.index', [$data->organization->getKey()]));

        // Assert
        $updateTheirs->assertNotFound();
        $deleteTeam->assertNotFound();
        $this->assertSame('Their routine', $theirs->refresh()->name);
        $this->assertSame(2, ValoMeeting::query()->count());
        $this->assertCount(0, $list->json('data'));
    }

    public function test_the_owner_of_a_routine_changes_and_deletes_it_and_admins_do_not_see_it_in_the_team_list(): void
    {
        // Arrange
        $data = $this->createUserWithRole(Role::Owner);
        $routine = ValoMeeting::create([
            'organization_id' => $data->organization->getKey(), 'name' => 'Gym', 'weekday' => 1,
            'start_time' => '08:00', 'end_time' => '09:00', 'tag_ids' => [], 'member_ids' => [$data->member->getKey()],
            'created_by_member_id' => $data->member->getKey(),
        ]);

        // Act
        $teamList = $this->actingAs($data->user)->getJson(route('valo.meetings.index', [$data->organization->getKey()]));
        $updated = $this->actingAs($data->user)->putJson(route('valo.routines.update', [$data->organization->getKey(), $routine->getKey()]), [
            'name' => 'Gym 2', 'weekday' => 5, 'start_time' => '07:30', 'end_time' => '08:30', 'tag_ids' => [],
        ]);
        $deleted = $this->actingAs($data->user)->deleteJson(route('valo.routines.destroy', [$data->organization->getKey(), $routine->getKey()]));

        // Assert
        $this->assertCount(0, $teamList->json('data'));
        $updated->assertOk();
        $updated->assertJsonPath('data.weekday', 5);
        $deleted->assertNoContent();
        $this->assertSame(0, ValoMeeting::query()->count());
    }
}
