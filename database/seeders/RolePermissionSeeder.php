<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    private const string GUARD = 'web';

    /**
     * All application roles.
     *
     * @var list<string>
     */
    private const array ROLES = [
        'SUPER_ADMIN',
        'ADMIN',
        'MANAGER',
        'USER',
        'MARKETING_MANAGER',
        'DIRECTOR_ASSISTANT',
        'TECHNICAL_SUPERVISOR',
        'REPRESENTATION_COMPANY',
        'PUBLIC_COMPANY',
        'EXTERNAL_OPERATORS',
        'PUBLIC_ADJUSTER',
        'INSURANCE_ADJUSTER',
        'TECHNICAL_SERVICES',
        'MARKETING',
        'WAREHOUSE',
        'ADMINISTRATIVE',
        'COLLECTIONS',
        'REPORTES',
        'SALESPERSON',
        'LEAD',
        'EMPLOYEES',
        'CLIENT',
        'CONTACT',
        'SPECTATOR',
        'MODERATOR',
        'GUEST',
    ];

    /**
     * Standard AquaShield restoration & ops modules — each gets the full CRUD action set.
     * Permission name format: "{ACTION}_{MODULE}" (e.g. "VIEW_ANY_CLAIM").
     *
     * @var list<string>
     */
    private const array MODULES = [
        'USER',
        'EMAIL_DATA',
        'SERVICE_CATEGORY',
        'PORTFOLIO',
        'COMPANY_DATA',
        'PROJECT_TYPE',
        'APPOINTMENT',
        'BLOG_CATEGORY',
        'POST',
        'SEO',
        'CALL_RECORD',
        'MODEL_AI',
        'ROLE',
        'PERMISSION',
        'INSURANCE_COMPANY',
        'INVOICE_DEMO',
        'INVOICE',
        'PUBLIC_COMPANY',
        'TYPE_DAMAGE',
        'CAUSE_OF_LOSS',
        'CLAIM_STATU',
        'ALLIANCE_COMPANY',
        'ZONE',
        'CATEGORY_PRODUCT',
        'CLAIM',
        'SCOPE_SHEET',
        'MANAGER',
        'SALESPERSON',
        'MARKETING_MANAGER',
        'DIRECTOR_ASSISTANT',
        'TECHNICAL_SUPERVISOR',
        'REPRESENTATION_COMPANY',
        'EXTERNAL_OPERATORS',
        'PUBLIC_ADJUSTER',
        'INSURANCE_ADJUSTER',
        'TECHNICAL_SERVICES',
        'MARKETING',
        'WAREHOUSE',
        'ADMINISTRATIVE',
        'COLLECTIONS',
        'REPORTES',
        'LEAD',
        'EMPLOYEES',
        'CLIENT',
        'CONTACT',
        'SPECTATOR',
        'W9FORM',
        'SALESPERSON_SIGNATURE',
        'SERVICE_REQUEST',
        'PRODUCT',
        'MORTGAGE_COMPANY',
        'CUSTOMER',
        'PROPERTIES',
    ];

    /**
     * Singleton records: provisioned in place, never listed or created anew.
     *
     * @var list<string>
     */
    private const array SINGLETON_MODULES = ['COMPANY_DATA'];

    /** @var list<string> */
    private const array SINGLETON_ACTIONS = ['VIEW', 'UPDATE', 'DELETE', 'RESTORE'];

    /**
     * Full action set granted to standard modules.
     *
     * @var list<string>
     */
    private const array MODULES_ACTIONS = [
        'VIEW_ANY',
        'VIEW',
        'CREATE',
        'UPDATE',
        'DELETE',
        'RESTORE',
        'BULK_DELETE',
        'BULK_RESTORE',
        'EXPORT',
    ];

    /**
     * Operational modules the ADMIN role manages directly.
     *
     * @var list<string>
     */
    private const array ADMIN_MODULES = [
        'APPOINTMENT',
        'CALL_RECORD',
        'INVOICE',
        'PRODUCT',
        'CUSTOMER',
        'PROPERTIES',
        'CLAIM',
        'SCOPE_SHEET',
    ];

    /**
     * Access-management permissions on the USERS module.
     *
     * @var list<string>
     */
    private const array USER_ACCESS_ACTIONS = ['ASSIGN_ROLES', 'ASSIGN_PERMISSIONS'];

    /**
     * Read-only modules (immutable audit logs).
     *
     * @var list<string>
     */
    private const array READ_ONLY_MODULES = ['ACTIVITY_LOGS'];

    /** @var list<string> */
    private const array READ_ONLY_ACTIONS = ['VIEW_ANY', 'VIEW', 'EXPORT'];

    /**
     * Self-service modules for signed-in users.
     *
     * @var list<string>
     */
    private const array SELF_SERVICE_MODULES = ['AUTH_SESSIONS'];

    /** @var list<string> */
    private const array SELF_SERVICE_ACTIONS = ['VIEW_ANY', 'DELETE', 'BULK_DELETE'];

    /**
     * Roles receiving self-service session management.
     *
     * @var list<string>
     */
    private const array SELF_SERVICE_ROLES = ['ADMIN', 'MANAGER', 'USER', 'MODERATOR'];

    /**
     * Backups panel actions.
     *
     * @var list<string>
     */
    private const array BACKUP_ACTIONS = ['VIEW_ANY', 'VIEW', 'DOWNLOAD', 'CREATE', 'DELETE', 'BULK_DELETE', 'EXPORT'];

    /**
     * System monitoring tools (Horizon, Telescope).
     *
     * @var list<string>
     */
    private const array SYSTEM_MONITORING_MODULES = ['HORIZON', 'TELESCOPE'];

    /** @var list<string> */
    private const array SYSTEM_MONITORING_ACTIONS = ['VIEW'];

    /**
     * Seed foundation roles and permissions (web guard).
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->ensureRoles();
        $this->ensurePermissions($this->permissionNames());
        $this->grantAllToSuperAdmin();
        $this->grantModulesToAdmin();
        $this->grantSelfServiceModules();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function ensureRoles(): void
    {
        foreach (self::ROLES as $role) {
            Role::firstOrCreate(
                ['name' => $role, 'guard_name' => self::GUARD],
            );
        }
    }

    /**
     * All permission names to seed, de-duplicated.
     *
     * @return list<string>
     */
    private function permissionNames(): array
    {
        $names = [
            ...$this->matrix(self::MODULES, self::MODULES_ACTIONS),
            ...$this->matrix(self::SINGLETON_MODULES, self::SINGLETON_ACTIONS),
            ...$this->matrix(['USER'], self::USER_ACCESS_ACTIONS),
            ...$this->matrix(self::READ_ONLY_MODULES, self::READ_ONLY_ACTIONS),
            ...$this->matrix(['BACKUPS'], self::BACKUP_ACTIONS),
            ...$this->matrix(self::SYSTEM_MONITORING_MODULES, self::SYSTEM_MONITORING_ACTIONS),
            ...$this->matrix(self::SELF_SERVICE_MODULES, self::SELF_SERVICE_ACTIONS),
        ];

        return array_values(array_unique($names));
    }

    /**
     * Cartesian product of modules × actions → "{ACTION}_{MODULE}".
     *
     * @param list<string> $modules
     * @param list<string> $actions
     * @return list<string>
     */
    private function matrix(array $modules, array $actions): array
    {
        $names = [];

        foreach ($modules as $module) {
            foreach ($actions as $action) {
                $names[] = "{$action}_{$module}";
            }
        }

        return $names;
    }

    /**
     * @param list<string> $names
     */
    private function ensurePermissions(array $names): void
    {
        foreach ($names as $name) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => self::GUARD],
            );
        }
    }

    /**
     * SUPER_ADMIN holds every active permission.
     */
    private function grantAllToSuperAdmin(): void
    {
        $role = Role::query()
            ->with('permissions')
            ->where('name', 'SUPER_ADMIN')
            ->where('guard_name', self::GUARD)
            ->first();

        $role?->syncPermissions(
            Permission::query()->where('guard_name', self::GUARD)->get(),
        );
    }

    /**
     * ADMIN manages day-to-day operations and CRM modules.
     */
    private function grantModulesToAdmin(): void
    {
        $names = [
            ...$this->matrix(self::ADMIN_MODULES, self::MODULES_ACTIONS),
            ...$this->matrix(['USER'], ['VIEW_ANY', 'VIEW']),
        ];

        Role::query()
            ->with('permissions')
            ->where('name', 'ADMIN')
            ->where('guard_name', self::GUARD)
            ->first()
            ?->givePermissionTo($names);
    }

    /**
     * Self-service session management for users.
     */
    private function grantSelfServiceModules(): void
    {
        $names = $this->matrix(self::SELF_SERVICE_MODULES, self::SELF_SERVICE_ACTIONS);

        Role::query()
            ->with('permissions')
            ->whereIn('name', self::SELF_SERVICE_ROLES)
            ->where('guard_name', self::GUARD)
            ->get()
            ->each(static fn (Role $role) => $role->givePermissionTo($names));
    }
}
