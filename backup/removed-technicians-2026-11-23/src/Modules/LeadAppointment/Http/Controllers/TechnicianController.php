<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\LeadAppointment\Application\Dtos\SaveTechnicianData;
use Modules\LeadAppointment\Application\Ports\CatalogRepository;
use Modules\LeadAppointment\Domain\Enums\Region;
use Modules\LeadAppointment\Http\Requests\StoreTechnicianRequest;
use Modules\LeadAppointment\Http\Requests\UpdateTechnicianRequest;
use Shared\Application\DTOs\BulkUuidsData;

final readonly class TechnicianController
{
    public function __construct(private CatalogRepository $catalog) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'region' => ['nullable', Rule::enum(Region::class)],
            'active_only' => ['nullable', 'boolean'],
        ]);

        return response()->json([
            'data' => $this->catalog->technicians(
                $validated['region'] ?? null,
                (bool) ($validated['active_only'] ?? true),
            ),
        ]);
    }

    public function store(StoreTechnicianRequest $request): JsonResponse
    {
        return response()->json(
            $this->catalog->saveTechnician(SaveTechnicianData::from($request->validated())),
            201,
        );
    }

    public function show(string $uuid): JsonResponse
    {
        $technician = $this->catalog->findTechnician($uuid);

        if ($technician === null) {
            abort(404);
        }

        return response()->json($technician);
    }

    public function update(UpdateTechnicianRequest $request, string $uuid): JsonResponse
    {
        return response()->json(
            $this->catalog->saveTechnician(SaveTechnicianData::from($request->validated()), $uuid),
        );
    }

    public function destroy(string $uuid): JsonResponse
    {
        $this->catalog->deleteTechnician($uuid);

        return response()->json(null, 204);
    }

    public function restore(string $uuid): JsonResponse
    {
        return response()->json($this->catalog->restoreTechnician($uuid));
    }

    public function bulkDelete(Request $request): JsonResponse
    {
        $data = BulkUuidsData::validateAndCreate($request->all());
        $this->catalog->bulkDeleteTechnicians($data->uuids);

        return response()->json(null, 204);
    }

    public function bulkRestore(Request $request): JsonResponse
    {
        $data = BulkUuidsData::validateAndCreate($request->all());
        $this->catalog->bulkRestoreTechnicians($data->uuids);

        return response()->json(null, 204);
    }
}
