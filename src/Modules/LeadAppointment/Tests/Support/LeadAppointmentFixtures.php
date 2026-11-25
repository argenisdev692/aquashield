<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Tests\Support;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Modules\LeadAppointment\Domain\Enums\Region;
use Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Models\AvailabilityRule;
use Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Models\Lead;
use Shared\Domain\Enums\Permission as PermissionName;
use Spatie\Permission\PermissionRegistrar;

final class LeadAppointmentFixtures
{
    /**
     * @var list<string>
     */
    public const array PERMISSIONS = [
        PermissionName::ViewAnyLead->value,
        PermissionName::ViewLead->value,
        PermissionName::CreateLead->value,
        PermissionName::UpdateLead->value,
        PermissionName::DeleteLead->value,
        PermissionName::RestoreLead->value,
        PermissionName::BulkDeleteLead->value,
        PermissionName::BulkRestoreLead->value,
        PermissionName::ExportLead->value,
        PermissionName::ViewAnyAppointment->value,
        PermissionName::ViewAppointment->value,
        PermissionName::CreateAppointment->value,
        PermissionName::UpdateAppointment->value,
        PermissionName::DeleteAppointment->value,
        PermissionName::RestoreAppointment->value,
        PermissionName::BulkDeleteAppointment->value,
        PermissionName::BulkRestoreAppointment->value,
        PermissionName::ExportAppointment->value,
        PermissionName::ViewAnyAvailabilityRule->value,
        PermissionName::CreateAvailabilityRule->value,
        PermissionName::UpdateAvailabilityRule->value,
        PermissionName::DeleteAvailabilityRule->value,
        PermissionName::RestoreAvailabilityRule->value,
        PermissionName::BulkDeleteAvailabilityRule->value,
        PermissionName::BulkRestoreAvailabilityRule->value,
        PermissionName::ViewAnyAvailabilityException->value,
        PermissionName::CreateAvailabilityException->value,
        PermissionName::UpdateAvailabilityException->value,
        PermissionName::DeleteAvailabilityException->value,
        PermissionName::RestoreAvailabilityException->value,
        PermissionName::BulkDeleteAvailabilityException->value,
        PermissionName::BulkRestoreAvailabilityException->value,
        PermissionName::ExportAvailabilityException->value,
    ];

    public static function seedPermissions(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        Role::findOrCreate('SUPER_ADMIN', 'web')->syncPermissions(Permission::all());

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public static function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('SUPER_ADMIN');

        return $user;
    }

    public static function companyRule(
        int $dayOfWeek,
        string $start = '08:00',
        string $end = '17:00',
    ): AvailabilityRule {
        return AvailabilityRule::factory()->create([
            'day_of_week' => $dayOfWeek,
            'start_time' => $start,
            'end_time' => $end,
            'is_available' => true,
        ]);
    }

    public static function lead(Region $region = Region::Houston): Lead
    {
        return Lead::factory()->create([
            'region' => $region->value,
            'state' => $region->state(),
        ]);
    }

    public static function nextWeekdayAt(string $time = '10:00'): CarbonImmutable
    {
        return CarbonImmutable::now('America/Chicago')
            ->next(CarbonInterface::MONDAY)
            ->setTime((int) substr($time, 0, 2), (int) substr($time, 3, 2));
    }
}
