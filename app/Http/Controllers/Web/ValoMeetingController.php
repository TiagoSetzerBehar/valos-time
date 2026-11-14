<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\Role;
use App\Enums\TimeEntryType;
use App\Models\Member;
use App\Models\Organization;
use App\Models\Tag;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\ValoMeeting;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * VALO's Time: weekly meetings. Members see them prepared in their calendar and confirm ("OK") the ones
 * they attended, which creates the time entry; owners and admins manage the list.
 */
class ValoMeetingController extends Controller
{
    /**
     * Meetings of the current member between two local dates (inclusive), with whether each one was confirmed.
     */
    public function occurrences(Request $request, string $organizationId): JsonResponse
    {
        [$organization, $member, $user] = $this->currentMember($organizationId);
        /** @var array{start: string, end: string} $data */
        $data = $request->validate([
            'start' => ['required', 'date_format:Y-m-d'],
            'end' => ['required', 'date_format:Y-m-d', 'after_or_equal:start'],
        ]);
        $timezone = $user->timezone;
        $from = CarbonImmutable::createFromFormat('Y-m-d', $data['start'], $timezone)->startOfDay();
        $to = CarbonImmutable::createFromFormat('Y-m-d', $data['end'], $timezone)->startOfDay();
        abort_if($from->diffInDays($to) > 62, 422, 'Range too long');

        $meetings = ValoMeeting::query()
            ->where('organization_id', '=', $organization->getKey())
            ->orderBy('start_time')
            ->get()
            ->filter(fn (ValoMeeting $meeting): bool => $meeting->isForMember($member));

        $confirmed = TimeEntry::query()
            ->where('member_id', '=', $member->getKey())
            ->whereIn('description', $meetings->pluck('name')->unique()->values()->all())
            ->where('start', '>=', $from->utc())
            ->where('start', '<', $to->addDay()->utc())
            ->get(['id', 'description', 'start'])
            ->map(fn (TimeEntry $entry): array => [
                'key' => $entry->description.'|'.$entry->start->copy()->setTimezone($timezone)->format('Y-m-d'),
                'id' => $entry->getKey(),
            ])
            ->pluck('id', 'key');

        $occurrences = [];
        for ($day = $from; $day->lte($to); $day = $day->addDay()) {
            foreach ($meetings as $meeting) {
                if ($meeting->weekday !== $day->isoWeekday()) {
                    continue;
                }
                $date = $day->format('Y-m-d');
                $occurrences[] = [
                    'meeting_id' => $meeting->getKey(),
                    'name' => $meeting->name,
                    'date' => $date,
                    'start' => $this->utc($date, $meeting->start_time, $timezone),
                    'end' => $this->utc($date, $meeting->end_time, $timezone),
                    'start_time' => $meeting->start_time,
                    'end_time' => $meeting->end_time,
                    'tag_ids' => $meeting->tag_ids,
                    'time_entry_id' => $confirmed->get($meeting->name.'|'.$date),
                ];
            }
        }

        return response()->json(['data' => $occurrences]);
    }

    /**
     * "OK, I attended": creates the time entry of the meeting for the current member (times can be adjusted).
     */
    public function confirm(Request $request, string $organizationId, string $meetingId): JsonResponse
    {
        [$organization, $member, $user] = $this->currentMember($organizationId);
        $meeting = $this->meeting($organization, $meetingId);
        abort_unless($meeting->isForMember($member), 403);

        /** @var array{date: string, start_time?: string|null, end_time?: string|null} $data */
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
        ]);
        $timezone = $user->timezone;
        $startTime = $data['start_time'] ?? $meeting->start_time;
        $endTime = $data['end_time'] ?? $meeting->end_time;
        $start = CarbonImmutable::createFromFormat('Y-m-d H:i', $data['date'].' '.$startTime, $timezone)->second(0);
        $end = CarbonImmutable::createFromFormat('Y-m-d H:i', $data['date'].' '.$endTime, $timezone)->second(0);
        abort_unless(
            $start->isoWeekday() === $meeting->weekday,
            422,
            __('This meeting does not take place on that day.')
        );
        if ($end->lte($start)) {
            abort(422, __('The end must be after the start'));
        }

        $dayStart = $start->startOfDay();
        $existing = TimeEntry::query()
            ->where('member_id', '=', $member->getKey())
            ->where('description', '=', $meeting->name)
            ->where('start', '>=', $dayStart->utc())
            ->where('start', '<', $dayStart->addDay()->utc())
            ->first();
        if ($existing !== null) {
            return response()->json(['data' => ['time_entry_id' => $existing->getKey()]]);
        }

        $timeEntry = new TimeEntry;
        $timeEntry->description = $meeting->name;
        $timeEntry->start = Carbon::instance($start->utc());
        $timeEntry->end = Carbon::instance($end->utc());
        $timeEntry->billable = false;
        $timeEntry->type = TimeEntryType::Work;
        $timeEntry->tags = array_values($meeting->tag_ids);
        $timeEntry->user_id = $user->getKey();
        $timeEntry->member_id = $member->getKey();
        $timeEntry->project_id = null;
        $timeEntry->task_id = null;
        $timeEntry->client_id = null;
        $timeEntry->organization()->associate($organization);
        $timeEntry->setComputedAttributeValue('billable_rate');
        $timeEntry->save();

        return response()->json(['data' => ['time_entry_id' => $timeEntry->getKey()]], 201);
    }

    public function index(string $organizationId): JsonResponse
    {
        $organization = $this->organizationManagedByCurrentUser($organizationId);

        return response()->json(['data' => ValoMeeting::query()
            ->where('organization_id', '=', $organization->getKey())
            ->orderBy('weekday')
            ->orderBy('start_time')
            ->get()
            ->map(fn (ValoMeeting $meeting): array => $this->toArray($meeting))
            ->values()]);
    }

    public function store(Request $request, string $organizationId): JsonResponse
    {
        $organization = $this->organizationManagedByCurrentUser($organizationId);
        $meeting = new ValoMeeting;
        $meeting->organization_id = $organization->getKey();
        $meeting->fill($this->validated($request, $organization));
        $meeting->save();

        return response()->json(['data' => $this->toArray($meeting)], 201);
    }

    public function update(Request $request, string $organizationId, string $meetingId): JsonResponse
    {
        $organization = $this->organizationManagedByCurrentUser($organizationId);
        $meeting = $this->meeting($organization, $meetingId);
        $meeting->fill($this->validated($request, $organization));
        $meeting->save();

        return response()->json(['data' => $this->toArray($meeting)]);
    }

    public function destroy(string $organizationId, string $meetingId): JsonResponse
    {
        $organization = $this->organizationManagedByCurrentUser($organizationId);
        $this->meeting($organization, $meetingId)->delete();

        return response()->json(null, 204);
    }

    /**
     * @return array{name: string, weekday: int, start_time: string, end_time: string, tag_ids: array<int, string>, member_ids: array<int, string>|null}
     */
    private function validated(Request $request, Organization $organization): array
    {
        $organizationId = $organization->getKey();
        $validator = validator($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'weekday' => ['required', 'integer', 'between:1,7'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'tag_ids' => ['present', 'array'],
            'tag_ids.*' => ['uuid', Rule::exists(Tag::class, 'id')->where('organization_id', $organizationId)],
            'member_ids' => ['nullable', 'array', 'min:1'],
            'member_ids.*' => ['uuid', Rule::exists(Member::class, 'id')->where('organization_id', $organizationId)],
        ]);
        $validator->after(function (Validator $validator) use ($request): void {
            $start = (string) $request->input('start_time');
            $end = (string) $request->input('end_time');
            if (preg_match('/^\d{2}:\d{2}$/', $start) === 1 && preg_match('/^\d{2}:\d{2}$/', $end) === 1 && $end <= $start) {
                $validator->errors()->add('end_time', __('The end must be after the start'));
            }
        });
        /** @var array{name: string, weekday: int, start_time: string, end_time: string, tag_ids: array<int, string>, member_ids?: array<int, string>|null} $data */
        $data = $validator->validate();

        return [
            'name' => trim($data['name']),
            'weekday' => (int) $data['weekday'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'tag_ids' => array_values($data['tag_ids']),
            'member_ids' => isset($data['member_ids']) ? array_values($data['member_ids']) : null,
        ];
    }

    /**
     * @return array{id: string, name: string, weekday: int, start_time: string, end_time: string, tag_ids: array<int, string>, member_ids: array<int, string>|null}
     */
    private function toArray(ValoMeeting $meeting): array
    {
        return [
            'id' => $meeting->getKey(),
            'name' => $meeting->name,
            'weekday' => $meeting->weekday,
            'start_time' => $meeting->start_time,
            'end_time' => $meeting->end_time,
            'tag_ids' => array_values($meeting->tag_ids),
            'member_ids' => $meeting->member_ids === null ? null : array_values($meeting->member_ids),
        ];
    }

    private function utc(string $date, string $time, string $timezone): string
    {
        return CarbonImmutable::createFromFormat('Y-m-d H:i', $date.' '.$time, $timezone)
            ->second(0)
            ->utc()
            ->format('Y-m-d\TH:i:s\Z');
    }

    private function meeting(Organization $organization, string $meetingId): ValoMeeting
    {
        /** @var ValoMeeting $meeting */
        $meeting = ValoMeeting::query()
            ->where('organization_id', '=', $organization->getKey())
            ->findOrFail($meetingId);

        return $meeting;
    }

    /**
     * @return array{0: Organization, 1: Member, 2: User}
     */
    private function currentMember(string $organizationId): array
    {
        /** @var User $user */
        $user = Auth::user();
        /** @var Organization $organization */
        $organization = Organization::query()->findOrFail($organizationId);
        /** @var Member|null $member */
        $member = Member::query()
            ->where('organization_id', '=', $organization->getKey())
            ->where('user_id', '=', $user->getKey())
            ->first();
        abort_if($member === null, 403);

        return [$organization, $member, $user];
    }

    private function organizationManagedByCurrentUser(string $organizationId): Organization
    {
        [$organization, $member] = $this->currentMember($organizationId);
        abort_unless(in_array($member->role, [Role::Owner->value, Role::Admin->value], true), 403);

        return $organization;
    }
}
