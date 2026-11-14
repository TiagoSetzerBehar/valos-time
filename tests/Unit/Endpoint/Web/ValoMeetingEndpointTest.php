<?php

declare(strict_types=1);

namespace Tests\Unit\Endpoint\Web;

use App\Enums\Role;
use App\Http\Controllers\Web\ValoMeetingController;
use App\Models\Member;
use App\Models\Tag;
use App\Models\TimeEntry;
use App\Models\ValoMeeting;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ValoMeetingController::class)]
class ValoMeetingEndpointTest extends EndpointTestAbstract
{
    public function test_members_see_their_weekly_meetings_and_only_the_ones_they_belong_to(): void
    {
        // Arrange
        $data = $this->createUserWithRole(Role::Employee);
        $data->user->timezone = 'Europe/Madrid';
        $data->user->save();
        $other = Member::factory()->forOrganization($data->organization)->create();
        ValoMeeting::create([
            'organization_id' => $data->organization->getKey(), 'name' => 'TSA Tech', 'weekday' => 1,
            'start_time' => '16:00', 'end_time' => '17:00', 'tag_ids' => [], 'member_ids' => null,
        ]);
        ValoMeeting::create([
            'organization_id' => $data->organization->getKey(), 'name' => 'TLT', 'weekday' => 3,
            'start_time' => '12:00', 'end_time' => '14:00', 'tag_ids' => [], 'member_ids' => [$other->getKey()],
        ]);

        // Act
        $response = $this->actingAs($data->user)->getJson(route('valo.meetings.occurrences', [
            $data->organization->getKey(), 'start' => '2026-11-09', 'end' => '2026-11-15',
        ]));

        // Assert
        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $response->assertJsonPath('data.0.name', 'TSA Tech');
        $response->assertJsonPath('data.0.date', '2026-11-09');
        // 16:00 in Madrid (winter, UTC+1) is 15:00 UTC
        $response->assertJsonPath('data.0.start', '2026-11-09T15:00:00Z');
        $response->assertJsonPath('data.0.time_entry_id', null);
    }

    public function test_ok_creates_the_time_entry_with_the_meeting_tags_and_marks_it_confirmed(): void
    {
        // Arrange
        $data = $this->createUserWithRole(Role::Employee);
        $data->user->timezone = 'Europe/Madrid';
        $data->user->save();
        $tag = Tag::factory()->forOrganization($data->organization)->create(['name' => 'tsa']);
        $meeting = ValoMeeting::create([
            'organization_id' => $data->organization->getKey(), 'name' => 'TSA Tech', 'weekday' => 1,
            'start_time' => '16:00', 'end_time' => '17:00', 'tag_ids' => [$tag->getKey()], 'member_ids' => null,
        ]);
        $url = route('valo.meetings.confirm', [$data->organization->getKey(), $meeting->getKey()]);

        // Act
        $first = $this->actingAs($data->user)->postJson($url, ['date' => '2026-11-09', 'start_time' => '16:15']);
        $again = $this->actingAs($data->user)->postJson($url, ['date' => '2026-11-09']);
        $list = $this->actingAs($data->user)->getJson(route('valo.meetings.occurrences', [
            $data->organization->getKey(), 'start' => '2026-11-09', 'end' => '2026-11-09',
        ]));

        // Assert
        $first->assertCreated();
        $again->assertOk();
        $this->assertSame(1, TimeEntry::query()->count());
        /** @var TimeEntry $entry */
        $entry = TimeEntry::query()->firstOrFail();
        $this->assertSame('TSA Tech', $entry->description);
        $this->assertSame([$tag->getKey()], $entry->tags);
        $this->assertNull($entry->project_id);
        $this->assertSame($data->member->getKey(), $entry->member_id);
        $this->assertSame('2026-11-09 15:15:00', $entry->start->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-09 16:00:00', $entry->end?->format('Y-m-d H:i:s'));
        $list->assertJsonPath('data.0.time_entry_id', $entry->getKey());
    }

    public function test_a_meeting_logged_by_hand_counts_whatever_the_spelling_or_with_its_tag_during_the_meeting(): void
    {
        // Arrange
        $data = $this->createUserWithRole(Role::Employee);
        $data->user->timezone = 'Europe/Madrid';
        $data->user->save();
        $tsa = Tag::factory()->forOrganization($data->organization)->create(['name' => 'tsa']);
        $legal = ValoMeeting::create([
            'organization_id' => $data->organization->getKey(), 'name' => 'TSA Legal', 'weekday' => 2,
            'start_time' => '10:00', 'end_time' => '11:00', 'tag_ids' => [$tsa->getKey()], 'member_ids' => null,
        ]);
        $finanzas = ValoMeeting::create([
            'organization_id' => $data->organization->getKey(), 'name' => 'TSA Finanzas', 'weekday' => 4,
            'start_time' => '14:00', 'end_time' => '15:00', 'tag_ids' => [$tsa->getKey()], 'member_ids' => null,
        ]);
        $tech = ValoMeeting::create([
            'organization_id' => $data->organization->getKey(), 'name' => 'TSA Tech', 'weekday' => 1,
            'start_time' => '16:00', 'end_time' => '17:00', 'tag_ids' => [$tsa->getKey()], 'member_ids' => null,
        ]);
        // Tuesday: written in lowercase (10:00-11:00 Madrid = 09:00-10:00 UTC)
        $byName = TimeEntry::factory()->forMember($data->member)->create([
            'description' => '  tsa  legal ', 'start' => '2026-11-10 09:00:00', 'end' => '2026-11-10 10:00:00', 'tags' => [],
        ]);
        // Thursday: another name, but during the meeting and with its tag
        $byTag = TimeEntry::factory()->forMember($data->member)->create([
            'description' => 'finanzas con Paul', 'start' => '2026-11-12 13:10:00', 'end' => '2026-11-12 14:00:00', 'tags' => [$tsa->getKey()],
        ]);
        // Monday: same hour but no tag and another name -> still pending
        TimeEntry::factory()->forMember($data->member)->create([
            'description' => 'OBV', 'start' => '2026-11-09 15:00:00', 'end' => '2026-11-09 16:00:00', 'tags' => [],
        ]);

        // Act
        $response = $this->actingAs($data->user)->getJson(route('valo.meetings.occurrences', [
            $data->organization->getKey(), 'start' => '2026-11-09', 'end' => '2026-11-15',
        ]));
        $ok = $this->actingAs($data->user)->postJson(
            route('valo.meetings.confirm', [$data->organization->getKey(), $legal->getKey()]), ['date' => '2026-11-10']);

        // Assert
        $byMeeting = collect($response->json('data'))->keyBy('meeting_id');
        $this->assertSame($byName->getKey(), $byMeeting[$legal->getKey()]['time_entry_id']);
        $this->assertSame($byTag->getKey(), $byMeeting[$finanzas->getKey()]['time_entry_id']);
        $this->assertNull($byMeeting[$tech->getKey()]['time_entry_id']);
        $ok->assertOk()->assertJsonPath('data.time_entry_id', $byName->getKey());
        $this->assertSame(3, TimeEntry::query()->count());
    }

    public function test_members_can_not_confirm_meetings_they_do_not_belong_to_or_on_another_weekday(): void
    {
        // Arrange
        $data = $this->createUserWithRole(Role::Employee);
        $other = Member::factory()->forOrganization($data->organization)->create();
        $tlt = ValoMeeting::create([
            'organization_id' => $data->organization->getKey(), 'name' => 'TLT', 'weekday' => 3,
            'start_time' => '12:00', 'end_time' => '14:00', 'tag_ids' => [], 'member_ids' => [$other->getKey()],
        ]);
        $tech = ValoMeeting::create([
            'organization_id' => $data->organization->getKey(), 'name' => 'TSA Tech', 'weekday' => 1,
            'start_time' => '16:00', 'end_time' => '17:00', 'tag_ids' => [], 'member_ids' => null,
        ]);

        // Act
        $notMember = $this->actingAs($data->user)->postJson(
            route('valo.meetings.confirm', [$data->organization->getKey(), $tlt->getKey()]), ['date' => '2026-11-11']);
        $wrongDay = $this->actingAs($data->user)->postJson(
            route('valo.meetings.confirm', [$data->organization->getKey(), $tech->getKey()]), ['date' => '2026-11-10']);

        // Assert
        $notMember->assertForbidden();
        $wrongDay->assertStatus(422);
        $this->assertSame(0, TimeEntry::query()->count());
    }

    public function test_only_owners_and_admins_manage_the_meetings(): void
    {
        // Arrange
        $owner = $this->createUserWithRole(Role::Owner);
        $employee = Member::factory()->forOrganization($owner->organization)->role(Role::Employee)->create();
        $url = route('valo.meetings.store', $owner->organization->getKey());
        $payload = ['name' => 'TLS', 'weekday' => 4, 'start_time' => '09:00', 'end_time' => '12:00', 'tag_ids' => [], 'member_ids' => null];

        // Act
        $forbidden = $this->actingAs($employee->user)->postJson($url, $payload);
        $created = $this->actingAs($owner->user)->postJson($url, $payload);
        $invalid = $this->actingAs($owner->user)->postJson($url, [...$payload, 'end_time' => '08:00']);
        $id = $created->json('data.id');
        $updated = $this->actingAs($owner->user)->putJson(
            route('valo.meetings.update', [$owner->organization->getKey(), $id]), [...$payload, 'name' => 'TLS jueves']);
        $deleted = $this->actingAs($owner->user)->deleteJson(
            route('valo.meetings.destroy', [$owner->organization->getKey(), $id]));

        // Assert
        $forbidden->assertForbidden();
        $created->assertCreated()->assertJsonPath('data.name', 'TLS');
        $invalid->assertStatus(422);
        $updated->assertOk()->assertJsonPath('data.name', 'TLS jueves');
        $deleted->assertNoContent();
        $this->assertSame(0, ValoMeeting::query()->count());
    }
}
