import { computed, inject, type ComputedRef, type InjectionKey } from 'vue';

// VALO's Time: the app layout provides whether money (rates, costs, currency) is shown.
export const moneyVisibleKey: InjectionKey<ComputedRef<boolean>> = Symbol('moneyVisible');

export function useMoneyVisible(): ComputedRef<boolean> {
    return inject(
        moneyVisibleKey,
        computed(() => true)
    );
}
