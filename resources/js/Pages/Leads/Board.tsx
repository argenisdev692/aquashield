/**
 * Leads/Board — the pipeline page.
 *
 * The board itself lives in `Modules/LeadAppointment/components/LeadKanban`
 * so the Dashboard renders the same server-grouped columns instead of a
 * second, static copy.
 */
import { Head, Link, usePage } from '@inertiajs/react';
import { KanbanSquare, Plus, Table2 } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import AppLayout from '@/Layouts/AppLayout';
import LeadKanban from '@/Modules/LeadAppointment/components/LeadKanban';

type LeadBoardColumn = Modules.LeadAppointment.Application.Dtos.LeadBoardColumnData;

type BoardProps = {
    columns: LeadBoardColumn[];
    transitions: Record<string, string[]>;
    canUpdate: boolean;
    canCreate: boolean;
};

export default function LeadsBoard() {
    const { columns, transitions, canUpdate, canCreate, errors } = usePage<
        BoardProps & { errors: Record<string, string> }
    >().props;

    const total = columns.reduce((sum, column) => sum + column.total, 0);

    return (
        <AppLayout title="Leads board">
            <Head title="Leads board" />

            <div className="flex flex-col gap-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <div className="flex items-center gap-2">
                            <KanbanSquare className="size-5 text-aq-accent" aria-hidden="true" />
                            <h1 className="text-xl font-semibold tracking-tight text-surface-900 dark:text-surface-0">
                                Leads pipeline
                            </h1>
                            <span className="rounded-full bg-surface-100 px-2 py-px text-xs font-semibold tabular-nums text-surface-600 dark:bg-surface-800 dark:text-surface-300">
                                {total}
                            </span>
                        </div>
                        <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">
                            Drag a card to a legal stage, or use the stage selector on the card for
                            keyboard access.
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <Button
                            render={<Link href="/leads" />}
                            nativeButton={false}
                            variant="outline"
                            className="h-9 gap-2"
                        >
                            <Table2 className="size-4" aria-hidden="true" />
                            Table view
                        </Button>
                        {canCreate && (
                            <Button
                                render={<Link href="/leads/create" />}
                                nativeButton={false}
                                className="h-9 gap-2"
                            >
                                <Plus className="size-4" aria-hidden="true" />
                                New lead
                            </Button>
                        )}
                    </div>
                </div>

                <LeadKanban
                    columns={columns}
                    transitions={transitions}
                    canUpdate={canUpdate}
                    errors={errors}
                />
            </div>
        </AppLayout>
    );
}
