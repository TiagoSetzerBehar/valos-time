import axios from 'axios';
import { computed, type ComputedRef } from 'vue';
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query';
import { getCurrentOrganizationId } from '@/utils/useUser';

/** VALO's Time: a time entry of more than 16 hours in a row (almost always a forgotten timer). */
export type LongEntry = {
    id: string;
    member_id: string;
    user_id: string;
    name: string;
    project_id: string | null;
    description: string;
    /** UTC, ISO */
    start: string;
    /** local date (YYYY-MM-DD) the entry is counted on */
    day: string;
    running: boolean;
    seconds: number;
    can_fix: boolean;
};

/** Entries to review between two local dates (YYYY-MM-DD, inclusive). */
export function useValoLongEntries(range: ComputedRef<{ start: string; end: string }>) {
    const query = useQuery({
        queryKey: computed(() => ['valoLongEntries', getCurrentOrganizationId(), range.value]),
        queryFn: async () => {
            const response = await axios.get<{ data: LongEntry[] }>(
                route('valo.long-entries.index', {
                    organizationId: getCurrentOrganizationId(),
                    start: range.value.start,
                    end: range.value.end,
                })
            );
            return response.data.data;
        },
        enabled: computed(() => !!getCurrentOrganizationId()),
        staleTime: 1000 * 30,
        placeholderData: (previous) => previous,
    });
    const longEntries = computed(() => query.data.value ?? []);
    return { longEntries, query };
}

/** Sets the real end of a forgotten timer (ISO date-time). */
export function useFixLongEntry() {
    const queryClient = useQueryClient();
    return useMutation({
        mutationFn: async (payload: { id: string; end: string }) => {
            await axios.put(
                route('valo.long-entries.update', {
                    organizationId: getCurrentOrganizationId(),
                    timeEntryId: payload.id,
                }),
                { end: payload.end }
            );
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['valoLongEntries'] });
            queryClient.invalidateQueries({ queryKey: ['timeEntries'] });
            queryClient.invalidateQueries({ queryKey: ['aggregatedTimeEntries'] });
        },
    });
}
