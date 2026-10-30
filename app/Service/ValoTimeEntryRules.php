<?php

declare(strict_types=1);

namespace App\Service;

use App\Models\Organization;
use App\Models\ValoReminderSetting;

/**
 * VALO's Time: required fields of finished work time entries (like Clockify's "required fields").
 */
class ValoTimeEntryRules
{
    /**
     * @return array<string, string> field => message
     */
    public static function missingRequiredFields(
        Organization $organization,
        bool $isBreak,
        bool $isFinished,
        ?string $projectId,
        ?string $description
    ): array {
        if ($isBreak || ! $isFinished) {
            return [];
        }
        $setting = ValoReminderSetting::query()
            ->where('organization_id', '=', $organization->getKey())
            ->first();
        if ($setting === null) {
            return [];
        }

        $missing = [];
        if ($setting->require_project && ($projectId === null || $projectId === '')) {
            $missing['project_id'] = __('A project is required to save this time entry.');
        }
        if ($setting->require_description && trim((string) $description) === '') {
            $missing['description'] = __('A description is required to save this time entry.');
        }

        return $missing;
    }
}
