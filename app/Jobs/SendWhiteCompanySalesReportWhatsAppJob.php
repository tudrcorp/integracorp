<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Http\Controllers\NotificationController;
use App\Models\WhiteCompany;
use App\Services\WhiteCompanySalesReportService;
use App\Support\SecurityAudit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Envía por WhatsApp el estado de cuenta de una empresa aliada.
 *
 * Reutiliza el hub de UltraMsg del sistema, que publica el PDF desde
 * `public/storage` y lo adjunta como documento.
 */
class SendWhiteCompanySalesReportWhatsAppJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(
        public int $whiteCompanyId,
        public string $from,
        public string $to,
        public string $phone,
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
            Log::warning('SendWhiteCompanySalesReportWhatsAppJob: la empresa aliada ya no existe', [
                'white_company_id' => $this->whiteCompanyId,
            ]);

            return;
        }

        $report = WhiteCompanySalesReportService::build($company, $this->from, $this->to);

        if ($report['rows'] === []) {
            Log::info('SendWhiteCompanySalesReportWhatsAppJob: sin ventas en el rango, no se envía', [
                'white_company_id' => $this->whiteCompanyId,
                'from' => $this->from,
                'to' => $this->to,
            ]);

            return;
        }

        $path = WhiteCompanySalesReportService::savePdf($report);
        $filename = basename($path);

        $sent = NotificationController::sendWhatsAppDocument(
            $this->phone,
            WhiteCompanySalesReportService::whatsAppCaption($report),
            'reportes-aliadas/'.$filename,
            $filename,
        );

        SecurityAudit::log(
            $sent
                ? 'AUDIT_WHITE_COMPANY_SALES_REPORT_WHATSAPP_SENT'
                : 'AUDIT_WHITE_COMPANY_SALES_REPORT_WHATSAPP_FAILED',
            'administration.white-companies.sales-report.whatsapp',
            [
                'white_company_id' => $company->getKey(),
                'white_company_name' => $company->name,
                'from' => $report['from'],
                'to' => $report['to'],
                'phone' => $this->phone,
                'security_key' => $report['security_key'],
            ],
        );
    }
}
