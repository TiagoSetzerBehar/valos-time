<?php

declare(strict_types=1);

namespace Tests\Unit\Endpoint\Web;

use App\Enums\Role;
use App\Http\Controllers\Web\ValoMeetingController;
use App\Models\TimeEntry;
use App\Models\ValoMeeting;
use Laravel\Passport\Passport;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ValoMeetingController::class)]
class ValoSameHourOnceTest extends EndpointTestAbstract
{
    public function test_ok_on_a_meeting_inside_hours_already_tracked_adds_nothing_and_counts_as_done(): void
    {
        // Arrange: 10:00-12:00 tracked (Madrid), meeting 10:00-11:00
        $data = $this->createUserWithRole(Role::Employee);
        $data->user->timezone = 'Europe/Madrid';
        $data->user->save();
        TimeEntry::factory()->forOrganization($data->organization)->forMember($data->member)->create([
            'start' => '2026-11-18 09:00:00', 'end' => '2026-11-18 11:00:00', 'description' => 'Trabajo',
        ]);
        $meeting = ValoMeeting::create([
            'organization_id' => $data->organization->getKey(), 'name' => 'Knowledge section', 'weekday' => 3,
            'start_time' => '10:00', 'end_time' => '11:00', 'tag_ids' => [], 'member_ids' => null,
        ]);

        // Act
        $ok = $this->actingAs($data->user)->postJson(
            route('valo.meetings.confirm', [$data->organization->getKey(), $meeting->getKey()]),
            ['date' => '2026-11-18']
        );
        $list = $this->actingAs($data->user)->getJson(route('valo.meetings.occurrences', [
            $data->organization->getKey(), 'start' => '2026-11-18', 'end' => '2026-11-18',
        ]));

        // Assert
        $ok->assertOk();
        $this->assertSame(1, TimeEntry::query()->count());
        $this->assertNotNull($list->json('data.0.time_entry_id'));
    }

    public function test_ok_only_adds_the_free_part_of_the_meeting(): void
    {
        // Arrange: 10:30-12:00 tracked (Madrid), meeting 10:00-11:00 -> only 10:00-10:30 is added
        $data = $this->createUserWithRole(Role::Employee);
        $data->user->timezone = 'Europe/Madrid';
        $data->user->save();
        TimeEntry::factory()->forOrganization($data->organization)->forMember($data->member)->create([
            'start' => '2026-11-18 09:30:00', 'end' => '2026-11-18 11:00:00',
        ]);
        $meeting = ValoMeeting::create([
            'organization_id' => $data->organization->getKey(), 'name' => 'Knowledge section', 'weekday' => 3,
            'start_time' => '10:00', 'end_time' => '11:00', 'tag_ids' => [], 'member_ids' => null,
        ]);

        // Act
        $ok = $this->actingAs($data->user)->postJson(
            route('valo.meetings.confirm', [$data->organization->getKey(), $meeting->getKey()]),
            ['date' => '2026-11-18']
        );

        // Assert
        $ok->assertCreated();
        /** @var TimeEntry $added */
        $added = TimeEntry::query()->where('description', '=', 'Knowledge section')->firstOrFail();
        $this->assertSame('2026-11-18 09:00:00', $added->start->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-18 09:30:00', $added->end?->format('Y-m-d H:i:s'));
    }

    public function test_a_new_entry_can_not_overlap_hours_already_tracked(): void
    {
        // Arrange
        $data = $this->createUserWithRole(Role::Employee);
        $data->organization->prevent_overlapping_time_entries = true;
        $data->organization->save();
        TimeEntry::factory()->forOrganization($data->organization)->forMember($data->member)->create([
            'start' => '2026-11-18 09:00:00', 'end' => '2026-11-18 11:00:00',
        ]);

        Passport::actingAs($data->user);

        // Act
        $overlapping = $this->postJson(route('api.v1.time-entries.store', [$data->organization->getKey()]), [
            'member_id' => $data->member->getKey(), 'start' => '2026-11-18T10:00:00Z', 'end' => '2026-11-18T12:00:00Z', 'billable' => false,
        ]);
        $after = $this->postJson(route('api.v1.time-entries.store', [$data->organization->getKey()]), [
            'member_id' => $data->member->getKey(), 'start' => '2026-11-18T11:00:00Z', 'end' => '2026-11-18T12:00:00Z', 'billable' => false,
        ]);

        // Assert
        $overlapping->assertStatus(400);
        $after->assertCreated();
        $this->assertSame(2, TimeEntry::query()->count());
    }
}
