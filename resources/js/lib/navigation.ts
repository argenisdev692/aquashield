/**
 * The one navigation model.
 *
 * The desktop rail, the mobile drawer and the ⌘K palette all read from here, so
 * a destination can never exist in one surface and be missing from another. The
 * list is deliberately short: every entry is a real admin destination, and
 * adding a module means adding its route here and nothing else.
 *
 * `anchor` entries are same-page targets, not Inertia visits. They only make
 * sense while the dashboard is mounted, which is why `dashboardGroups` gates
 * them on the current path instead of shipping a link that would do nothing on
 * the auth screens.
 */
import type { LucideIcon } from 'lucide-react';
import {
    Activity,
    Building2,
    Calendar,
    CalendarClock,
    CalendarDays,
    ChartLine,
    ChartColumn,
    Database,
    History,
    KanbanSquare,
    LayoutDashboard,
    LockKeyhole,
    Settings,
    ShieldCheck,
    Users,
} from 'lucide-react';

export const DASHBOARD_PATH = '/dashboard';

export interface NavItem {
    readonly label: string;
    readonly href: string;
    /** Lucide icon component the row renders. */
    readonly icon: LucideIcon;
    /** Extra words the palette filter should match but the row should not show. */
    readonly keywords: string;
    /** Permission required to see the destination; omitted for every-user rows. */
    readonly permission?: string;
    /** Non-permission capability (a Gate answer), e.g. staff management. */
    readonly capability?: 'manageStaff' | 'manageAccess';
    /** Same-page target (`#id`) instead of a route visit. */
    readonly anchor?: boolean;
}

export interface NavGroup {
    /** Omitted for the primary group, which needs no heading. */
    readonly label?: string;
    readonly items: readonly NavItem[];
}

export const PRIMARY_NAV: readonly NavItem[] = [
    {
        label: 'Dashboard',
        href: DASHBOARD_PATH,
        icon: LayoutDashboard,
        keywords: 'home overview start workspace pipeline crm',
    },
    {
        label: 'Leads',
        href: '/leads',
        icon: KanbanSquare,
        keywords: 'leads pipeline kanban board prospects meta ads inspections crud contacts',
        permission: 'VIEW_ANY_LEAD',
    },
    {
        label: 'Appointments',
        href: '/appointments',
        icon: CalendarClock,
        keywords: 'appointments calendar inspections estimates visits schedule reschedule cancel',
        permission: 'VIEW_ANY_APPOINTMENT',
    },
    {
        label: 'Availability',
        href: '/availability',
        icon: CalendarDays,
        keywords:
            'availability days off closures holidays crew schedule weekly hours exceptions blocks',
        permission: 'VIEW_ANY_AVAILABILITY_EXCEPTION',
    },
    {
        // The CompanyData module's one screen. Adding the module to the shell is
        // this entry and nothing else: the rail, the drawer and the palette read
        // from here.
        label: 'Company',
        href: '/company-data/edit',
        icon: Building2,
        keywords: 'company profile settings branding contact address billing bank socials',
        permission: 'VIEW_COMPANY_DATA',
    },
    {
        label: 'Users',
        href: '/users',
        icon: Users,
        keywords: 'users team staff people accounts access roles permissions invitations',
        capability: 'manageStaff',
    },
    {
        label: 'Roles',
        href: '/roles',
        icon: ShieldCheck,
        keywords: 'roles rbac access control permission matrix catalogue grants',
        capability: 'manageAccess',
    },
    {
        label: 'Permissions',
        href: '/permissions',
        icon: LockKeyhole,
        keywords: 'permissions capabilities catalogue access control system custom groups',
        capability: 'manageAccess',
    },
    {
        label: 'Backups',
        href: '/backups',
        icon: Database,
        keywords: 'backups database dumps archives restore download retention quarantine',
        permission: 'VIEW_ANY_BACKUPS',
    },
    {
        label: 'Activity log',
        href: '/activity',
        icon: History,
        keywords: 'audit activity log history events security trail',
        permission: 'VIEW_ANY_ACTIVITY_LOGS',
    },
];

/**
 * The account destination. It is a normal rail row, but it belongs at the foot
 * of the list with the other account utilities rather than between the daily
 * work destinations. No permission: every signed-in user owns their account.
 */
export const SETTINGS_NAV: NavItem = {
    label: 'Settings',
    href: '/settings/profile',
    icon: Settings,
    keywords: 'settings profile account security password passkey two-factor preferences',
};

/**
 * The account utility group. It closes the list on every screen, so Settings
 * never lands between two work destinations and never changes position when
 * the dashboard's contextual group appears.
 */
const ACCOUNT_NAV: NavGroup = { items: [SETTINGS_NAV] };

export interface NavCapabilities {
    readonly manageStaff: boolean;
    readonly manageAccess: boolean;
}

export const visiblePrimaryNav = (
    permissions: readonly string[],
    capabilities: NavCapabilities = { manageStaff: false, manageAccess: false },
): readonly NavItem[] => {
    const granted = new Set(permissions);

    return PRIMARY_NAV.filter((item) => {
        if (item.capability === 'manageStaff') {
            return capabilities.manageStaff;
        }

        if (item.capability === 'manageAccess') {
            return capabilities.manageAccess;
        }

        return item.permission === undefined || granted.has(item.permission);
    });
};

/**
 * The dashboard's real section ids. `Dashboard.tsx` owns the markup; these hrefs
 * are the scrolling contract between that page and the shell.
 */
export const DASHBOARD_SECTIONS: readonly NavItem[] = [
    {
        label: 'Key metrics',
        href: '#kpis',
        icon: ChartColumn,
        keywords: 'kpi leads open pipeline value won response time',
        anchor: true,
    },
    {
        label: 'Pipeline',
        href: '#pipeline',
        icon: KanbanSquare,
        keywords: 'kanban stages deals board drag calendar month appointments',
        anchor: true,
    },
    {
        label: 'Revenue',
        href: '#revenue',
        icon: ChartLine,
        keywords: 'won money months signed jobs chart',
        anchor: true,
    },
    {
        label: 'Schedule',
        href: '#schedule',
        icon: Calendar,
        keywords: 'today inspections estimates appointments field',
        anchor: true,
    },
];

/**
 * Rail groups for the current route: the permission-filtered primary group,
 * the dashboard's "On this page" anchor group only while mounted, and the
 * account group last.
 */
export const dashboardGroups = (
    currentPath: string,
    permissions: readonly string[],
    capabilities: NavCapabilities = { manageStaff: false, manageAccess: false },
): readonly NavGroup[] => {
    const groups: NavGroup[] = [{ items: visiblePrimaryNav(permissions, capabilities) }];

    if (currentPath === DASHBOARD_PATH) {
        groups.push({ label: 'On this page', items: DASHBOARD_SECTIONS });
    }

    groups.push(ACCOUNT_NAV);

    return groups;
};

/** `/dashboard` and everything beneath it, never a prefix of another path. */
export const isActivePath = (currentPath: string, href: string): boolean =>
    !href.startsWith('#') && (currentPath === href || currentPath.startsWith(`${href}/`));

export const sectionId = (href: string): string => href.replace(/^#/, '');

/**
 * Scrolls to a section with the offset the sections already declare via
 * `scroll-mt-24`, honouring reduced-motion. The palette and the rail share this
 * so the two cannot disagree about motion or target resolution.
 */
export const jumpToSection = (id: string): void => {
    const target = document.getElementById(id);

    if (target === null) {
        return;
    }

    target.scrollIntoView({
        behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
        block: 'start',
    });
};
