<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TimeEntryType;
use App\Models\Concerns\HasUuids;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * VALO's Time: a meeting that repeats every week (e.g. "TSA Tech", Monday 16:00–17:00).
 * It is shown prepared in the calendar of its members and becomes a time entry only when they confirm it.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $name
 * @property int $weekday ISO weekday, 1 = Monday ... 7 = Sunday
 * @property string $start_time HH:MM
 * @property string $end_time HH:MM
 * @property array<int, string> $tag_ids
 * @property array<int, string>|null $member_ids null = every member
 * @property string|null $created_by_member_id set for a member's own routine ("Crear rutina")
 * @property-read Organization $organization
 */
class ValoMeeting extends Model
{
    use HasUuids;

    protected $table = 'valo_meetings';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'name',
        'weekday',
        'start_time',
        'end_time',
        'tag_ids',
        'member_ids',
        'created_by_member_id',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'tag_ids' => '[]',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'weekday' => 'integer',
        'tag_ids' => 'array',
        'member_ids' => 'array',
    ];

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function isForMember(Member $member): bool
    {
        return $this->member_ids === null || in_array($member->getKey(), $this->member_ids, true);
    }

    /**
     * The entry that shows the member already counted this meeting on that day: same name (capitals and
     * spaces do not matter) or an entry during the meeting time with one of the meeting's tags.
     *
     * @param  Collection<int, TimeEntry>  $entries  entries of one member
     */
    public function attendedEntry(string $date, string $timezone, Collection $entries): ?TimeEntry
    {
        $name = self::normalizeName($this->name);
        $meetingStart = CarbonImmutable::createFromFormat('Y-m-d H:i', $date.' '.$this->start_time, $timezone)->second(0);
        $meetingEnd = CarbonImmutable::createFromFormat('Y-m-d H:i', $date.' '.$this->end_time, $timezone)->second(0);

        return $entries->first(function (TimeEntry $entry) use ($name, $date, $timezone, $meetingStart, $meetingEnd): bool {
            if ($entry->start->copy()->setTimezone($timezone)->format('Y-m-d') !== $date) {
                return false;
            }
            if (self::normalizeName($entry->description) === $name) {
                return true;
            }
            $sharesTag = array_intersect($entry->tags, $this->tag_ids) !== [];
            $overlaps = $entry->end !== null && $entry->start->lt($meetingEnd) && $entry->end->gt($meetingStart);

            return $sharesTag && $overlaps;
        }) ?? $this->coveringEntry($meetingStart, $meetingEnd, $entries);
    }

    /**
     * When the member already has hours during the whole meeting, the meeting is inside them: an hour can
     * not count twice, so it counts as done.
     *
     * @param  Collection<int, TimeEntry>  $entries
     */
    private function coveringEntry(CarbonImmutable $start, CarbonImmutable $end, Collection $entries): ?TimeEntry
    {
        if (self::freeGaps($start, $end, $entries) !== []) {
            return null;
        }

        return $entries->first(fn (TimeEntry $entry): bool => $entry->type !== TimeEntryType::Break
            && $entry->start->lt($end)
            && ($entry->end === null || $entry->end->gt($start)));
    }

    /**
     * The parts of [from, to) that are not already in a work entry of the member (a running timer counts
     * until now). Pieces shorter than a minute are left out.
     *
     * @param  Collection<int, TimeEntry>  $entries
     * @return list<array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    public static function freeGaps(CarbonImmutable $from, CarbonImmutable $to, Collection $entries): array
    {
        $now = CarbonImmutable::now();
        $busy = $entries
            ->filter(fn (TimeEntry $entry): bool => $entry->type !== TimeEntryType::Break)
            ->map(fn (TimeEntry $entry): array => [
                CarbonImmutable::instance($entry->start),
                $entry->end === null ? $now : CarbonImmutable::instance($entry->end),
            ])
            ->filter(fn (array $range): bool => $range[0]->lt($to) && $range[1]->gt($from))
            ->sortBy(fn (array $range): int => $range[0]->getTimestamp())
            ->values();

        $gaps = [];
        $cursor = $from;
        foreach ($busy as [$busyStart, $busyEnd]) {
            if ($busyStart->gt($cursor)) {
                $gaps[] = [$cursor, $busyStart->lt($to) ? $busyStart : $to];
            }
            if ($busyEnd->gt($cursor)) {
                $cursor = $busyEnd;
            }
            if ($cursor->gte($to)) {
                break;
            }
        }
        if ($cursor->lt($to)) {
            $gaps[] = [$cursor, $to];
        }

        return array_values(array_filter(
            $gaps,
            fn (array $gap): bool => $gap[0]->diffInSeconds($gap[1], true) >= 60
        ));
    }

    public static function normalizeName(string $name): string
    {
        return mb_strtolower((string) preg_replace('/\s+/u', ' ', trim($name)));
    }
}
