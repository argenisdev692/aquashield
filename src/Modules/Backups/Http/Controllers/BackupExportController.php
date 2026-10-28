<?php

declare(strict_types=1);

namespace Modules\Backups\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Backups\Application\Exports\BackupExportTransformer;
use Modules\Backups\Application\Queries\BackupIndexQuery;
use Modules\Backups\Data\BackupFilterData;
use Shared\Domain\Ports\ExportPort;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class BackupExportController
{
    public function __construct(
        private ExportPort $exporter,
        private BackupIndexQuery $backups,
    ) {}

    public function __invoke(Request $request, string $format): StreamedResponse|Response
    {
        $filters = BackupFilterData::fromRequest($request);

        if ($this->backups->count($filters) > BackupExportTransformer::MAX_ROWS) {
            abort(422, 'The export is limited to '.BackupExportTransformer::MAX_ROWS.' rows. Narrow the filters.');
        }

        $transformer = new BackupExportTransformer($filters, $this->backups);
        $filename = 'backups.'.$format;

        if ($format === 'pdf') {
            return $this->exporter->pdf($filename, 'exports.backups', [
                'title' => 'Database backups',
                'columns' => $transformer->columns(),
                'rows' => $transformer->rows(),
            ]);
        }

        return $this->exporter->tabular($filename, $transformer->headers(), $transformer->rows());
    }
}
