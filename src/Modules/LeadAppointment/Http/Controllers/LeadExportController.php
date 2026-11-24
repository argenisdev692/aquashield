<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\LeadAppointment\Application\Ports\LeadRepository;
use Modules\LeadAppointment\Exports\LeadExportTransformer;
use Modules\LeadAppointment\Http\Support\LeadFilterFactory;
use Shared\Domain\Ports\AuditPort;
use Shared\Domain\Ports\ExportPort;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class LeadExportController
{
    public function __construct(
        private ExportPort $exporter,
        private LeadRepository $repository,
        private AuditPort $audit,
    ) {}

    public function __invoke(Request $request, string $format): StreamedResponse|Response
    {
        $filters = LeadFilterFactory::fromRequest($request);
        $rowCount = $this->repository->count($filters);

        if ($rowCount > LeadExportTransformer::MAX_ROWS) {
            abort(422, 'The export is limited to '.LeadExportTransformer::MAX_ROWS.' rows. Narrow the filters.');
        }

        // Non-PII filter summary only; never the search term itself.
        $this->audit->log('lead.exported', null, [
            'format' => $format,
            'rows' => $rowCount,
            'filters' => [
                'status' => $filters->pipelineStatus,
                'record_state' => $filters->recordState,
                'source' => $filters->source,
                'region' => $filters->region,
                'emergency' => $filters->emergency,
                'spam' => $filters->spam,
                'date_from' => $filters->dateFrom,
                'date_to' => $filters->dateTo,
                'has_search' => $filters->search !== null && $filters->search !== '',
            ],
        ], $request->user(), 'leads');

        $transformer = new LeadExportTransformer($filters, $this->repository);
        $filename = 'leads.'.$format;

        if ($format === 'pdf') {
            return $this->exporter->pdf($filename, 'exports.lead-appointment.leads', [
                'title' => 'Leads export',
                'columns' => $transformer->columns(),
                'rows' => $transformer->rows(),
            ]);
        }

        return $this->exporter->tabular($filename, $transformer->headers(), $transformer->rows());
    }
}
