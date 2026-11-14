<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
