<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SendNotificacionWhatsApp;
use App\Services\HelpdeskTicketAssigneeWhatsAppService;
use App\Support\Audit\AuditCompletionReport;
use Illuminate\Console\Command;

class SendAuditSummaryWhatsAppTest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'audit:test-whatsapp
        {phone : Teléfono destino, ej. 04127018390}
        {--queue : Encola el envío en lugar de enviarlo de inmediato}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envía el reporte de auditorías completas por WhatsApp a un solo teléfono (para pruebas).';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $rawPhone = (string) $this->argument('phone');
        $phone = HelpdeskTicketAssigneeWhatsAppService::normalizePhoneForWhatsApp($rawPhone);

        if ($phone === null) {
            $this->error("Teléfono inválido: {$rawPhone}");

            return self::FAILURE;
        }

        $counts = AuditCompletionReport::counts();
        $body = AuditCompletionReport::whatsappBody($counts);

        $this->info('Resumen de auditorías:');
        $this->table(
            ['Categoría', 'Total', 'Auditados', 'Pendientes'],
            [
                [$counts['agencies']['label'], $counts['agencies']['total'], $counts['agencies']['audited'], $counts['agencies']['pending']],
                [$counts['agents']['label'], $counts['agents']['total'], $counts['agents']['audited'], $counts['agents']['pending']],
                [$counts['individual_affiliations']['label'], $counts['individual_affiliations']['total'], $counts['individual_affiliations']['audited'], $counts['individual_affiliations']['pending']],
                [$counts['corporate_affiliations']['label'], $counts['corporate_affiliations']['total'], $counts['corporate_affiliations']['audited'], $counts['corporate_affiliations']['pending']],
                ['TOTAL GENERAL', $counts['totals']['total'], $counts['totals']['audited'], $counts['totals']['pending']],
            ],
        );

        $context = ['panel' => 'system', 'source' => 'audit.daily-summary.test'];

        if ($this->option('queue')) {
            SendNotificacionWhatsApp::dispatch(null, $body, $phone, null, $context);
            $this->info("WhatsApp encolado para {$phone}. Asegúrate de tener el worker de la cola activo.");

            return self::SUCCESS;
        }

        SendNotificacionWhatsApp::dispatchSync(null, $body, $phone, null, $context);
        $this->info("WhatsApp enviado a {$phone}.");

        return self::SUCCESS;
    }
}
