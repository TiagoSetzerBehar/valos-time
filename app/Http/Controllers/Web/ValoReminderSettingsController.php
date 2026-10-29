<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\Role;
use App\Models\Member;
use App\Models\Organization;
use App\Models\User;
use App\Models\ValoReminderSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * VALO's Time: owners and admins read and change the forgotten-hours reminders of their organization.
 */
class ValoReminderSettingsController extends Controller
{
    public function show(string $organizationId): JsonResponse
    {
        $organization = $this->organizationManagedByCurrentUser($organizationId);

        return response()->json($this->toArray(ValoReminderSetting::forOrganization($organization)));
    }

    public function update(Request $request, string $organizationId): JsonResponse
    {
        $organization = $this->organizationManagedByCurrentUser($organizationId);
        /** @var array{daily_enabled: bool, daily_time: string, weekly_enabled: bool, expected_weekly_hours: int} $data */
        $data = $request->validate([
            'daily_enabled' => ['required', 'boolean'],
            'daily_time' => ['required', 'date_format:H:i'],
            'weekly_enabled' => ['required', 'boolean'],
            'expected_weekly_hours' => ['required', 'integer', 'min:1', 'max:80'],
        ]);

        $setting = ValoReminderSetting::forOrganization($organization);
        $setting->fill($data);
        $setting->save();

        return response()->json($this->toArray($setting));
    }

    private function organizationManagedByCurrentUser(string $organizationId): Organization
    {
        /** @var User $user */
        $user = Auth::user();
        /** @var Organization $organization */
        $organization = Organization::query()->findOrFail($organizationId);
        $canManage = Member::query()
            ->where('organization_id', '=', $organization->getKey())
            ->where('user_id', '=', $user->getKey())
            ->whereIn('role', [Role::Owner->value, Role::Admin->value])
            ->exists();
        abort_unless($canManage, 403);

        return $organization;
    }

    /**
     * @return array{daily_enabled: bool, daily_time: string, weekly_enabled: bool, expected_weekly_hours: int}
     */
    private function toArray(ValoReminderSetting $setting): array
    {
        return [
            'daily_enabled' => $setting->daily_enabled,
            'daily_time' => $setting->daily_time,
            'weekly_enabled' => $setting->weekly_enabled,
            'expected_weekly_hours' => $setting->expected_weekly_hours,
        ];
    }
}
