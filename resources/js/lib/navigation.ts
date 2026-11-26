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
    Building2,
    CalendarClock,
    CalendarDays,
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

/**
 * The rail's information architecture. Every destination declares its section
 * here, so a new module cannot land in the unlabelled void between groups and
 * the palette keeps the same mental model as the rail.
 */
export type NavSection = 'primary' | 'workspace' | 'organization' | 'system' | 'account';

export interface NavItem {
    readonly label: string;
    readonly href: string;
    /** Lucide icon component the row renders. */
    readonly icon: LucideIcon;
    /** Extra words the palette filter should match but the row should not show. */
    readonly keywords: string;
    /** Section heading the rail renders above this destination. */
    readonly section: NavSection;
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
        // Dashboard anchors the rail: it leads the list on its own and never
        // folds. The three daily work destinations sit under the Workspace
        // disclosure below it.
        label: 'Dashboard',
        href: DASHBOARD_PATH,
        icon: LayoutDashboard,
        keywords: 'home overview start workspace pipeline crm',
        section: 'primary',
    },
    {
        label: 'Leads',
        href: '/leads',
        icon: KanbanSquare,
        keywords: 'leads pipeline kanban board prospects meta ads inspections crud contacts',
        section: 'workspace',
        permission: 'VIEW_ANY_LEAD',
    },
    {
        label: 'Appointments',
        href: '/appointments',
        icon: CalendarClock,
        keywords: 'appointments calendar inspections estimates visits schedule reschedule cancel',
        section: 'workspace',
        permission: 'VIEW_ANY_APPOINTMENT',
    },
    {
        label: 'Availability',
        href: '/availability',
        icon: CalendarDays,
        keywords:
            'availability days off closures holidays crew schedule weekly hours exceptions blocks',
        section: 'workspace',
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
        section: 'organization',
        permission: 'VIEW_COMPANY_DATA',
    },
    {
        label: 'Users',
        href: '/users',
        icon: Users,
        keywords: 'users team staff people accounts access roles permissions invitations',
        section: 'organization',
        capability: 'manageStaff',
    },
    {
        label: 'Roles',
        href: '/roles',
        icon: ShieldCheck,
        keywords: 'roles rbac access control permission matrix catalogue grants',
        section: 'system',
        capability: 'manageAccess',
        permission: 'VIEW_ANY_ROLE',
    },
    {
        label: 'Permissions',
        href: '/permissions',
        icon: LockKeyhole,
        keywords: 'permissions capabilities catalogue access control system custom groups',
        section: 'system',
        capability: 'manageAccess',
        permission: 'VIEW_ANY_PERMISSION',
    },
    {
        label: 'Backups',
        href: '/backups',
        icon: Database,
        keywords: 'backups database dumps archives restore download retention quarantine',
        section: 'system',
        permission: 'VIEW_ANY_BACKUPS',
    },
    {
        label: 'Activity log',
        href: '/activity',
        icon: History,
        keywords: 'audit activity log history events security trail',
        section: 'system',
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
    section: 'account',
};

/**
 * The account utility group. It closes the list on every screen, so Settings
 * never lands between two work destinations and never changes position.
 */
const ACCOUNT_NAV: NavGroup = { label: 'Account', items: [SETTINGS_NAV] };

/**
 * The rail's section order. Labels are rendered uppercase by the row component,
 * so the source keeps normal sentence case.
 *
 * The primary group carries no label on purpose: Dashboard is a standalone row
 * at the top of the rail, and Workspace folds the three daily work destinations
 * (Leads, Appointments, Availability) beneath that row. A section with no label
 * renders no heading and therefore no disclosure arrow.
 */
const NAV_SECTIONS: readonly { key: Exclude<NavSection, 'account'>; label?: string }[] = [
    { key: 'primary' },
    { key: 'workspace', label: 'Workspace' },
    { key: 'organization', label: 'Organization' },
    { key: 'system', label: 'System' },
];

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
        if (item.capability === 'manageStaff' && !capabilities.manageStaff) {
            return false;
        }

        if (item.capability === 'manageAccess' && !capabilities.manageAccess) {
            return false;
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
        section: 'workspace',
        anchor: true,
    },
    {
        label: 'Pipeline',
        href: '#pipeline',
        icon: KanbanSquare,
        keywords: 'kanban stages deals board drag calendar month appointments',
        section: 'workspace',
        anchor: true,
    },
];

/**
 * The permitted destinations, split into the rail's real sections. A section
 * with nothing visible is dropped rather than rendered as an empty heading.
 */
export const groupedPrimaryNav = (
    permissions: readonly string[],
    capabilities: NavCapabilities = { manageStaff: false, manageAccess: false },
): readonly NavGroup[] => {
    const visible = visiblePrimaryNav(permissions, capabilities);

    return NAV_SECTIONS.map(({ key, label }) => ({
        label,
        items: visible.filter((item) => item.section === key),
    })).filter((group) => group.items.length > 0);
};

/**
 * Rail groups for every screen: the permission-filtered work sections and the
 * account group last. The dashboard's "On this page" documentation links are
 * not repeated here — its own segmented view selector owns that job now.
 */
export const dashboardGroups = (
    _currentPath: string,
    permissions: readonly string[],
    capabilities: NavCapabilities = { manageStaff: false, manageAccess: false },
): readonly NavGroup[] => [...groupedPrimaryNav(permissions, capabilities), ACCOUNT_NAV];

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
