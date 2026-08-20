<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\WhiteCompanySalesReportMail;
use App\Models\WhiteCompany;
use App\Services\WhiteCompanySalesReportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Genera y envía el reporte de ventas de una empresa aliada.
 *
 * El PDF se arma dentro del job para no bloquear el request: un rango amplio
 * puede abarcar cientos de afiliaciones.
 */
class SendWhiteCompanySalesReportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(
        public int $whiteCompanyId,
        public string $from,
        public string $to,
        public string $recipient,
    ) {
        $this->onQueue((string) config('affiliate-card.documents_queue', 'documents'));
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 60];
    }

    public function handle(): void
    {
        $company = WhiteCompany::query()->find($this->whiteCompanyId);

        if (! $company instanceof WhiteCompany) {
            Log::warning('SendWhiteCompanySalesReportJob: la empresa aliada ya no existe', [
                'white_company_id' => $this->whiteCompanyId,
            ]);

            return;
        }

        $report = WhiteCompanySalesReportService::build($company, $this->from, $this->to);

        if ($report['rows'] === []) {
            Log::info('SendWhiteCompanySalesReportJob: sin ventas en el rango, no se envía', [
                'white_company_id' => $this->whiteCompanyId,
                'from' => $this->from,
                'to' => $this->to,
            ]);

            return;
        }

        $path = WhiteCompanySalesReportService::savePdf($report);

        Mail::to($this->recipient)->send(new WhiteCompanySalesReportMail(
            companyName: (string) $company->name,
            fromDate: $report['from'],
            toDate: $report['to'],
            totals: $report['totals'],
            rowsCount: count($report['rows']),
            securityKey: $report['security_key'],
            attachmentPath: $path,
        ));

        WhiteCompanySalesReportService::auditIssue(
            $report,
            'administration.white-companies.sales-report.sent',
            $this->recipient,
        );
    }
}
