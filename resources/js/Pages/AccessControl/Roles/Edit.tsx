import { Head } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import RoleForm from './components/RoleForm';

interface RolesEditProps {
    role: Modules.AccessControl.Data.RoleDetailData;
    catalog: Modules.AccessControl.Data.PermissionCatalogData;
}

export default function RolesEdit({ role, catalog }: RolesEditProps) {
    return (
        <AppLayout title={`Edit ${role.name}`}>
            <Head title={`Edit ${role.name}`} />

            <div className="mb-6">
                <div className="flex flex-wrap items-center gap-2">
                    <h2 className="text-xl font-semibold tracking-tight text-surface-900 dark:text-surface-0">
                        {role.name}
                    </h2>
                    <span className="text-xs font-medium tracking-tight text-surface-500 dark:text-surface-400">
                        {role.users_count} user{role.users_count === 1 ? '' : 's'}
                    </span>
                </div>
                <p className="mt-1 text-sm text-surface-600 dark:text-surface-300">
                    Saving replaces the permission set. The role name is fixed for system roles.
                </p>
            </div>

            <RoleForm role={role} catalog={catalog} />
        </AppLayout>
    );
}
