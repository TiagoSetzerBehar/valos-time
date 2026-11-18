<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\TimeEntryType;
use App\Models\Member;
use App\Models\Organization;
use App\Models\TimeEntry;
use App\Models\User;
use App\Service\PermissionStore;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * VALO's Time: time entries longer than 16 hours in a row are almost always a forgotten timer.
 * The weekly report shows them apart ("Para revisar") and lets the member or a manager set the real end.
 */
class ValoLongEntryController extends Controller
{
    public const int LIMIT_SECONDS = 16 * 3600;

    public function index(Request $request, string $organizationId, PermissionStore $permissions): JsonResponse
    {
        [$organization, $member, $user] = $this->currentMember($organizationId);
        /** @var array{start: string, end: string} $data */
        $data = $request->validate([
            'start' => ['required', 'date_format:Y-m-d'],
            'end' => ['required', 'date_format:Y-m-d', 'after_or_equal:start'],
        ]);
        $timezone = $user->timezone;
        $from = CarbonImmutable::createFromFormat('Y-m-d', $data['start'], $timezone)->startOfDay();
        $to = CarbonImmutable::createFromFormat('Y-m-d', $data['end'], $timezone)->startOfDay()->addDay();
        abort_if($from->diffInDays($to) > 63, 422, 'Range too long');
        $seesAll = $permissions->has($organization, 'time-entries:view:all');
        $fixesAll = $permissions->has($organization, 'time-entries:update:all');
        $now = CarbonImmutable::now();

        $entries = TimeEntry::query()
            ->where('organization_id', '=', $organization->getKey())
            ->where('type', '=', TimeEntryType::Work)
            ->where('start', '>=', $from->utc())
            ->where('start', '<', $to->utc())
            ->when(! $seesAll, fn ($query) => $query->where('member_id', '=', $member->getKey()))
            ->where(function ($query) use ($now): void {
                $query->whereRaw('extract(epoch from ("end" - "start")) > ?', [self::LIMIT_SECONDS])
                    ->orWhere(function ($running) use ($now): void {
                        $running->whereNull('end')->where('start', '<', $now->subSeconds(self::LIMIT_SECONDS)->utc());
                    });
            })
            ->with('user')
            ->orderBy('start')
            ->get();

        return response()->json([
            'data' => $entries->map(fn (TimeEntry $entry): array => [
                'id' => $entry->getKey(),
                'member_id' => $entry->member_id,
                'user_id' => $entry->user_id,
                'name' => $entry->user->name,
                'project_id' => $entry->project_id,
                'description' => $entry->description,
                'start' => $entry->start->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
                'day' => $entry->start->copy()->setTimezone($timezone)->format('Y-m-d'),
                'running' => $entry->end === null,
                'seconds' => (int) $entry->start->diffInSeconds($entry->end ?? $now, true),
                'can_fix' => $fixesAll || $entry->member_id === $member->getKey(),
            ])->values()->all(),
        ]);
    }

    /**
     * Sets the real end of a forgotten timer.
     */
    public function update(Request $request, string $organizationId, string $timeEntryId, PermissionStore $permissions): JsonResponse
    {
        [$organization, $member] = $this->currentMember($organizationId);
        /** @var TimeEntry $entry */
        $entry = TimeEntry::query()
            ->where('organization_id', '=', $organization->getKey())
            ->findOrFail($timeEntryId);
        abort_unless(
            $entry->member_id === $member->getKey() || $permissions->has($organization, 'time-entries:update:all'),
            403
        );
        /** @var array{end: string} $data */
        $data = $request->validate([
            'end' => ['required', 'date'],
        ]);
        $end = Carbon::parse($data['end'])->utc();
        abort_if($end->lte($entry->start), 422, __('The end must be after the start'));
        abort_if($end->gt(Carbon::now()->addMinute()), 422, __('The end can not be in the future'));
        abort_if($entry->end !== null && $end->gt($entry->end), 422, __('The new end can not be later than the current one'));

        $entry->end = $end;
        $entry->save();

        return response()->json(['data' => ['id' => $entry->getKey()]]);
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
}
