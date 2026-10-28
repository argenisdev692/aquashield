<?php

declare(strict_types=1);

namespace Modules\ActivityLog\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\ActivityLog\Data\ActivityFilterData;
use Modules\ActivityLog\Exports\ActivityExportTransformer;
use Modules\ActivityLog\Queries\ActivityListQuery;
use Shared\Domain\Ports\ExportPort;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class ActivityExportController
{
    public function __construct(
        private ExportPort $exporter,
        private ActivityListQuery $activity,
    ) {}

    public function __invoke(Request $request, string $format): StreamedResponse|Response
    {
        $filters = ActivityFilterData::fromRequest($request);

        if ($this->activity->count($filters) > ActivityExportTransformer::MAX_ROWS) {
            abort(422, 'The export is limited to '.ActivityExportTransformer::MAX_ROWS.' rows. Narrow the filters.');
        }

        $transformer = new ActivityExportTransformer($filters, $this->activity);
        $filename = 'activity-log.'.$format;

        if ($format === 'pdf') {
            return $this->exporter->pdf($filename, 'exports.activity-log', [
                'title' => 'Activity log',
                'columns' => $transformer->columns(),
                'rows' => $transformer->rows(),
            ]);
        }

        return $this->exporter->tabular($filename, $transformer->headers(), $transformer->rows());
    }
}
