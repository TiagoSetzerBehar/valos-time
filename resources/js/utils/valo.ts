import { usePage } from '@inertiajs/vue3';

/**
 * VALO's Time hides money (billable rates, costs, currency) unless VALO_SHOW_MONEY=true.
 * Without the shared prop (e.g. in unit tests) money stays visible, like upstream solidtime.
 */
export function isMoneyVisible(): boolean {
    try {
        return usePage<{ valo_show_money?: boolean }>().props.valo_show_money !== false;
    } catch {
        return true;
    }
}
