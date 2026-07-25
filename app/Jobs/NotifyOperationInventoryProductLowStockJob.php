<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\SystemNotificationKey;
use App\Mail\OperationInventoryLowStockMail;
use App\Services\HelpdeskTicketAssigneeWhatsAppService;
use App\Services\OperationInventoryLowStockReporter;
use App\Support\SystemNotificationRecipients;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class NotifyOperationInventoryProductLowStockJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function __construct(
        public int $productId,
    ) {}

    public function handle(OperationInventoryLowStockReporter $reporter): void
    {
        if (! SystemNotificationRecipients::isActive(SystemNotificationKey::OperationInventoryLowStock)) {
            return;
        }

        $report = $reporter->reportForProduct($this->productId);

        if ($report === null || $report['products'] === []) {
            return;
        }

        $emails = SystemNotificationRecipients::emails(SystemNotificationKey::OperationInventoryLowStock);
        $phones = SystemNotificationRecipients::phones(SystemNotificationKey::OperationInventoryLowStock);

        if ($emails === [] && $phones === []) {
            Log::warning('NotifyOperationInventoryProductLowStockJob: sin destinatarios', [
                'product_id' => $this->productId,
            ]);

            return;
        }

        $this->dispatchWhatsApp($reporter->whatsappBodyImmediate($report), $phones);
        $this->sendEmails($report, $emails);
    }

    /**
     * @param  list<string>  $phones
     */
    private function dispatchWhatsApp(string $body, array $phones): void
    {
        foreach ($phones as $rawPhone) {
            $phone = HelpdeskTicketAssigneeWhatsAppService::normalizePhoneForWhatsApp($rawPhone);

            if ($phone === null) {
                continue;
            }

            try {
                SendNotificacionWhatsApp::dispatch(null, $body, $phone, null, [
                    'panel' => 'operations',
                    'source' => 'inventory.low-stock-immediate',
                    'product_id' => $this->productId,
                ]);
            } catch (Throwable $exception) {
                Log::error('NotifyOperationInventoryProductLowStockJob: error WhatsApp', [
                    'product_id' => $this->productId,
                    'phone' => $phone,
                    'message' => $exception->getMessage(),
                ]);
            }
        }
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
        foreach ($emails as $email) {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            try {
                Mail::to($email)->send(new OperationInventoryLowStockMail($report, $email, immediate: true));
            } catch (Throwable $exception) {
                Log::error('NotifyOperationInventoryProductLowStockJob: error email', [
                    'product_id' => $this->productId,
                    'email' => $email,
                    'message' => $exception->getMessage(),
                ]);
            }
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('NotifyOperationInventoryProductLowStockJob: FAILED', [
            'product_id' => $this->productId,
            'message' => $exception?->getMessage(),
        ]);
    }
}
