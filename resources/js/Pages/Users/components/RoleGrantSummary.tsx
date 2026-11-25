/**
 * A read-only preview of what the selected roles grant.
 *
 * The access catalog already ships each role's permission list; rendering it is
 * what makes "a role grants its permission set" checkable before a write, and
 * keeps the payload from being a field the server computes and nobody reads.
 */
import { Tag } from '@/Components/ui/tag';

interface RoleGrantSummaryProps {
    catalog: Modules.Users.Data.UserAccessCatalogData;
    selectedRoles: readonly string[];
}

export default function RoleGrantSummary({ catalog, selectedRoles }: RoleGrantSummaryProps) {
    if (selectedRoles.length === 0) {
        return null;
    }

    const granted = [
        ...new Set(
            catalog.roles
                .filter((role) => selectedRoles.includes(role.name))
                .flatMap((role) => role.permissions),
        ),
    ].sort();

    return (
        <div className="rounded-lg border border-surface-200 bg-surface-50 px-3 py-2 dark:border-surface-800 dark:bg-surface-900/60">
            <p className="text-xs font-medium text-surface-700 dark:text-surface-200">
                Granted by the selected {selectedRoles.length === 1 ? 'role' : 'roles'}
            </p>

            {granted.length === 0 ? (
                <p className="mt-1 text-xs text-surface-500 dark:text-surface-300">
                    No permissions.
                </p>
            ) : (
                <span className="mt-1.5 flex flex-wrap gap-1">
                    {granted.map((permission) => (
                        <Tag key={permission} variant="outline">
                            {permission}
                        </Tag>
                    ))}
                </span>
            )}
        </div>
    );
}
