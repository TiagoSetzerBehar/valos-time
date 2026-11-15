<?php

declare(strict_types=1);

namespace App\Service;

use App\Enums\Role;
use App\Enums\TimeEntryType;
use App\Models\Member;
use App\Models\Organization;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\ValoMeeting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * VALO's Time: numbers for the one-page weekly summary (this week compared with the week before):
 * total hours, hours per day, per person, per project and who attended the weekly meetings.
 */
class ValoWeeklySummaryService
{
    private const int MAX_PROJECTS = 6;

    private const array WEEKDAYS = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];

    /**
     * @return array{
     *     organization: string,
     *     week_number: int,
     *     start: CarbonImmutable,
     *     end: CarbonImmutable,
     *     total: int,
     *     previous_total: int,
     *     members_count: int,
     *     active_members: int,
     *     days: array<int, array{label: string, seconds: int, previous: int}>,
     *     members: array<int, array{name: string, seconds: int, previous: int}>,
     *     projects: array<int, array{name: string, color: string, seconds: int, previous: int}>,
     *     meetings: array<int, array{name: string, when: string, attended: int, expected: int}>,
     *     running: array<int, string>,
     * }
     */
    public function build(Organization $organization, CarbonImmutable $weekStart, string $timezone): array
    {
        $start = $weekStart->setTimezone($timezone)->startOfDay();
        $end = $start->addDays(7);
        $previousStart = $start->subDays(7);

        /** @var Collection<int, TimeEntry> $entries */
        $entries = TimeEntry::query()
            ->where('organization_id', '=', $organization->getKey())
            ->where('type', '=', TimeEntryType::Work)
            ->where('start', '>=', $previousStart->utc())
            ->where('start', '<', $end->utc())
            ->get(['id', 'member_id', 'project_id', 'description', 'start', 'end', 'tags']);

        // A timer that is still running does not count yet (people forget to stop them); it is listed apart.
        $seconds = static fn (TimeEntry $entry): int => $entry->end === null
            ? 0
            : max(0, (int) $entry->start->diffInSeconds($entry->end, true));
        $inWeek = static fn (TimeEntry $entry): bool => $entry->start->gte($start);
        $current = $entries->filter($inWeek);
        $previous = $entries->reject($inWeek);

        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $day = $start->addDays($i);
            $sumDay = static fn (Collection $list, CarbonImmutable $date): int => (int) $list
                ->filter(fn (TimeEntry $entry): bool => $entry->start->copy()->setTimezone($timezone)->isSameDay($date))
                ->sum($seconds);
            $days[] = [
                'label' => self::WEEKDAYS[$day->isoWeekday() - 1].' '.$day->day,
                'seconds' => $sumDay($current, $day),
                'previous' => $sumDay($previous, $day->subDays(7)),
            ];
        }

        /** @var Collection<int, Member> $members */
        $members = Member::query()
            ->where('organization_id', '=', $organization->getKey())
            ->with('user')
            ->get();
        $memberRows = $members
            ->map(fn (Member $member): array => [
                'name' => $member->user->name,
                'seconds' => (int) $current->where('member_id', $member->getKey())->sum($seconds),
                'previous' => (int) $previous->where('member_id', $member->getKey())->sum($seconds),
                'placeholder' => $member->role === Role::Placeholder->value,
            ])
            ->reject(fn (array $row): bool => $row['placeholder'] && $row['seconds'] === 0 && $row['previous'] === 0)
            ->map(fn (array $row): array => ['name' => $row['name'], 'seconds' => $row['seconds'], 'previous' => $row['previous']])
            ->sortBy([['seconds', 'desc'], ['name', 'asc']])
            ->values();

        /** @var Collection<string, Project> $projects */
        $projects = Project::query()
            ->whereIn('id', $entries->pluck('project_id')->filter()->unique()->values())
            ->get(['id', 'name', 'color'])
            ->keyBy('id');
        $projectRows = $entries->pluck('project_id')->unique()->values()
            ->map(function (?string $projectId) use ($projects, $current, $previous, $seconds): array {
                $project = $projectId !== null ? $projects->get($projectId) : null;

                return [
                    'name' => $project !== null ? $project->name : 'Sin proyecto',
                    'color' => $project !== null ? $project->color : '#4F7CAC',
                    'seconds' => (int) $current->where('project_id', $projectId)->sum($seconds),
                    'previous' => (int) $previous->where('project_id', $projectId)->sum($seconds),
                ];
            })
            ->filter(fn (array $row): bool => $row['seconds'] > 0)
            ->sortByDesc('seconds')
            ->values();
        $projectList = array_values($projectRows->all());
        if (count($projectList) > self::MAX_PROJECTS) {
            $rest = array_slice($projectList, self::MAX_PROJECTS - 1);
            $projectList = array_slice($projectList, 0, self::MAX_PROJECTS - 1);
            $projectList[] = [
                'name' => 'Otros ('.count($rest).')',
                'color' => '#94A3B8',
                'seconds' => array_sum(array_column($rest, 'seconds')),
                'previous' => array_sum(array_column($rest, 'previous')),
            ];
        }

        $meetingRows = [];
        $meetings = ValoMeeting::query()
            ->where('organization_id', '=', $organization->getKey())
            ->orderBy('weekday')
            ->orderBy('start_time')
            ->get();
        $activeMembers = $members->reject(fn (Member $member): bool => $member->role === Role::Placeholder->value);
        foreach ($meetings as $meeting) {
            $date = $start->addDays($meeting->weekday - 1);
            $expected = $activeMembers->filter(fn (Member $member): bool => $meeting->isForMember($member));
            $attended = $expected->filter(fn (Member $member): bool => $meeting->attendedEntry(
                $date->format('Y-m-d'),
                $timezone,
                $current->where('member_id', $member->getKey())
            ) !== null);
            $meetingRows[] = [
                'name' => $meeting->name,
                'when' => self::WEEKDAYS[$date->isoWeekday() - 1].' '.$meeting->start_time,
                'attended' => $attended->count(),
                'expected' => $expected->count(),
            ];
        }

        return [
            'organization' => $organization->name,
            'week_number' => (int) $start->format('W'),
            'start' => $start,
            'end' => $end->subDay(),
            'total' => (int) $current->sum($seconds),
            'previous_total' => (int) $previous->sum($seconds),
            'members_count' => $memberRows->count(),
            'active_members' => $memberRows->where('seconds', '>', 0)->count(),
            'days' => $days,
            'members' => array_values($memberRows->all()),
            'projects' => $projectList,
            'meetings' => $meetingRows,
            'running' => array_values($members
                ->whereIn('id', $current->whereNull('end')->pluck('member_id')->unique()->all())
                ->map(fn (Member $member): string => $member->user->name)
                ->sort()
                ->all()),
        ];
    }
}
