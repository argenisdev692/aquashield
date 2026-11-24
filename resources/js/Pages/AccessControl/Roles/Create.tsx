import { Head } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import RoleForm from './components/RoleForm';

interface RolesCreateProps {
    catalog: Modules.AccessControl.Data.PermissionCatalogData;
}

export default function RolesCreate({ catalog }: RolesCreateProps) {
    return (
        <AppLayout title="Create role">
            <Head title="Create role" />

            <div className="mb-6">
                <h1 className="text-xl font-semibold tracking-tight text-surface-900 dark:text-surface-0">
                    Create role
                </h1>
                <p className="mt-1 text-sm text-surface-600 dark:text-surface-300">
                    Name the role and choose the capabilities it grants. The permission matrix is
                    searchable and grouped by module.
                </p>
            </div>

            <RoleForm catalog={catalog} />
        </AppLayout>
    );
}
