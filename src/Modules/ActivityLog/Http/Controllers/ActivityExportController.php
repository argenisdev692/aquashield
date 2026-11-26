<?php

declare(strict_types=1);

namespace Modules\ActivityLog\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\ActivityLog\Data\ActivityFilterData;
use Modules\ActivityLog\Exports\ActivityExportTransformer;
use Modules\ActivityLog\Queries\ActivityListQuery;
use Shared\Domain\Ports\AuditPort;
use Shared\Domain\Ports\ExportPort;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class ActivityExportController
{
    public function __construct(
        private ExportPort $exporter,
        private ActivityListQuery $activity,
        private AuditPort $audit,
    ) {}

    public function __invoke(Request $request, string $format): StreamedResponse|Response
    {
        $filters = ActivityFilterData::fromRequest($request);
        $rowCount = $this->activity->count($filters);
        $maxRows = $format === 'pdf'
            ? ActivityExportTransformer::MAX_PDF_ROWS
            : ActivityExportTransformer::MAX_ROWS;

        if ($rowCount > $maxRows) {
            abort(422, 'The '.$format.' export is limited to '.$maxRows.' rows. Narrow the filters.');
        }

        $transformer = new ActivityExportTransformer($filters, $this->activity);
        $filename = 'activity-log.'.$format;

        // Build the response before auditing it: a renderer that cannot start
        // must not leave an "exported" entry behind. The PDF renderer runs
        // synchronously here; the tabular adapter defers its writer to the
        // stream callback. The audit row therefore records the authorized
        // export attempt, not the bytes the client actually received.
        $response = $format === 'pdf'
            ? $this->exporter->pdf($filename, 'exports.activity-log', [
                'title' => 'Activity log',
                'columns' => $transformer->columns(),
                'rows' => $transformer->rows($maxRows),
            ])
            : $this->exporter->tabular($filename, $transformer->headers(), $transformer->rows($maxRows));

        // Audit-worthy egress (OWASP A09). The date window is operational; the
        // free-text filters are reduced to booleans so a typed email or name is
        // never copied into the audit trail.
        $this->audit->log('activity.exported', null, [
            'format' => $format,
            'rows' => $rowCount,
            'filters' => [
                'date_from' => $filters->dateFrom,
                'date_to' => $filters->dateTo,
                'timezone' => $filters->timezone,
                'has_log_name_filter' => $filters->logName !== null && $filters->logName !== '',
                'has_event_filter' => $filters->event !== null && $filters->event !== '',
                'has_search' => $filters->search !== null && $filters->search !== '',
            ],
        ], $request->user(), 'activity');

        return $response;
    }
}
