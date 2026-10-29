import type { Weekday } from '@/packages/api/src';
import { trans } from 'laravel-vue-i18n';

export const weekdayOptions: { value: Weekday; label: string }[] = [
    {
        value: 'monday',
        get label() {
            return trans('Monday');
        },
    },
    {
        value: 'tuesday',
        get label() {
            return trans('Tuesday');
        },
    },
    {
        value: 'wednesday',
        get label() {
            return trans('Wednesday');
        },
    },
    {
        value: 'thursday',
        get label() {
            return trans('Thursday');
        },
    },
    {
        value: 'friday',
        get label() {
            return trans('Friday');
        },
    },
    {
        value: 'saturday',
        get label() {
            return trans('Saturday');
        },
    },
    {
        value: 'sunday',
        get label() {
            return trans('Sunday');
        },
    },
];
