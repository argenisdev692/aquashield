<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Repositories;

use App\Models\User;
use Modules\LeadAppointment\Application\Dtos\SaveTechnicianData;
use Modules\LeadAppointment\Application\Dtos\TechnicianData;
use Modules\LeadAppointment\Application\Ports\CatalogRepository;
use Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Models\Technician;

final readonly class EloquentCatalogRepository implements CatalogRepository
{
    /**
     * @return list<TechnicianData>
     */
    public function technicians(?string $region, bool $activeOnly = true): array
    {
        return Technician::query()
            ->when($region !== null, fn ($query) => $query->where('region', $region))
            ->when($activeOnly, fn ($query) => $query->where('is_active', true))
            ->with(['user:id,uuid'])
            ->orderBy('name')
            ->get()
            ->map(fn (Technician $technician): TechnicianData => $this->toTechnicianData($technician))
            ->values()
            ->all();
    }

    public function findTechnician(string $uuid): ?TechnicianData
    {
        $technician = Technician::query()
            ->with(['user:id,uuid'])
            ->where('uuid', $uuid)
            ->first();

        return $technician === null ? null : $this->toTechnicianData($technician);
    }

    public function saveTechnician(SaveTechnicianData $data, ?string $uuid = null): TechnicianData
    {
        $technician = $uuid === null
            ? new Technician
            : Technician::query()->where('uuid', $uuid)->firstOrFail();

        $userId = $data->userUuid === null
            ? null
            : User::query()->where('uuid', $data->userUuid)->value('id');

        $technician->fill([
            'user_id' => $userId,
            'name' => $data->name,
            'region' => $data->region->value,
            'phone' => $data->phone,
            'email' => $data->email,
            'is_active' => $data->isActive,
            'daily_capacity' => $data->dailyCapacity,
            'travel_buffer_minutes' => $data->travelBufferMinutes,
        ]);
        $technician->save();

        return $this->findTechnician($technician->uuid)
            ?? throw new \RuntimeException('Technician was not persisted.');
    }

    public function deleteTechnician(string $uuid): void
    {
        Technician::query()->where('uuid', $uuid)->delete();
    }

    public function restoreTechnician(string $uuid): TechnicianData
    {
        $technician = Technician::onlyTrashed()->where('uuid', $uuid)->firstOrFail();
        $technician->restore();

        return $this->findTechnician($technician->uuid)
            ?? throw new \RuntimeException('Technician was not restored.');
    }

    /**
     * @param list<string> $uuids
     */
    public function bulkDeleteTechnicians(array $uuids): void
    {
        Technician::query()->whereIn('uuid', $uuids)->delete();
    }

    /**
     * @param list<string> $uuids
     */
    public function bulkRestoreTechnicians(array $uuids): void
    {
        Technician::onlyTrashed()->whereIn('uuid', $uuids)->restore();
    }

    public function technicianIdByUuid(string $uuid): ?int
    {
        $id = Technician::query()->where('uuid', $uuid)->value('id');

        return $id === null ? null : (int) $id;
    }

    public function technicianUuidForUser(int $userId): ?string
    {
        $uuid = Technician::query()->where('user_id', $userId)->value('uuid');

        return $uuid === null ? null : (string) $uuid;
    }

    private function toTechnicianData(Technician $technician): TechnicianData
    {
        return new TechnicianData(
            uuid: $technician->uuid,
            userUuid: $technician->user?->uuid,
            name: $technician->name,
            region: $technician->region->value,
            phone: $technician->phone,
            email: $technician->email,
            isActive: $technician->is_active,
            dailyCapacity: $technician->daily_capacity,
            travelBufferMinutes: $technician->travel_buffer_minutes,
        );
    }
}
