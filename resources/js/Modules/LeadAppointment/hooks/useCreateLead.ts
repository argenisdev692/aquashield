/**
 * Creating a lead from a client-owned panel (the dashboard modal).
 *
 * The CRUD pages submit through Inertia because creation is a page visit; the
 * dashboard stays mounted, so its modal writes JSON and reloads the pipeline
 * props afterwards. This hook is the only place that request lives.
 */
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { requestJson } from '@/lib/http';
import type { LeadFormValues } from '../schemas/lead';

export interface CreateLeadController {
    create: (input: LeadFormValues) => Promise<unknown>;
    isPending: boolean;
    error: Error | null;
}

export function useCreateLead(): CreateLeadController {
    const queryClient = useQueryClient();

    const mutation = useMutation({
        mutationFn: (input: LeadFormValues) =>
            requestJson('/leads', { method: 'POST', body: input }),
        onSettled: () => {
            void queryClient.invalidateQueries({ queryKey: ['lead-appointment', 'lead-options'] });
        },
    });

    return {
        create: (input) => mutation.mutateAsync(input),
        isPending: mutation.isPending,
        error: mutation.error,
    };
}
