<?php

declare(strict_types=1);

namespace Modules\AccessControl\Http\Controllers;

use Generator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\AccessControl\Data\RoleFilterData;
use Modules\AccessControl\Exports\RoleExportTransformer;
use Modules\AccessControl\Queries\RoleListQuery;
use Shared\Domain\Ports\AuditPort;
use Shared\Domain\Ports\ExportPort;
use Shared\Support\ExportLimit;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class RoleExportController
{
    public function __construct(
        private ExportPort $exporter,
        private RoleListQuery $roles,
        private AuditPort $audit,
    ) {}

    public function __invoke(Request $request, string $format): StreamedResponse|Response
    {
        $filters = RoleFilterData::fromRequest($request);
        $rowCount = $this->roles->count($filters);
        $limit = ExportLimit::ensureWithin($rowCount, $format);

        $transformer = new RoleExportTransformer($filters, $this->roles, $limit);
        $rows = $this->withCompletionAudit(
            $transformer->rows(),
            'role.exported',
            [
                'format' => $format,
                'rows' => $rowCount,
                'limit' => $limit,
                'filters' => [
                    'type' => $filters->type,
                    'permission' => $filters->permission,
                    'date_from' => $filters->dateFrom,
                    'date_to' => $filters->dateTo,
                    'has_search' => $filters->search !== null && $filters->search !== '',
                ],
            ],
            $request->user(),
        );

        $filename = 'roles.'.$format;

        if ($format === 'pdf') {
            return $this->exporter->pdf($filename, 'exports.roles', [
                'title' => 'Roles export',
                'columns' => $transformer->columns(),
                'rows' => $rows,
            ]);
        }

        return $this->exporter->tabular($filename, $transformer->headers(), $rows);
    }

    /**
     * Audit after the rows have actually been produced, not before the stream
     * starts. A writer failure or a client abort therefore does not leave a
     * "role.exported" row that claims a file nobody received.
     *
     * @param iterable<int, array<string, string|int|float>> $rows
     * @param array<string, mixed> $properties
     * @return Generator<int, array<string, string|int|float>>
     */
    private function withCompletionAudit(
        iterable $rows,
        string $event,
        array $properties,
        ?object $actor,
    ): Generator {
        foreach ($rows as $row) {
            yield $row;
        }

        $this->audit->log($event, null, $properties, $actor, 'access-control');
    }
}
