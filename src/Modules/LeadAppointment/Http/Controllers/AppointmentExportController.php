<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\LeadAppointment\Application\Exports\AppointmentExportTransformer;
use Modules\LeadAppointment\Application\Ports\AppointmentReader;
use Modules\LeadAppointment\Http\Support\AppointmentFilterFactory;
use Shared\Domain\Ports\AuditPort;
use Shared\Domain\Ports\ExportPort;
use Shared\Support\ExportLimit;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class AppointmentExportController
{
    public function __construct(
        private ExportPort $exporter,
        private AppointmentReader $repository,
        private AuditPort $audit,
    ) {}

    public function __invoke(Request $request, string $format): StreamedResponse|Response
    {
        $filters = AppointmentFilterFactory::fromRequest($request);
        $rowCount = $this->repository->count($filters);
        ExportLimit::ensureWithin($rowCount, $format);

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
