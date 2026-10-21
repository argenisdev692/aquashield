<script setup lang="ts">
/**
 * The CRM start screen — placeholder edition.
 *
 * A design-complete frontend only: every number and card comes from the
 * in-file demo set below, so the pipeline board and the KPI tiles read
 * exactly how the real dashboard will behave once the leads pipeline is a
 * backend module. Nothing here touches the network — no fetch, no Inertia
 * visits. A drag, the drawer's stage select and the "New lead" dialog all
 * mutate the local mock set, which is why a reload resets it.
 *
 * The KPI math below deliberately mirrors what the server should compute
 * (last-7-day intake, open-pipeline value, won-this-month against last
 * month, average first response), so swapping the mock set for API props
 * is a data-source swap, not a redesign.
 *
 * Layout, 2026-minimal single column (KPIs → board → chart → today):
 * - KPI row: four tiles behind the shared `KpiCard` (app-wide component —
 *   hover lift, crown hairline and entrance stagger live there), native CSS
 *   staggered entrance (no motion-v on this page — the bundle keeps its
 *   marketing budget for the welcome).
 * - Kanban board: full width, fixed-width columns, horizontal scroll.
 *   Drag is HTML5 native (desktop pointer); cards open a Detail drawer
 *   (Volt/PrimeVue Drawer, right side) on click for pointer AND keyboard
 *   users — the drawer's stage select is the accessible twin of drag.
 * - Won-revenue chart: tree-shaken Chart.js, lazy-loaded from its own
 *   chunk on first draw, palette read from the design tokens and rebuilt
 *   on dark-mode change (ThemeToggle toggles `.dark` on <html>; a
 *   MutationObserver is the reactive bridge).
 * - Scheduled today: the day's field appointments under the chart.
 */
import {
    computed,
    onMounted,
    onUnmounted,
    onWatcherCleanup,
    reactive,
    ref,
    useTemplateRef,
    watch,
} from 'vue';
import { Head } from '@inertiajs/vue3';
import KpiCard from '@/Components/KpiCard.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from '@/volt/Button.vue';
import Card from '@/volt/Card.vue';
import Dialog from '@/volt/Dialog.vue';
import Drawer from '@/volt/Drawer.vue';

/* ------------------------------------------------------------- vocabulary */

type Stage = 'NEW' | 'CONTACTED' | 'INSPECTION' | 'ESTIMATE' | 'WON' | 'LOST';
type ServiceType = 'WATER_DAMAGE' | 'ROOF' | 'STORM' | 'MOLD' | 'FIRE';
type LeadPriority = 'NORMAL' | 'HIGH' | 'EMERGENCY';
type KpiTrend = 'up' | 'down' | 'flat';

interface Kpi {
    key: string;
    label: string;
    format: 'count' | 'minor' | 'minutes';
    value: number;
    delta: string | null;
    trend: KpiTrend;
    /** PrimeIcons class rendered as the tile's corner badge. */
    icon: string;
}

interface Lead {
    id: string;
    contactName: string;
    phone: string;
    city: string;
    service: ServiceType;
    priority: LeadPriority;
    stage: Stage;
    valueCents: number | null;
    scheduledAt: Date | null;
    wonAt: Date | null;
    createdAt: Date;
}

const STAGES: readonly Stage[] = ['NEW', 'CONTACTED', 'INSPECTION', 'ESTIMATE', 'WON', 'LOST'];

const OPEN_STAGES: readonly Stage[] = STAGES.filter((stage) => stage !== 'WON' && stage !== 'LOST');

/** Column metadata. The dot colour is state vocabulary, not decoration. */
const STAGE_META: Record<Stage, { label: string; dot: string }> = {
    NEW: { label: 'New', dot: 'bg-aq-accent' },
    CONTACTED: { label: 'Contacted', dot: 'bg-aq-accent-soft' },
    INSPECTION: { label: 'Inspection', dot: 'bg-aq-warning' },
    ESTIMATE: { label: 'Estimate', dot: 'bg-aq-accent-deep' },
    WON: { label: 'Won', dot: 'bg-aq-success' },
    LOST: { label: 'Lost', dot: 'bg-aq-ink-disabled' },
};

const SERVICE_LABEL: Record<ServiceType, string> = {
    WATER_DAMAGE: 'Water damage',
    ROOF: 'Roof',
    STORM: 'Storm',
    MOLD: 'Mold',
    FIRE: 'Fire',
};

const PRIORITY_LABEL: Record<LeadPriority, string> = {
    NORMAL: 'Normal',
    HIGH: 'High',
    EMERGENCY: 'Emergency',
};

const PRIORITY_WEIGHT: Record<LeadPriority, number> = {
    EMERGENCY: 0,
    HIGH: 1,
    NORMAL: 2,
};

// The delta-pill tone and background moved onto KpiCard: the tile owns its
// own state colours, the page only types and computes them as data.

/* ---------------------------------------------------------------- mock set */

const seededNow = new Date();

const daysAgo = (days: number): Date => new Date(seededNow.getTime() - days * 86_400_000);

/** Compose `base + N days` at a fixed wall time of day. */
const at = (daysFromNow: number, time: string): Date => {
    const [hour, minute] = time.split(':').map(Number);
    const date = new Date(seededNow.getTime() + daysFromNow * 86_400_000);

    date.setHours(hour ?? 0, minute ?? 0, 0, 0);

    return date;
};

interface MockRow {
    contactName: string;
    phone: string;
    city: string;
    service: ServiceType;
    priority: LeadPriority;
    stage: Stage;
    valueCents: number | null;
    /** Days back from seeding time. */
    createdDaysAgo: number;
    /** `[daysFromNow, 'HH:mm']` or null — the inspection/estimate appointment. */
    scheduled?: [number, string] | null;
    wonDaysAgo?: number | null;
}

/** Houston-metro demo pipeline, dated relative to page load on purpose: the
 * 7-day, month and six-month windows always read a live picture. */
const MOCK_ROWS: readonly MockRow[] = [
    // New
    {
        contactName: 'Alejandra Gutiérrez',
        phone: '(713) 555-0112',
        city: 'Houston',
        service: 'WATER_DAMAGE',
        priority: 'EMERGENCY',
        stage: 'NEW',
        valueCents: null,
        createdDaysAgo: 0.083,
        scheduled: null,
    },
    {
        contactName: 'Derek Cole',
        phone: '(281) 555-0134',
        city: 'Pasadena',
        service: 'ROOF',
        priority: 'NORMAL',
        stage: 'NEW',
        valueCents: null,
        createdDaysAgo: 1,
        scheduled: null,
    },
    {
        contactName: 'Rohan Patel',
        phone: '(832) 555-0181',
        city: 'Katy',
        service: 'MOLD',
        priority: 'HIGH',
        stage: 'NEW',
        valueCents: null,
        createdDaysAgo: 3,
        scheduled: null,
    },
    // Contacted
    {
        contactName: 'Suzie Nguyen',
        phone: '(713) 555-0168',
        city: 'Spring',
        service: 'WATER_DAMAGE',
        priority: 'HIGH',
        stage: 'CONTACTED',
        valueCents: null,
        createdDaysAgo: 4,
        scheduled: null,
    },
    {
        contactName: 'Familia Ríos',
        phone: '(281) 555-0190',
        city: 'Houston',
        service: 'STORM',
        priority: 'EMERGENCY',
        stage: 'CONTACTED',
        valueCents: null,
        createdDaysAgo: 6,
        scheduled: null,
    },
    // Inspection
    {
        contactName: 'Tyler Maddox',
        phone: '(713) 555-0127',
        city: 'Cypress',
        service: 'WATER_DAMAGE',
        priority: 'EMERGENCY',
        stage: 'INSPECTION',
        valueCents: null,
        createdDaysAgo: 8,
        scheduled: [0, '09:00'],
    },
    {
        contactName: 'Joan Whitaker',
        phone: '(713) 555-0103',
        city: 'Houston',
        service: 'ROOF',
        priority: 'NORMAL',
        stage: 'INSPECTION',
        valueCents: null,
        createdDaysAgo: 12,
        scheduled: [1, '10:00'],
    },
    {
        contactName: 'Ana Reyes',
        phone: '(281) 555-0152',
        city: 'Baytown',
        service: 'FIRE',
        priority: 'HIGH',
        stage: 'INSPECTION',
        valueCents: null,
        createdDaysAgo: 5,
        scheduled: [0, '14:30'],
    },
    // Estimate
    {
        contactName: 'Ben Owens',
        phone: '(832) 555-0149',
        city: 'Houston',
        service: 'WATER_DAMAGE',
        priority: 'HIGH',
        stage: 'ESTIMATE',
        valueCents: 485_000,
        createdDaysAgo: 20,
        scheduled: [-15, '11:00'],
    },
    {
        contactName: 'Naomi Fields',
        phone: '(713) 555-0175',
        city: 'Sugar Land',
        service: 'STORM',
        priority: 'NORMAL',
        stage: 'ESTIMATE',
        valueCents: 980_000,
        createdDaysAgo: 9,
        scheduled: [-2, '13:00'],
    },
    {
        contactName: 'Clark Dixon',
        phone: '(281) 555-0122',
        city: 'Katy',
        service: 'ROOF',
        priority: 'NORMAL',
        stage: 'ESTIMATE',
        valueCents: 1_250_000,
        createdDaysAgo: 26,
        scheduled: [-21, '15:00'],
    },
    // Won
    {
        contactName: 'Chris Bell',
        phone: '(713) 555-0140',
        city: 'Houston',
        service: 'WATER_DAMAGE',
        priority: 'EMERGENCY',
        stage: 'WON',
        valueCents: 520_000,
        createdDaysAgo: 34,
        scheduled: [-33, '10:00'],
        wonDaysAgo: 5,
    },
    {
        contactName: 'Kimberly Tran',
        phone: '(832) 555-0158',
        city: 'Houston',
        service: 'MOLD',
        priority: 'HIGH',
        stage: 'WON',
        valueCents: 415_000,
        createdDaysAgo: 46,
        scheduled: [-44, '09:30'],
        wonDaysAgo: 18,
    },
    {
        contactName: 'Brad Moran',
        phone: '(281) 555-0111',
        city: 'Houston',
        service: 'ROOF',
        priority: 'NORMAL',
        stage: 'WON',
        valueCents: 2_110_000,
        createdDaysAgo: 100,
        scheduled: [-97, '12:00'],
        wonDaysAgo: 40,
    },
    {
        contactName: 'Mariana Sánchez',
        phone: '(713) 555-0186',
        city: 'Galveston',
        service: 'WATER_DAMAGE',
        priority: 'HIGH',
        stage: 'WON',
        valueCents: 640_000,
        createdDaysAgo: 80,
        scheduled: [-78, '14:00'],
        wonDaysAgo: 67,
    },
    {
        contactName: 'Preston Whitcomb',
        phone: '(281) 555-0139',
        city: 'The Woodlands',
        service: 'ROOF',
        priority: 'NORMAL',
        stage: 'WON',
        valueCents: 1_880_000,
        createdDaysAgo: 172,
        scheduled: [-168, '11:30'],
        wonDaysAgo: 96,
    },
    // Lost
    {
        contactName: 'Otis Grant',
        phone: '(713) 555-0164',
        city: 'Houston',
        service: 'WATER_DAMAGE',
        priority: 'NORMAL',
        stage: 'LOST',
        valueCents: 300_000,
        createdDaysAgo: 40,
        scheduled: [-36, '10:00'],
    },
    {
        contactName: 'Lena Byers',
        phone: '(832) 555-0198',
        city: 'Kingwood',
        service: 'ROOF',
        priority: 'NORMAL',
        stage: 'LOST',
        valueCents: 2_400_000,
        createdDaysAgo: 66,
        scheduled: [-60, '15:30'],
    },
];

let demoId = 0;

const toLead = (row: MockRow): Lead => ({
    id: `demo-${(demoId += 1)}`,
    contactName: row.contactName,
    phone: row.phone,
    city: row.city,
    service: row.service,
    priority: row.priority,
    stage: row.stage,
    valueCents: row.valueCents,
    scheduledAt: row.scheduled ? at(row.scheduled[0], row.scheduled[1]) : null,
    wonAt: row.wonDaysAgo != null ? daysAgo(row.wonDaysAgo) : null,
    createdAt: daysAgo(row.createdDaysAgo),
});

/** The working copy: drags, selects and new demo leads mutate this. */
const leads = ref<Lead[]>(MOCK_ROWS.map(toLead));

/* -------------------------------------------------------------- formatting */

const money = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    maximumFractionDigits: 0,
});

const moneyCompact = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    notation: 'compact',
    maximumFractionDigits: 1,
});

const clock = new Intl.DateTimeFormat('en-US', { hour: 'numeric', minute: '2-digit' });

const dayShort = new Intl.DateTimeFormat('en-US', { month: 'short', day: 'numeric' });

const monthShort = new Intl.DateTimeFormat('en-US', { month: 'short' });

const formatKpi = (format: string, value: number): string => {
    if (format === 'count') {
        return String(value);
    }

    if (format === 'minor') {
        return moneyCompact.format(value / 100);
    }

    if (format === 'minutes') {
        return `${value} min`;
    }

    return String(value);
};

const formatCardMoney = (minor: number | null): string =>
    minor === null ? '—' : money.format(minor / 100);

const formatScheduled = (date: Date): string => {
    const now = new Date();
    const sameDay =
        date.getFullYear() === now.getFullYear() &&
        date.getMonth() === now.getMonth() &&
        date.getDate() === now.getDate();

    return sameDay ? `Today · ${clock.format(date)}` : dayShort.format(date);
};

const ageInDays = (date: Date): string => {
    const days = Math.max(0, Math.floor((Date.now() - date.getTime()) / 86_400_000));

    return days === 0 ? 'today' : `${days}d`;
};

const subheadDate = new Intl.DateTimeFormat('en-US', {
    weekday: 'long',
    month: 'long',
    day: 'numeric',
}).format(new Date());

const todayList = computed(() =>
    (leads.value ?? [])
        .filter((lead) => {
            const scheduled = lead.scheduledAt;

            if (scheduled === null) {
                return false;
            }

            const now = new Date();

            return (
                scheduled.getFullYear() === now.getFullYear() &&
                scheduled.getMonth() === now.getMonth() &&
                scheduled.getDate() === now.getDate()
            );
        })
        .sort((a, b) => (a.scheduledAt?.getTime() ?? 0) - (b.scheduledAt?.getTime() ?? 0))
        .map((lead) => ({ ...lead, scheduledAt: lead.scheduledAt as Date })),
);

/* --------------------------------------------------------------------- KPIs */

const kpis = computed(() => {
    const now = new Date();
    const weekStart = now.getTime() - 7 * 86_400_000;
    const priorStart = weekStart - 7 * 86_400_000;

    const createdIn = (from: number, to: number): number =>
        leads.value.filter((lead) => {
            const at = lead.createdAt.getTime();

            return at >= from && at < to;
        }).length;

    const newThisWeek = createdIn(weekStart, now.getTime() + 1);
    const newPriorWeek = createdIn(priorStart, weekStart);
    const newDelta = newThisWeek - newPriorWeek;

    const open = leads.value.filter((lead) => OPEN_STAGES.includes(lead.stage));
    const openValue = open.reduce((sum, lead) => sum + (lead.valueCents ?? 0), 0);

    const wonInRange = (from: number, to: number): Lead[] =>
        leads.value.filter((lead) => {
            const won = lead.wonAt?.getTime() ?? -1;

            return lead.stage === 'WON' && won >= from && won < to;
        });

    const monthStart = new Date(now.getFullYear(), now.getMonth(), 1).getTime();
    const priorMonthStart = new Date(now.getFullYear(), now.getMonth() - 1, 1).getTime();

    const wonThis = wonInRange(monthStart, now.getTime() + 1);
    const wonPrior = wonInRange(priorMonthStart, monthStart);
    const wonThisTotal = wonThis.reduce((sum, lead) => sum + (lead.valueCents ?? 0), 0);
    const wonPriorTotal = wonPrior.reduce((sum, lead) => sum + (lead.valueCents ?? 0), 0);

    const wonTrend = (() => {
        if (wonThisTotal > wonPriorTotal) {
            return 'up';
        }

        if (wonThisTotal < wonPriorTotal) {
            return 'down';
        }

        return 'flat';
    })();

    const responses = leads.value
        .filter((lead) => lead.scheduledAt !== null)
        .map((lead) => ({ createdAt: lead.createdAt, scheduledAt: lead.scheduledAt as Date }));

    const avgResponseMinutes =
        responses.length === 0
            ? 0
            : Math.round(
                  responses.reduce(
                      (sum, row) =>
                          sum + (row.scheduledAt.getTime() - row.createdAt.getTime()) / 60_000,
                      0,
                  ) / responses.length,
              );

    const newDeltaLabel =
        newDelta > 0
            ? `+${newDelta} vs prior week`
            : newDelta < 0
              ? `${newDelta} vs prior week`
              : 'even vs prior week';

    const kpis: Kpi[] = [
        {
            key: 'new_leads',
            label: 'New leads · 7 days',
            format: 'count',
            value: newThisWeek,
            delta: newDeltaLabel,
            trend: newDelta > 0 ? 'up' : newDelta < 0 ? 'down' : 'flat',
            icon: 'pi pi-users',
        },
        {
            key: 'open_pipeline',
            label: 'Open pipeline value',
            format: 'minor',
            value: openValue,
            delta: `${open.length} open deals`,
            trend: 'flat',
            icon: 'pi pi-chart-line',
        },
        {
            key: 'won_month',
            label: 'Won this month',
            format: 'minor',
            value: wonThisTotal,
            delta: `${wonThis.length} closed this month`,
            trend: wonTrend,
            icon: 'pi pi-trophy',
        },
        {
            key: 'response_time',
            label: 'Avg. first response',
            format: 'minutes',
            value: avgResponseMinutes,
            delta: responses.length === 0 ? 'no inspections yet' : 'target ≤ 60 min',
            trend: 'flat',
            icon: 'pi pi-clock',
        },
    ];

    return kpis;
});

/** Six bars of won revenue — empty months zeroed, current month last. */
const revenue = computed(() => {
    const now = new Date();
    const first = new Date(now.getFullYear(), now.getMonth() - 5, 1);

    return Array.from({ length: 6 }, (_, index) => {
        const month = new Date(first.getFullYear(), first.getMonth() + index, 1);
        const nextMonth = new Date(month.getFullYear(), month.getMonth() + 1, 1);

        const minor = leads.value
            .filter((lead) => {
                const won = lead.wonAt;

                return (
                    lead.stage === 'WON' &&
                    won !== null &&
                    won.getTime() >= month.getTime() &&
                    won.getTime() < nextMonth.getTime()
                );
            })
            .reduce((sum, lead) => sum + (lead.valueCents ?? 0), 0);

        return { label: monthShort.format(month), minor };
    });
});

const revenueAriaLabel = computed(() =>
    [
        'Bar chart of won revenue for the last six months.',
        ...revenue.value.map(
            (month) => `${month.label}: ${moneyCompact.format(month.minor / 100)}`,
        ),
    ].join(' '),
);

/* -------------------------------------------------------------------- board */

const columns = computed(() =>
    STAGES.map((stage) => {
        const columnLeads = leads.value
            .filter((lead) => lead.stage === stage)
            .sort((a, b) => {
                const weight = PRIORITY_WEIGHT[a.priority] - PRIORITY_WEIGHT[b.priority];

                if (weight !== 0) {
                    return weight;
                }

                return a.createdAt.getTime() - b.createdAt.getTime();
            });

        return {
            stage,
            dot: STAGE_META[stage].dot,
            label: STAGE_META[stage].label,
            leads: columnLeads,
            total: columnLeads.reduce((sum, lead) => sum + (lead.valueCents ?? 0), 0),
        };
    }),
);

/* -------------------------------------------------------------- drag state */

const dragging = ref<string | null>(null);

const overStage = ref<Stage | null>(null);

/** Enter/leave counters per column so child flicker never flips the tint. */
const dropDepths = ref(new Map<Stage, number>());

const isOver = (stage: Stage): boolean =>
    overStage.value === stage && (dropDepths.value.get(stage) ?? 0) > 0;

const depth = (stage: Stage, delta: number): void => {
    dropDepths.value.set(stage, Math.max(0, (dropDepths.value.get(stage) ?? 0) + delta));
};

const enterColumn = (stage: Stage): void => {
    depth(stage, +1);
    overStage.value = stage;
};

const leaveColumn = (stage: Stage): void => {
    depth(stage, -1);

    if ((dropDepths.value.get(stage) ?? 0) <= 0 && overStage.value === stage) {
        overStage.value = null;
    }
};

const resetDrag = (): void => {
    dragging.value = null;
    overStage.value = null;
    dropDepths.value = new Map<Stage, number>();
};

const startDrag = (event: DragEvent, lead: Lead): void => {
    dragging.value = lead.id;
    event.dataTransfer?.setData('text/plain', lead.id);

    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
    }
};

/**
 * Browsers can fire a click right after a drag ends; a 160 ms guard keeps
 * that artifact from flinging the drawer open every time a card is dropped.
 */
let lastDragEndAt = 0;

const onDragEnd = (): void => {
    resetDrag();
    lastDragEndAt = Date.now();
};

/** Placeholder move: pure local state, nothing to reconcile with a server. */
const moveLead = (lead: Lead, stage: Stage): void => {
    if (lead.stage === stage) {
        return;
    }

    lead.stage = stage;
};

const dropOn = (stage: Stage): void => {
    const id = dragging.value;
    resetDrag();

    if (id === null) {
        return;
    }

    const lead = leads.value.find((row) => row.id === id);

    if (lead !== undefined) {
        moveLead(lead, stage);
    }
};

/* -------------------------------------------------------------- lead drawer */

const drawerVisible = ref(false);

/** The lead shown in the detail drawer; stays reactive while it mutates. */
const activeLead = ref<Lead | null>(null);

const openLead = (lead: Lead): void => {
    if (Date.now() - lastDragEndAt < 160) {
        return;
    }

    activeLead.value = lead;
    drawerVisible.value = true;
};

const phoneHref = computed(() =>
    activeLead.value === null ? '' : `tel:${activeLead.value.phone.replace(/\D/g, '')}`,
);

/* ----------------------------------------------------------- new lead dialog */

const showCreate = ref(false);

const form = reactive({
    contactName: '',
    phone: '',
    city: '',
    service: 'WATER_DAMAGE' as ServiceType,
    priority: 'NORMAL' as LeadPriority,
    dollars: '',
});

const canSubmit = computed(
    () => form.contactName.trim().length >= 2 && form.phone.trim().length >= 7,
);

const addLead = (): void => {
    if (!canSubmit.value) {
        return;
    }

    leads.value.push({
        id: `demo-${(demoId += 1)}`,
        contactName: form.contactName.trim(),
        phone: form.phone.trim(),
        city: form.city.trim() || '—',
        service: form.service,
        priority: form.priority,
        stage: 'NEW',
        valueCents: form.dollars === '' ? null : Math.round(Number(form.dollars) * 100),
        scheduledAt: null,
        wonAt: null,
        createdAt: new Date(),
    });

    Object.assign(form, { contactName: '', phone: '', city: '', dollars: '' });
    showCreate.value = false;
};

/* --------------------------------------------------------- revenue chart.js */

type ChartModule = typeof import('chart.js');

type ChartInstance = InstanceType<ChartModule['Chart']>;

const revenueCanvas = useTemplateRef<HTMLCanvasElement>('revenue-canvas');

let chartInstance: ChartInstance | null = null;

/** Monotonic draw slot: a pending await that is no longer the newest draw is
 * dropped after the chunk load resolves, so a stale chart can never attach. */
let drawEpoch = 0;

const readToken = (name: string): string =>
    getComputedStyle(document.documentElement).getPropertyValue(name).trim();

const destroyChart = (): void => {
    chartInstance?.destroy();
    chartInstance = null;
};

/**
 * Theme bridge. ThemeToggle writes `.dark` on <html>; a class-mutation
 * observer is the reactive source (the toggle is layout chrome, not a page
 * import). Initialised in onMounted, before the first chart draw.
 */
const isDark = ref(true);

/**
 * Builds the six-month revenue bars. chart.js is imported from its own
 * chunk on first call; every colour/font is read from the design tokens so
 * the chart follows the theme without a second palette in source.
 */
const drawRevenueChart = async (): Promise<ChartInstance | null> => {
    const epoch = (drawEpoch += 1);
    destroyChart();

    const canvas = revenueCanvas.value;

    if (canvas === null) {
        return null;
    }

    const { Chart, BarController, BarElement, CategoryScale, LinearScale, Tooltip } =
        await import('chart.js');

    if (epoch !== drawEpoch || canvas.isConnected === false) {
        return null;
    }

    Chart.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip);

    const dark = isDark.value;

    const accent = readToken('--p-primary-500');
    const accentHover = readToken('--p-primary-300');
    const tickColor = readToken('--p-text-muted-color');
    const gridColor = dark ? readToken('--p-surface-800') : readToken('--p-surface-200');
    const tooltipBg = dark ? readToken('--p-surface-700') : readToken('--p-surface-900');
    const tooltipFg = readToken('--p-surface-0');

    if (
        accent === '' ||
        accentHover === '' ||
        tickColor === '' ||
        gridColor === '' ||
        tooltipBg === '' ||
        tooltipFg === ''
    ) {
        // Design tokens are guaranteed by globals.css; an empty read means
        // the stylesheet is not mounted — skip the chart rather than paint
        // from a second palette.
        return null;
    }

    Chart.defaults.font.family = readToken('--font-sans') || Chart.defaults.font.family;

    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const chart = new Chart(canvas, {
        type: 'bar',
        data: {
            labels: revenue.value.map((month) => month.label),
            datasets: [
                {
                    label: 'Won revenue',
                    data: revenue.value.map((month) => month.minor / 100),
                    backgroundColor: accent,
                    hoverBackgroundColor: accentHover,
                    borderRadius: 8,
                    maxBarThickness: 32,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: reducedMotion ? false : { duration: 350 },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: tooltipBg,
                    titleColor: tooltipFg,
                    bodyColor: tooltipFg,
                    displayColors: false,
                    padding: 10,
                    callbacks: {
                        label: (item) => moneyCompact.format(Number(item.parsed.y)),
                    },
                },
            },
            scales: {
                x: {
                    border: { display: false },
                    grid: { display: false },
                    ticks: { color: tickColor, font: { weight: 500 } },
                },
                y: {
                    beginAtZero: true,
                    grace: '12%',
                    border: { display: false },
                    grid: { color: gridColor },
                    ticks: {
                        color: tickColor,
                        callback: (value) => moneyCompact.format(Number(value)),
                        maxTicksLimit: 5,
                    },
                },
            },
        },
    });

    chartInstance = chart;

    return chart;
};

onMounted(() => {
    isDark.value = document.documentElement.classList.contains('dark');

    const themeObserver = new MutationObserver(() => {
        isDark.value = document.documentElement.classList.contains('dark');
    });
    themeObserver.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['class'],
    });
    onUnmounted(() => themeObserver.disconnect());

    void drawRevenueChart();
});

/**
 * Data changes flow through the `revenue` computed; theme changes through
 * the observer above. `onWatcherCleanup` drops any pending chunk load when
 * either source fires again (or on unmount), so a stale draw never paints.
 */
const dirtyDraw = (): void => {
    onWatcherCleanup(() => {
        drawEpoch += 1;
    });

    void drawRevenueChart();
};

watch([isDark, revenue], dirtyDraw);

onUnmounted(() => destroyChart());
</script>

<template>
    <Head title="Dashboard · AquaShield" />

    <AppLayout title="Dashboard">
        <!-- Subhead + the screen's single primary action (calm-CRM pattern). -->
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p id="dashboard-intro" class="text-sm text-surface-500 dark:text-surface-400">
                Your pipeline ·
                <span class="font-medium text-surface-700 dark:text-surface-200">{{
                    subheadDate
                }}</span>
            </p>
            <Button
                label="New lead"
                icon="pi pi-plus"
                @click="showCreate = true"
                aria-describedby="dashboard-intro"
            />
        </div>

        <!-- ----------------------------------------------------------- KPIs -->
        <!-- The tile, its hover lift and its entrance stagger all live on
             KpiCard; the page only supplies data (formatted by formatKpi). -->
        <section
            id="kpis"
            aria-label="Key pipeline metrics"
            class="mt-6 grid scroll-mt-24 grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4"
        >
            <KpiCard
                v-for="(kpi, index) in kpis"
                :key="kpi.key"
                :label="kpi.label"
                :value="formatKpi(kpi.format, kpi.value)"
                :delta="kpi.delta"
                :trend="kpi.trend"
                :index="index"
                :icon="kpi.icon"
            />
        </section>

        <!-- ----------------------------------------------- Pipeline board -->
        <section
            id="pipeline"
            class="aq-rise mt-8 scroll-mt-24"
            :style="{ animationDelay: '140ms' }"
            aria-label="Pipeline board"
        >
            <div class="mb-2 flex items-center justify-between gap-3">
                <h2 class="text-sm font-semibold text-surface-900 dark:text-surface-0">
                    Leads pipeline
                </h2>
                <p class="text-xs text-surface-500 dark:text-surface-400">
                    Drag a card to move it · scroll for more stages
                </p>
            </div>

            <!-- Empty board invitation, before any columns ship -->
            <Card
                v-if="leads.length === 0"
                class="flex h-full items-center justify-center py-16 text-center"
            >
                <template #content>
                    <svg
                        aria-hidden="true"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.5"
                        class="mx-auto size-10 text-aq-accent"
                    >
                        <path
                            d="M9 3v3M15 3v3M9 21v3M15 21v3M3 9h3M3 15h3M18 9h3M18 15h3"
                            stroke-linecap="round"
                        />
                        <rect x="5" y="5" width="14" height="14" rx="3" />
                    </svg>
                    <p class="mt-3 font-medium text-surface-900 dark:text-surface-0">
                        Your board is empty
                    </p>
                    <p class="mt-1 max-w-sm text-sm text-surface-500 dark:text-surface-400">
                        Log your first lead and it will travel this pipeline from first call to
                        signed job.
                    </p>
                    <div class="mt-4 flex justify-center">
                        <Button label="New lead" icon="pi pi-plus" @click="showCreate = true" />
                    </div>
                </template>
            </Card>

            <!-- Columns: horizontal scroll carries the sixth stage on any screen -->
            <div v-else class="flex gap-3 overflow-x-auto pb-2">
                <div
                    v-for="column in columns"
                    :key="column.stage"
                    class="min-w-72 shrink-0 rounded-xl border p-2 transition-colors duration-150 motion-reduce:transition-none"
                    :class="[
                        'border-surface-200 bg-surface-50 dark:border-surface-800 dark:bg-surface-900/60',
                        isOver(column.stage)
                            ? 'ring-2 ring-aq-accent/50 bg-surface-100 dark:bg-surface-800/80'
                            : '',
                    ]"
                    :data-stage="column.stage"
                    @dragover.prevent
                    @dragenter.prevent="enterColumn(column.stage)"
                    @dragleave="leaveColumn(column.stage)"
                    @drop.prevent="dropOn(column.stage)"
                >
                    <header class="flex items-center justify-between gap-2 px-2 pb-2 pt-1">
                        <div class="flex items-center gap-2">
                            <span
                                aria-hidden="true"
                                class="size-2 rounded-full"
                                :class="column.dot"
                            ></span>
                            <h3 class="text-sm font-semibold text-surface-900 dark:text-surface-0">
                                {{ column.label }}
                            </h3>
                            <span
                                class="rounded-full bg-surface-200 px-2 py-px text-[11px] font-semibold tabular-nums text-surface-600 dark:bg-surface-700 dark:text-surface-300"
                            >
                                {{ column.leads.length }}
                            </span>
                        </div>
                        <span
                            v-if="column.total > 0"
                            class="text-xs font-medium tabular-nums text-surface-500 dark:text-surface-400"
                        >
                            {{ moneyCompact.format(column.total / 100) }}
                        </span>
                    </header>

                    <TransitionGroup tag="ol" name="kanban" class="flex min-h-28 flex-col gap-2">
                        <li v-for="lead in column.leads" :key="lead.id">
                            <!-- Whole card = one button: click/Enter opens the drawer,
                                 drag moves the card. Nested-native behaviour without
                                 nesting interactive elements. -->
                            <button
                                type="button"
                                draggable="true"
                                class="w-full cursor-grab rounded-lg border border-surface-200 bg-surface-0 p-3 text-left shadow-sm transition-[opacity,box-shadow,border-color] duration-150 hover:shadow-md active:cursor-grabbing focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent dark:border-surface-700 dark:bg-surface-800 motion-reduce:transition-none"
                                :class="[
                                    dragging === lead.id ? 'opacity-40' : '',
                                    lead.priority === 'EMERGENCY'
                                        ? 'border-s-2 border-s-aq-danger'
                                        : '',
                                ]"
                                @dragstart="startDrag($event, lead)"
                                @dragend="onDragEnd"
                                @click="openLead(lead)"
                            >
                                <span class="flex items-start justify-between gap-2">
                                    <span
                                        class="text-sm font-semibold text-surface-900 dark:text-surface-0"
                                    >
                                        {{ lead.contactName }}
                                    </span>
                                    <span
                                        v-if="lead.priority === 'EMERGENCY'"
                                        class="shrink-0 rounded-md bg-aq-danger/10 px-1.5 py-0.5 text-[10px] font-bold tracking-wide text-aq-danger"
                                    >
                                        EMERGENCY
                                    </span>
                                    <span
                                        v-else-if="lead.priority === 'HIGH'"
                                        class="shrink-0 rounded-md bg-aq-warning/10 px-1.5 py-0.5 text-[10px] font-bold tracking-wide text-aq-warning"
                                    >
                                        HIGH
                                    </span>
                                </span>

                                <span
                                    class="mt-0.5 flex items-center gap-1.5 text-xs text-surface-500 dark:text-surface-400"
                                >
                                    <span class="truncate">
                                        {{ SERVICE_LABEL[lead.service] }} · {{ lead.city }}
                                    </span>
                                    <span
                                        aria-hidden="true"
                                        class="text-surface-300 dark:text-surface-600"
                                    >
                                        ·
                                    </span>
                                    <span class="shrink-0 tabular-nums">{{
                                        ageInDays(lead.createdAt)
                                    }}</span>
                                </span>

                                <span class="mt-2 flex items-center justify-between gap-2 text-xs">
                                    <span
                                        class="font-semibold tabular-nums text-surface-900 dark:text-surface-100"
                                    >
                                        {{ formatCardMoney(lead.valueCents) }}
                                    </span>
                                    <span
                                        v-if="lead.scheduledAt !== null"
                                        class="inline-flex shrink-0 items-center gap-1 rounded-md bg-surface-100 px-1.5 py-0.5 tabular-nums text-surface-600 dark:bg-surface-700 dark:text-surface-300"
                                    >
                                        <svg
                                            aria-hidden="true"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            class="size-3"
                                        >
                                            <rect x="3" y="4" width="18" height="18" rx="2" />
                                            <path d="M16 2v4M8 2v4M3 10h18" />
                                        </svg>
                                        {{ formatScheduled(lead.scheduledAt) }}
                                    </span>
                                </span>
                            </button>
                        </li>
                    </TransitionGroup>

                    <p
                        v-if="column.leads.length === 0"
                        class="rounded-lg border border-dashed border-surface-200 py-6 text-center text-xs text-surface-400 dark:border-surface-700 dark:text-surface-500"
                    >
                        Drop a card here
                    </p>
                </div>
            </div>
        </section>

        <!-- ------------------------------------------------ Won revenue chart -->
        <section
            id="revenue"
            class="aq-rise mt-6 scroll-mt-24"
            :style="{ animationDelay: '260ms' }"
            aria-label="Won revenue, last six months"
        >
            <Card>
                <template #title>
                    <span class="text-sm font-semibold text-surface-900 dark:text-surface-0">
                        Won revenue
                    </span>
                </template>
                <template #subtitle>
                    <span class="text-xs text-surface-500 dark:text-surface-400">
                        Last 6 months · signed jobs
                    </span>
                </template>
                <template #content>
                    <div class="relative h-64">
                        <canvas
                            ref="revenue-canvas"
                            class="h-full w-full"
                            role="img"
                            :aria-label="revenueAriaLabel"
                        >
                        </canvas>
                    </div>
                    <p class="sr-only">{{ revenueAriaLabel }}</p>
                </template>
            </Card>
        </section>

        <!-- ------------------------------------------------- Scheduled today -->
        <section
            id="schedule"
            class="aq-rise mt-6 scroll-mt-24"
            :style="{ animationDelay: '340ms' }"
            aria-label="Field schedule for today"
        >
            <Card>
                <template #title>
                    <span class="flex items-center gap-2">
                        <span class="text-sm font-semibold text-surface-900 dark:text-surface-0">
                            Scheduled today
                        </span>
                        <span
                            class="rounded-full bg-surface-200 px-2 py-px text-[11px] font-semibold tabular-nums text-surface-600 dark:bg-surface-700 dark:text-surface-300"
                        >
                            {{ todayList.length }}
                        </span>
                    </span>
                </template>
                <template #subtitle>
                    <span class="text-xs text-surface-500 dark:text-surface-400">
                        Inspections and estimates on the board's calendar
                    </span>
                </template>
                <template #content>
                    <p
                        v-if="todayList.length === 0"
                        class="flex items-center justify-center py-8 text-center text-sm text-surface-500 dark:text-surface-400"
                    >
                        Nothing on the field calendar for today.
                    </p>
                    <ol v-else class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        <li
                            v-for="item in todayList"
                            :key="item.id"
                            class="flex items-center gap-3 rounded-lg border border-surface-200 bg-surface-50 px-3 py-2 transition-shadow duration-150 hover:shadow-sm dark:border-surface-700 dark:bg-surface-800/60 motion-reduce:transition-none"
                        >
                            <span
                                class="w-16 shrink-0 text-xs font-semibold tabular-nums text-aq-accent"
                            >
                                {{ clock.format(item.scheduledAt) }}
                            </span>
                            <span class="min-w-0 flex-1">
                                <span
                                    class="block truncate text-sm font-medium text-surface-900 dark:text-surface-0"
                                >
                                    {{ item.contactName }}
                                </span>
                                <span class="block text-xs text-surface-500 dark:text-surface-400">
                                    {{ SERVICE_LABEL[item.service] }} · {{ item.city }}
                                </span>
                            </span>
                            <span
                                class="shrink-0 rounded-md bg-surface-100 px-1.5 py-0.5 text-[10px] font-bold tracking-wide text-surface-600 uppercase dark:bg-surface-700 dark:text-surface-300"
                            >
                                {{ STAGE_META[item.stage].label }}
                            </span>
                        </li>
                    </ol>
                </template>
            </Card>
        </section>

        <!-- --------------------------------------------------- New lead dialog -->
        <Dialog
            v-model:visible="showCreate"
            modal
            header="New lead"
            :style="{ width: '26rem' }"
            class="rounded-2xl"
        >
            <form class="flex flex-col gap-3" @submit.prevent="addLead">
                <p class="text-xs text-surface-500 dark:text-surface-400">
                    Demo placeholder — leads stay local and reset on reload.
                </p>

                <div>
                    <label
                        for="lead-contact-name"
                        class="block text-xs font-semibold uppercase tracking-wide text-surface-500 dark:text-surface-400"
                    >
                        Contact name
                    </label>
                    <input
                        id="lead-contact-name"
                        v-model="form.contactName"
                        type="text"
                        maxlength="120"
                        autocomplete="off"
                        class="mt-1 h-10 w-full rounded-xl border border-surface-200 bg-surface-0 px-3 text-sm text-surface-900 transition-colors duration-150 hover:border-surface-300 focus-visible:border-aq-accent focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent dark:border-surface-700 dark:bg-surface-900 dark:text-surface-0 motion-reduce:transition-none"
                    />
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label
                            for="lead-phone"
                            class="block text-xs font-semibold uppercase tracking-wide text-surface-500 dark:text-surface-400"
                        >
                            Phone
                        </label>
                        <input
                            id="lead-phone"
                            v-model="form.phone"
                            type="tel"
                            maxlength="24"
                            autocomplete="off"
                            class="mt-1 h-10 w-full rounded-xl border border-surface-200 bg-surface-0 px-3 text-sm tabular-nums text-surface-900 transition-colors duration-150 hover:border-surface-300 focus-visible:border-aq-accent focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent dark:border-surface-700 dark:bg-surface-900 dark:text-surface-0 motion-reduce:transition-none"
                        />
                    </div>
                    <div>
                        <label
                            for="lead-city"
                            class="block text-xs font-semibold uppercase tracking-wide text-surface-500 dark:text-surface-400"
                        >
                            City
                        </label>
                        <input
                            id="lead-city"
                            v-model="form.city"
                            type="text"
                            maxlength="80"
                            autocomplete="off"
                            class="mt-1 h-10 w-full rounded-xl border border-surface-200 bg-surface-0 px-3 text-sm text-surface-900 transition-colors duration-150 hover:border-surface-300 focus-visible:border-aq-accent focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent dark:border-surface-700 dark:bg-surface-900 dark:text-surface-0 motion-reduce:transition-none"
                        />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label
                            for="lead-service"
                            class="block text-xs font-semibold uppercase tracking-wide text-surface-500 dark:text-surface-400"
                        >
                            Service
                        </label>
                        <select
                            id="lead-service"
                            v-model="form.service"
                            class="mt-1 h-10 w-full cursor-pointer rounded-xl border border-surface-200 bg-surface-0 px-2 text-sm text-surface-900 transition-colors duration-150 hover:border-surface-300 focus-visible:border-aq-accent focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent dark:border-surface-700 dark:bg-surface-900 dark:text-surface-0 motion-reduce:transition-none"
                        >
                            <option
                                v-for="(label, value) in SERVICE_LABEL"
                                :key="value"
                                :value="value"
                            >
                                {{ label }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label
                            for="lead-priority"
                            class="block text-xs font-semibold uppercase tracking-wide text-surface-500 dark:text-surface-400"
                        >
                            Priority
                        </label>
                        <select
                            id="lead-priority"
                            v-model="form.priority"
                            class="mt-1 h-10 w-full cursor-pointer rounded-xl border border-surface-200 bg-surface-0 px-2 text-sm text-surface-900 transition-colors duration-150 hover:border-surface-300 focus-visible:border-aq-accent focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent dark:border-surface-700 dark:bg-surface-900 dark:text-surface-0 motion-reduce:transition-none"
                        >
                            <option
                                v-for="(label, value) in PRIORITY_LABEL"
                                :key="value"
                                :value="value"
                            >
                                {{ label }}
                            </option>
                        </select>
                    </div>
                </div>

                <div>
                    <label
                        for="lead-value"
                        class="block text-xs font-semibold uppercase tracking-wide text-surface-500 dark:text-surface-400"
                    >
                        Estimated value (USD)
                    </label>
                    <input
                        id="lead-value"
                        v-model="form.dollars"
                        type="number"
                        min="0"
                        max="2000000"
                        step="any"
                        inputmode="decimal"
                        autocomplete="off"
                        placeholder="e.g. 4850"
                        class="mt-1 h-10 w-full rounded-xl border border-surface-200 bg-surface-0 px-3 text-sm tabular-nums text-surface-900 transition-colors duration-150 hover:border-surface-300 focus-visible:border-aq-accent focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent dark:border-surface-700 dark:bg-surface-900 dark:text-surface-0 motion-reduce:transition-none"
                    />
                </div>

                <div class="mt-2 flex justify-end gap-2">
                    <Button
                        label="Cancel"
                        severity="secondary"
                        type="button"
                        @click="showCreate = false"
                    />
                    <Button label="Save lead" type="submit" :disabled="!canSubmit" />
                </div>
            </form>
        </Dialog>

        <!-- -------------------------------------------------- Lead detail drawer -->
        <Drawer v-model:visible="drawerVisible" position="right">
            <template #header>
                <div v-if="activeLead !== null" class="flex min-w-0 flex-col gap-1">
                    <p
                        class="truncate text-lg font-bold text-surface-900 dark:text-surface-0"
                        :title="activeLead.contactName"
                    >
                        {{ activeLead.contactName }}
                    </p>
                    <p class="flex flex-wrap items-center gap-1.5">
                        <span
                            v-if="activeLead.priority === 'EMERGENCY'"
                            class="rounded-md bg-aq-danger/10 px-1.5 py-0.5 text-[10px] font-bold tracking-wide text-aq-danger"
                        >
                            EMERGENCY
                        </span>
                        <span
                            v-else-if="activeLead.priority === 'HIGH'"
                            class="rounded-md bg-aq-warning/10 px-1.5 py-0.5 text-[10px] font-bold tracking-wide text-aq-warning"
                        >
                            HIGH
                        </span>
                        <span
                            class="rounded-md bg-surface-100 px-1.5 py-0.5 text-[10px] font-semibold tracking-wide text-surface-600 dark:bg-surface-700 dark:text-surface-300"
                        >
                            {{ SERVICE_LABEL[activeLead.service] }}
                        </span>
                        <span class="text-xs text-surface-400 dark:text-surface-500">
                            logged {{ ageInDays(activeLead.createdAt) }}
                        </span>
                    </p>
                </div>
            </template>

            <div v-if="activeLead !== null" class="flex flex-col gap-5">
                <dl class="flex flex-col gap-3">
                    <div class="flex items-baseline justify-between gap-3">
                        <dt
                            class="text-xs font-semibold uppercase tracking-wide text-surface-500 dark:text-surface-400"
                        >
                            City
                        </dt>
                        <dd class="text-sm text-surface-900 dark:text-surface-0">
                            {{ activeLead.city }}
                        </dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-3">
                        <dt
                            class="text-xs font-semibold uppercase tracking-wide text-surface-500 dark:text-surface-400"
                        >
                            Phone
                        </dt>
                        <dd>
                            <a
                                :href="phoneHref"
                                class="text-sm font-medium tabular-nums text-aq-accent transition-[color,text-decoration-color] duration-150 underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent motion-reduce:transition-none"
                            >
                                {{ activeLead.phone }}
                            </a>
                        </dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-3">
                        <dt
                            class="text-xs font-semibold uppercase tracking-wide text-surface-500 dark:text-surface-400"
                        >
                            Est. value
                        </dt>
                        <dd
                            class="text-sm font-semibold tabular-nums text-surface-900 dark:text-surface-0"
                        >
                            {{ formatCardMoney(activeLead.valueCents) }}
                        </dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-3">
                        <dt
                            class="text-xs font-semibold uppercase tracking-wide text-surface-500 dark:text-surface-400"
                        >
                            Scheduled
                        </dt>
                        <dd class="text-sm tabular-nums text-surface-900 dark:text-surface-0">
                            <template v-if="activeLead.scheduledAt !== null">
                                {{ formatScheduled(activeLead.scheduledAt) }}
                            </template>
                            <template v-else>Not scheduled</template>
                        </dd>
                    </div>
                </dl>

                <!-- The keyboard/AT twin of dragging. -->
                <div>
                    <label
                        for="lead-stage-drawer"
                        class="block text-xs font-semibold uppercase tracking-wide text-surface-500 dark:text-surface-400"
                    >
                        Move to stage
                    </label>
                    <select
                        id="lead-stage-drawer"
                        v-model="activeLead.stage"
                        class="mt-1 h-10 w-full cursor-pointer rounded-xl border border-surface-200 bg-surface-0 px-2 text-sm text-surface-900 transition-colors duration-150 hover:border-surface-300 focus-visible:border-aq-accent focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent dark:border-surface-700 dark:bg-surface-900 dark:text-surface-0 motion-reduce:transition-none"
                    >
                        <option v-for="stage in STAGES" :key="stage" :value="stage">
                            {{ STAGE_META[stage].label }}
                        </option>
                    </select>
                    <p class="mt-1.5 text-xs text-surface-400 dark:text-surface-500">
                        Keyboard twin of the board's drag — the card moves live.
                    </p>
                </div>
            </div>
        </Drawer>
    </AppLayout>
</template>
