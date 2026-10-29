<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * VALO's Time: reminder settings of one organization.
 *
 * @property string $id
 * @property string $organization_id
 * @property bool $daily_enabled
 * @property string $daily_time
 * @property bool $weekly_enabled
 * @property int $expected_weekly_hours
 * @property-read Organization $organization
 */
class ValoReminderSetting extends Model
{
    use HasUuids;

    protected $table = 'valo_reminder_settings';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'daily_enabled',
        'daily_time',
        'weekly_enabled',
        'expected_weekly_hours',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'daily_enabled' => false,
        'daily_time' => '18:00',
        'weekly_enabled' => false,
        'expected_weekly_hours' => 20,
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'daily_enabled' => 'boolean',
        'weekly_enabled' => 'boolean',
        'expected_weekly_hours' => 'integer',
    ];

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public static function forOrganization(Organization $organization): self
    {
        return self::query()->firstOrNew([
            'organization_id' => $organization->getKey(),
        ]);
    }
}
