<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Application\Ports;

use Modules\LeadAppointment\Application\Dtos\SaveTechnicianData;
use Modules\LeadAppointment\Application\Dtos\TechnicianData;

interface CatalogRepository
{
    /**
     * @return list<TechnicianData>
     */
    public function technicians(?string $region, bool $activeOnly = true): array;

    public function findTechnician(string $uuid): ?TechnicianData;

    public function saveTechnician(SaveTechnicianData $data, ?string $uuid = null): TechnicianData;

    public function deleteTechnician(string $uuid): void;

    public function restoreTechnician(string $uuid): TechnicianData;

    /**
     * @param list<string> $uuids
     */
    public function bulkDeleteTechnicians(array $uuids): void;

    /**
     * @param list<string> $uuids
     */
    public function bulkRestoreTechnicians(array $uuids): void;

    public function technicianIdByUuid(string $uuid): ?int;

    public function technicianUuidForUser(int $userId): ?string;
}
