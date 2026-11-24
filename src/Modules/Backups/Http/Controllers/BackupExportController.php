<?php

declare(strict_types=1);

namespace Modules\Backups\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Backups\Application\Exports\BackupExportTransformer;
use Modules\Backups\Application\Queries\BackupIndexQuery;
use Modules\Backups\Data\BackupFilterData;
use Shared\Domain\Ports\AuditPort;
use Shared\Domain\Ports\ExportPort;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class BackupExportController
{
    public function __construct(
        private ExportPort $exporter,
        private BackupIndexQuery $backups,
        private AuditPort $audit,
    ) {}

    public function __invoke(Request $request, string $format): StreamedResponse|Response
    {
        $filters = BackupFilterData::fromRequest($request);
        $rowCount = $this->backups->count($filters);

        if ($rowCount > BackupExportTransformer::MAX_ROWS) {
            abort(422, 'The export is limited to '.BackupExportTransformer::MAX_ROWS.' rows. Narrow the filters.');
        }

        $transformer = new BackupExportTransformer($filters, $this->backups);
        $filename = 'backups.'.$format;

        // The adapter opens the writer/renderer before returning, so an export
        // that cannot be produced throws before the audit entry is written.
        $response = $format === 'pdf'
            ? $this->exporter->pdf($filename, 'exports.backups', [
                'title' => 'Database backups',
                'columns' => $transformer->columns(),
                'rows' => $transformer->rows(),
            ])
            : $this->exporter->tabular($filename, $transformer->headers(), $transformer->rows());

        $this->audit->log('backup.exported', null, [
            'format' => $format,
            'rows' => $rowCount,
            'filters' => [
                'status' => $filters->status,
                'disk' => $filters->disk,
                'date_from' => $filters->dateFrom,
                'date_to' => $filters->dateTo,
                'has_search' => $filters->search !== null && $filters->search !== '',
            ],
        ], $request->user(), 'backups');

        return $response;
    }
}
