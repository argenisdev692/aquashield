<?php

declare(strict_types=1);

namespace Modules\Users\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Users\Data\UserFilterData;
use Modules\Users\Exports\UserExportTransformer;
use Modules\Users\Queries\UserListQuery;
use Shared\Domain\Ports\AuditPort;
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
        private AuditPort $audit,
    ) {}

    public function __invoke(Request $request, string $format): StreamedResponse|Response
    {
        $filters = UserFilterData::fromRequest($request);
        $rowCount = $this->users->count($filters);

        if ($rowCount > UserExportTransformer::MAX_ROWS) {
            abort(422, 'The export is limited to '.UserExportTransformer::MAX_ROWS.' rows. Narrow the filters.');
        }

        // dompdf renders the whole table inside the request, so the PDF cap is
        // lower than the streamed formats'. Refuse before rendering rather
        // than producing a 500 halfway through the document.
        if ($format === 'pdf' && $rowCount > UserExportTransformer::MAX_PDF_ROWS) {
            abort(422, 'The PDF export is limited to '.UserExportTransformer::MAX_PDF_ROWS.' rows. Narrow the filters or use CSV/XLSX.');
        }

        // Audit-worthy file access (OWASP A09). No search term, no email: the
        // filter summary is deliberately non-PII.
        $this->audit->log('user.exported', null, [
            'format' => $format,
            'rows' => $rowCount,
            'filters' => [
                'status' => $filters->status,
                'account_status' => $filters->accountStatus,
                'role' => $filters->role,
                'permission' => $filters->permission,
                'date_from' => $filters->dateFrom,
                'date_to' => $filters->dateTo,
                'has_search' => $filters->search !== null && $filters->search !== '',
            ],
        ], $request->user(), 'users');

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
