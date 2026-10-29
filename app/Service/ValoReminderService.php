<?php

declare(strict_types=1);

namespace App\Service;

use App\Enums\Role;
use App\Enums\TimeEntryType;
use App\Models\Member;
use App\Models\Organization;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * VALO's Time: shared logic of the forgotten-hours reminders.
 */
class ValoReminderService
{
    /**
     * Members that are expected to log time (no placeholders).
     *
     * @return Collection<int, Member>
     */
    public function trackableMembers(Organization $organization): Collection
    {
        return Member::query()
            ->where('organization_id', '=', $organization->getKey())
            ->where('role', '!=', Role::Placeholder->value)
            ->whereHas('user', function (Builder $query): void {
                /** @var Builder<User> $query */
                $query->where('is_placeholder', '=', false);
            })
            ->with('user')
            ->get();
    }

    /**
     * Seconds of work a member logged in entries starting in [start, end); running entries count until now.
     */
    public function trackedSeconds(Member $member, Carbon $start, Carbon $end): int
    {
        $now = Carbon::now();

        return (int) TimeEntry::query()
            ->where('member_id', '=', $member->getKey())
            ->where('type', '!=', TimeEntryType::Break->value)
            ->where('start', '>=', $start->copy()->utc())
            ->where('start', '<', $end->copy()->utc())
            ->get(['start', 'end'])
            ->sum(fn (TimeEntry $timeEntry): int => (int) $timeEntry->start->diffInSeconds($timeEntry->end ?? $now, true));
    }

    public static function formatHours(int $seconds): string
    {
        $minutes = intdiv($seconds, 60);

        return intdiv($minutes, 60).':'.str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT);
    }
}
