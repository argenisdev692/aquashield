<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Http\Controllers;

use Dedoc\Scramble\Attributes\Parameter;
use Dedoc\Scramble\Attributes\Response as OpenApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\LeadAppointment\Application\Exports\AvailabilityExceptionExportTransformer;
use Modules\LeadAppointment\Application\Ports\AvailabilityReader;
use Modules\LeadAppointment\Http\Support\AvailabilityExceptionFilterFactory;
use Shared\Domain\Ports\AuditPort;
use Shared\Domain\Ports\ExportPort;
use Shared\Support\ExportLimit;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * One streamed file per format, built from the list filters. The row cap is
 * checked before generation; the throttle lives on the route.
 */
final readonly class AvailabilityExceptionExportController
{
    public function __construct(
        private ExportPort $exporter,
        private AvailabilityReader $availability,
        private AuditPort $audit,
    ) {}

    #[Parameter('query', 'search', type: 'string')]
    #[Parameter('query', 'filter[availability]', type: 'string', description: 'One of: open, closed.')]
    #[Parameter('query', 'filter[source]', type: 'string', description: 'One of: manual, holiday.')]
    #[Parameter('query', 'filter[record_state]', type: 'string', description: 'One of: active, trashed, all.')]
    #[Parameter('query', 'filter[date_from]', type: 'string', format: 'date')]
    #[Parameter('query', 'filter[date_to]', type: 'string', format: 'date')]
    #[Parameter('query', 'sort', type: 'string', description: 'One of: date, created_at, with optional - prefix.')]
    #[Parameter('query', 'page', type: 'integer', default: 1)]
    #[Parameter('query', 'per_page', type: 'integer', default: 15)]
    #[OpenApiResponse(200, 'CSV export', mediaType: 'text/csv', type: 'string', format: 'binary')]
    #[OpenApiResponse(200, 'XLSX export', mediaType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', type: 'string', format: 'binary')]
    #[OpenApiResponse(200, 'PDF export', mediaType: 'application/pdf', type: 'string', format: 'binary')]
    #[OpenApiResponse(403, 'Forbidden', type: 'array{message: string}')]
    public function __invoke(Request $request, string $format): StreamedResponse|Response
    {
        $filters = AvailabilityExceptionFilterFactory::fromRequest($request);
        $rowCount = $this->availability->countExceptions($filters);
        $limit = ExportLimit::for($format);

        if ($rowCount > $limit) {
            abort(422, "The export is limited to {$limit} rows for {$format}. Narrow the filters.");
        }

        $this->audit->log('availability_exception.exported', null, [
            'format' => $format,
            'rows' => $rowCount,
            'filters' => [
                'availability' => $filters->availability,
                'source' => $filters->source,
                'record_state' => $filters->recordState,
                'date_from' => $filters->dateFrom,
                'date_to' => $filters->dateTo,
                'has_search' => $filters->search !== null && $filters->search !== '',
            ],
        ], $request->user(), 'availability');

        $transformer = new AvailabilityExceptionExportTransformer($filters, $this->availability);
        $filename = 'availability-exceptions.'.$format;

        if ($format === 'pdf') {
            return $this->exporter->pdf($filename, 'exports.lead-appointment.availability-exceptions', [
                'title' => 'Availability exceptions export',
                'columns' => $transformer->columns(),
                'rows' => $transformer->rows(),
            ]);
        }

        return $this->exporter->tabular($filename, $transformer->headers(), $transformer->rows());
    }
}
