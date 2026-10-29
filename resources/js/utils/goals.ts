import { trans } from 'laravel-vue-i18n';
import type { Goal, GoalComparison, GoalPeriod, GoalStatus } from '@/packages/api/src';
import { canDeleteOrganizationGoals, canUpdateOrganizationGoals } from '@/utils/permissions';
import { getCurrentMembershipId } from '@/utils/useUser';

export const goalComparisonOptions: { value: GoalComparison; label: string }[] = [
    {
        value: 'at_least',
        get label() {
            return trans('At least');
        },
    },
    {
        value: 'less_than',
        get label() {
            return trans('Less than');
        },
    },
];

export const goalPeriodOptions: { value: GoalPeriod; label: string }[] = [
    {
        value: 'day',
        get label() {
            return trans('per day');
        },
    },
    {
        value: 'week',
        get label() {
            return trans('per week');
        },
    },
    {
        value: 'month',
        get label() {
            return trans('per month');
        },
    },
];

/**
 * Who a goal is for. The three answers map to the goal type and the member of the goal:
 * me = a personal goal for yourself, other = an organization goal for one member,
 * organization = an organization goal that counts every member.
 */
export type GoalTarget = 'me' | 'other' | 'organization';

export const goalTargetOptions: { value: GoalTarget; label: string }[] = [
    {
        value: 'me',
        get label() {
            return trans('For me');
        },
    },
    {
        value: 'other',
        get label() {
            return trans('For someone else');
        },
    },
    {
        value: 'organization',
        get label() {
            return trans('Organization goal');
        },
    },
];

/**
 * Mirrors the access rules on the server: a personal goal belongs to its member, an organization goal
 * to the members that may manage goals. The member an organization goal is for never manages it.
 */
export function canManageGoal(goal: Goal): boolean {
    if (goal.type === 'personal') {
        return goal.member_id === getCurrentMembershipId();
    }
    return canUpdateOrganizationGoals() || canDeleteOrganizationGoals();
}

export function goalTypeLabel(goal: Goal): string {
    return goal.type === 'personal' ? trans('Personal goal') : trans('Organization goal');
}

export function goalComparisonLabel(comparison: GoalComparison): string {
    return goalComparisonOptions.find((option) => option.value === comparison)?.label ?? comparison;
}

export function goalStatusLabel(status: GoalStatus): string {
    switch (status) {
        case 'in_progress':
            return trans('In progress');
        case 'achieved':
            return trans('Achieved');
        case 'on_track':
            return trans('On track');
        case 'exceeded':
            return trans('Exceeded');
    }
}

export function goalStatusIsPositive(status: GoalStatus): boolean {
    return status === 'achieved' || status === 'on_track';
}
