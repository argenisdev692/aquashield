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
use Spatie\Permission\PermissionRegistrar;

final class LeadAppointmentFixtures
{
    /**
     * @var list<string>
     */
    public const array PERMISSIONS = [
        'VIEW_ANY_LEAD', 'VIEW_LEAD', 'CREATE_LEAD', 'UPDATE_LEAD', 'DELETE_LEAD',
        'RESTORE_LEAD', 'BULK_DELETE_LEAD', 'BULK_RESTORE_LEAD', 'EXPORT_LEAD',
        'VIEW_ANY_APPOINTMENT', 'VIEW_APPOINTMENT', 'CREATE_APPOINTMENT', 'UPDATE_APPOINTMENT',
        'DELETE_APPOINTMENT', 'RESTORE_APPOINTMENT', 'BULK_DELETE_APPOINTMENT',
        'BULK_RESTORE_APPOINTMENT', 'EXPORT_APPOINTMENT',
        'VIEW_ANY_AVAILABILITY_RULE', 'CREATE_AVAILABILITY_RULE', 'UPDATE_AVAILABILITY_RULE',
        'DELETE_AVAILABILITY_RULE', 'RESTORE_AVAILABILITY_RULE', 'BULK_DELETE_AVAILABILITY_RULE',
        'BULK_RESTORE_AVAILABILITY_RULE',
        'VIEW_ANY_AVAILABILITY_EXCEPTION', 'CREATE_AVAILABILITY_EXCEPTION',
        'UPDATE_AVAILABILITY_EXCEPTION', 'DELETE_AVAILABILITY_EXCEPTION',
        'RESTORE_AVAILABILITY_EXCEPTION', 'BULK_DELETE_AVAILABILITY_EXCEPTION',
        'BULK_RESTORE_AVAILABILITY_EXCEPTION',
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
