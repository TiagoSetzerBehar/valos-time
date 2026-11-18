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

const AVATAR_COLORS = [
    '#0284c7',
    '#7c3aed',
    '#be185d',
    '#c2410c',
    '#047857',
    '#0e7490',
    '#4f46e5',
    '#b45309',
    '#be123c',
    '#0f766e',
];

/** A steady color for the round avatar of a member or project (same key, same color). */
export function valoAvatarColor(key: string): string {
    let hash = 0;
    for (const char of key) {
        hash = (hash * 31 + char.charCodeAt(0)) >>> 0;
    }
    return AVATAR_COLORS[hash % AVATAR_COLORS.length] as string;
}

const HIDDEN_SECTIONS: readonly string[] = ['goals', 'clients'];

/** VALO's Time does not use goals nor clients: those sections are hidden everywhere. */
export function valoSectionVisible(section: 'goals' | 'clients'): boolean {
    return !HIDDEN_SECTIONS.includes(section);
}

/** "Tiago Behar" -> "TB", "nahia" -> "N". */
export function valoInitials(name: string): string {
    const parts = name.trim().split(/\s+/);
    return ((parts[0]?.[0] ?? '') + (parts[1]?.[0] ?? '')).toUpperCase();
}
