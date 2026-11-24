<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\LeadAppointment\Application\Ports\AppointmentRepository;
use Modules\LeadAppointment\Exports\AppointmentExportTransformer;
use Modules\LeadAppointment\Http\Support\AppointmentFilterFactory;
use Shared\Domain\Ports\AuditPort;
use Shared\Domain\Ports\ExportPort;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class AppointmentExportController
{
    public function __construct(
        private ExportPort $exporter,
        private AppointmentRepository $repository,
        private AuditPort $audit,
    ) {}

    public function __invoke(Request $request, string $format): StreamedResponse|Response
    {
        $filters = AppointmentFilterFactory::fromRequest($request);
        $rowCount = $this->repository->count($filters);

        if ($rowCount > AppointmentExportTransformer::MAX_ROWS) {
            abort(422, 'The export is limited to '.AppointmentExportTransformer::MAX_ROWS.' rows. Narrow the filters.');
        }

        $this->audit->log('appointment.exported', null, [
            'format' => $format,
            'rows' => $rowCount,
            'filters' => [
                'status' => $filters->appointmentStatus,
                'record_state' => $filters->recordState,
                'kind' => $filters->kind,
                'region' => $filters->region,
                'date_from' => $filters->dateFrom,
                'date_to' => $filters->dateTo,
                'has_search' => $filters->search !== null && $filters->search !== '',
            ],
        ], $request->user(), 'appointments');

        $transformer = new AppointmentExportTransformer($filters, $this->repository);
        $filename = 'appointments.'.$format;

        if ($format === 'pdf') {
            return $this->exporter->pdf($filename, 'exports.lead-appointment.appointments', [
                'title' => 'Appointments export',
                'columns' => $transformer->columns(),
                'rows' => $transformer->rows(),
            ]);
        }

        return $this->exporter->tabular($filename, $transformer->headers(), $transformer->rows());
    }
}
