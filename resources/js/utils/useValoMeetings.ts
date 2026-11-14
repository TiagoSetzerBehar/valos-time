import axios from 'axios';
import { computed, type ComputedRef } from 'vue';
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query';
import { getCurrentOrganizationId } from '@/utils/useUser';

/** VALO's Time: one weekly meeting on one day, for the current member. */
export type MeetingOccurrence = {
    meeting_id: string;
    name: string;
    date: string;
    start: string;
    end: string;
    start_time: string;
    end_time: string;
    tag_ids: string[];
    /** set once the member pressed OK (the meeting became a time entry) */
    time_entry_id: string | null;
};

/** Meetings of the current member between two local dates (YYYY-MM-DD, inclusive). */
export function useMeetingOccurrences(range: ComputedRef<{ start: string; end: string } | null>) {
    const query = useQuery({
        queryKey: computed(() => ['valoMeetings', getCurrentOrganizationId(), range.value]),
        queryFn: async () => {
            const response = await axios.get<{ data: MeetingOccurrence[] }>(
                route('valo.meetings.occurrences', {
                    organizationId: getCurrentOrganizationId(),
                    start: range.value!.start,
                    end: range.value!.end,
                })
            );
            return response.data.data;
        },
        enabled: computed(() => !!getCurrentOrganizationId() && range.value !== null),
        staleTime: 1000 * 30,
        placeholderData: (previous) => previous,
    });

    const occurrences = computed(() => query.data.value ?? []);
    return { occurrences, query };
}

/** "OK, asistí": turns the meeting into a time entry (times can be adjusted). */
export function useConfirmMeeting() {
    const queryClient = useQueryClient();
    return useMutation({
        mutationFn: async (payload: {
            meetingId: string;
            date: string;
            startTime: string;
            endTime: string;
        }) => {
            await axios.post(
                route('valo.meetings.confirm', {
                    organizationId: getCurrentOrganizationId(),
                    meetingId: payload.meetingId,
                }),
                { date: payload.date, start_time: payload.startTime, end_time: payload.endTime }
            );
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['valoMeetings'] });
            queryClient.invalidateQueries({ queryKey: ['timeEntries'] });
            queryClient.invalidateQueries({ queryKey: ['aggregatedTimeEntries'] });
            queryClient.invalidateQueries({ queryKey: ['goals'] });
        },
    });
}
