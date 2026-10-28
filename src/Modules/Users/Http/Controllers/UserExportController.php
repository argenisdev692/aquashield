<?php

declare(strict_types=1);

namespace Modules\Users\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Users\Data\UserFilterData;
use Modules\Users\Exports\UserExportTransformer;
use Modules\Users\Queries\UserListQuery;
use Shared\Domain\Ports\ExportPort;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Thin export controller. The shared ExportPort owns the writer and the PDF
 * renderer; this class only resolves the format and hands over the module's
 * row transformation.
 *
 * The route carries `permission:EXPORT_USER` and the filters are parsed by the
 * same UserFilterData as `index`, so a filtered export exports exactly the
 * filtered list — never the whole table.
 */
final readonly class UserExportController
{
    public function __construct(
        private ExportPort $exporter,
        private UserListQuery $users,
    ) {}

    public function __invoke(Request $request, string $format): StreamedResponse|Response
    {
        $filters = UserFilterData::fromRequest($request);

        if ($this->users->count($filters) > UserExportTransformer::MAX_ROWS) {
            abort(422, 'The export is limited to '.UserExportTransformer::MAX_ROWS.' rows. Narrow the filters.');
        }

        $transformer = new UserExportTransformer($filters, $this->users);
        $filename = 'users.'.$format;

        if ($format === 'pdf') {
            return $this->exporter->pdf($filename, 'exports.users', [
                'title' => 'Users export',
                'columns' => $transformer->columns(),
                'rows' => $transformer->rows(),
            ]);
        }

        return $this->exporter->tabular($filename, $transformer->headers(), $transformer->rows());
    }
}
