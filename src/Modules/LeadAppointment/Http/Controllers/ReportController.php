<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Modules\LeadAppointment\Application\Dtos\LeadSourceReportData;
use Modules\LeadAppointment\Application\Dtos\LeadSourceReportFilterData;
use Modules\LeadAppointment\Application\Exports\ReportExportTransformer;
use Modules\LeadAppointment\Application\Ports\LeadReader;
use Shared\Domain\Ports\AuditPort;
use Shared\Domain\Ports\ExportPort;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class ReportController
{
    public function __construct(
        private LeadReader $leads,
        private ExportPort $exporter,
        private AuditPort $audit,
    ) {}

    public function index(Request $request): LeadSourceReportData
    {
        [$from, $to] = $this->range($request);

        return $this->leads->sourceReport($from, $to);
    }

    public function export(Request $request, string $format): StreamedResponse|Response
    {
        [$from, $to] = $this->range($request);
        $report = $this->leads->sourceReport($from, $to);

        $this->audit->log('lead_source_report.exported', null, [
            'format' => $format,
            'rows' => count($report->rows),
            'from' => $from,
            'to' => $to,
        ], $request->user(), 'leads');

        $transformer = new ReportExportTransformer($report);
        $filename = 'lead-sources.'.$format;

        if ($format === 'pdf') {
            return $this->exporter->pdf($filename, 'exports.lead-appointment.report', [
                'title' => 'Lead sources report',
                'columns' => $transformer->columns(),
                'rows' => $transformer->rows(),
            ]);
        }

        return $this->exporter->tabular($filename, $transformer->headers(), $transformer->rows());
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function range(Request $request): array
    {
        $filters = LeadSourceReportFilterData::validateAndCreate($request->query());

        $from = $filters->from ?? CarbonImmutable::now()->subDays(30)->toDateString();
        $to = $filters->to ?? CarbonImmutable::now()->toDateString();
        $maxDays = (int) config('lead-appointment.report_max_days', 366);

        if (CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to)) > $maxDays) {
            throw ValidationException::withMessages([
                'to' => "The report window is limited to {$maxDays} days.",
            ]);
        }

        return [$from, $to];
    }
}
