<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\SystemNotificationKey;
use App\Mail\OperationInventoryLowStockMail;
use App\Services\HelpdeskTicketAssigneeWhatsAppService;
use App\Services\OperationInventoryLowStockReporter;
use App\Support\Concerns\ReportsScheduledExecution;
use App\Support\ScheduledTaskRunReport;
use App\Support\SystemNotificationRecipients;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendOperationInventoryLowStockAlert implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, ReportsScheduledExecution, SerializesModels;

    public function __construct() {}

    public function handle(OperationInventoryLowStockReporter $reporter): void
    {
        $this->runWithScheduledReport(
            'Alerta diaria de stock bajo de inventario',
            function () use ($reporter): void {
                $this->dispatchLowStockAlert($reporter);
            },
            'Revisa productos activos del inventario Diagnomóvil con existencia total menor o igual al umbral y envía la lista por WhatsApp y correo.',
            [
                '*Existencia total* = suma de existencias en todos los almacenes.',
                'Solo se notifican productos activos bajo el umbral configurado en Parámetros de Inventario.',
                'Los destinatarios se gestionan en el Centro de notificaciones (Stock bajo de inventario).',
            ],
        );
    }

    private function dispatchLowStockAlert(OperationInventoryLowStockReporter $reporter): void
    {
        $report = $reporter->report();
        $productCount = count($report['products']);

        ScheduledTaskRunReport::addMetric('Umbral configurado', $report['threshold']);
        ScheduledTaskRunReport::addMetric('Productos bajo umbral', $productCount);

        if ($productCount === 0) {
            ScheduledTaskRunReport::addMetric('WhatsApp despachados', 0);
            ScheduledTaskRunReport::addMetric('Email alerta enviado', 'Sin productos bajo umbral');

            return;
        }

        $emails = SystemNotificationRecipients::emails(SystemNotificationKey::OperationInventoryLowStock);
        $phones = SystemNotificationRecipients::phones(SystemNotificationKey::OperationInventoryLowStock);

        if ($emails === [] && $phones === []) {
            ScheduledTaskRunReport::addMetric('WhatsApp despachados', 0);
            ScheduledTaskRunReport::addMetric('Email alerta enviado', 'Sin destinatarios');
            ScheduledTaskRunReport::recordFailure('No hay destinatarios configurados en el Centro de notificaciones (Stock bajo de inventario)');

            return;
        }

        $this->dispatchWhatsApp($reporter->whatsappBody($report), $phones);
        $this->sendEmails($report, $emails);
    }

    /**
     * @param  list<string>  $phones
     */
    private function dispatchWhatsApp(string $body, array $phones): void
    {
        $dispatched = 0;

        foreach ($phones as $rawPhone) {
            $phone = HelpdeskTicketAssigneeWhatsAppService::normalizePhoneForWhatsApp($rawPhone);

            if ($phone === null) {
                ScheduledTaskRunReport::recordFailure('Teléfono inválido para WhatsApp: '.$rawPhone);

                continue;
            }

            try {
                SendNotificacionWhatsApp::dispatch(null, $body, $phone, null, [
                    'panel' => 'operations',
                    'source' => 'inventory.low-stock-alert',
                ]);

                $dispatched++;
            } catch (Throwable $exception) {
                ScheduledTaskRunReport::recordFailure('Error al despachar WhatsApp a '.$phone);
                Log::error('SendOperationInventoryLowStockAlert: error despachando WhatsApp', [
                    'phone' => $phone,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        ScheduledTaskRunReport::addMetric('WhatsApp despachados', $dispatched);
    }

    /**
     * @param  array{
     *     threshold: int,
     *     generated_at: string,
     *     products: list<array{
     *         id: int,
     *         code: string,
     *         name: string,
     *         category: string|null,
     *         unit: string|null,
     *         total_existence: int,
     *         warehouses: list<array{name: string, existence: int}>
     *     }>
     * }  $report
     * @param  list<string>  $emails
     */
    private function sendEmails(array $report, array $emails): void
    {
        $emailsSent = 0;

        foreach ($emails as $email) {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                ScheduledTaskRunReport::recordFailure('Correo inválido para alerta de stock bajo: '.$email);

                continue;
            }

            try {
                Mail::to($email)->send(new OperationInventoryLowStockMail($report, $email));
                $emailsSent++;
            } catch (Throwable $exception) {
                ScheduledTaskRunReport::recordFailure('Error al enviar email de alerta a '.$email);
                Log::error('SendOperationInventoryLowStockAlert: error enviando email', [
                    'email' => $email,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        ScheduledTaskRunReport::addMetric(
            'Email alerta enviado',
            $emailsSent > 0 ? 'Sí ('.$emailsSent.')' : ($emails === [] ? 'No aplica' : 'No'),
        );
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('SendOperationInventoryLowStockAlert: FAILED', [
            'message' => $exception?->getMessage(),
            'trace' => $exception?->getTraceAsString(),
        ]);
    }
}
