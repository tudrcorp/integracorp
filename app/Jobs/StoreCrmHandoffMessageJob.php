<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\CrmHandoffEnvelope;
use App\Models\CrmHandoffMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class StoreCrmHandoffMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 5;

    public int $timeout = 15;

    /**
     * @param  array{message_id: string, handoff_id: ?string, phone: string, text: string}  $message
     */
    public function __construct(public array $message)
    {
        $this->onConnection(StoreCrmHandoffEnvelopeJob::resolveConnection(
            config('crm-inbox.queue_connection'),
            app()->runningUnitTests() || app()->environment('local'),
        ));
        $this->onQueue((string) config('crm-inbox.queue', 'inbox'));
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [5, 20, 60, 120, 300];
    }

    public function handle(): void
    {
        $messageId = (string) ($this->message['message_id'] ?? '');
        $phone = (string) ($this->message['phone'] ?? '');
        $text = (string) ($this->message['text'] ?? '');

        if ($messageId === '' || $phone === '' || $text === '') {
            return;
        }

        DB::transaction(function () use ($messageId, $phone, $text): void {
            $stored = CrmHandoffMessage::query()->firstOrCreate(
                ['message_id' => $messageId],
                [
                    'handoff_id' => $this->message['handoff_id'] ?? null,
                    'phone' => $phone,
                    'body' => $text,
                    'received_at' => now(),
                ],
            );

            if (! $stored->wasRecentlyCreated) {
                return;
            }

            $envelope = $this->envelope($stored->handoff_id, $phone);

            if ($envelope === null) {
                return;
            }

            if ($stored->handoff_id === null) {
                $stored->forceFill(['handoff_id' => $envelope->handoff_id])->save();
            }

            $envelope->forceFill([
                'last_customer_at' => $stored->received_at ?? now(),
                'last_customer_text' => mb_substr($text, 0, 200),
            ])->save();
        });
    }

    public function failed(Throwable $exception): void
    {
        Log::error('CRM handoff: no se pudo guardar el mensaje', [
            'message_id' => $this->message['message_id'] ?? null,
            'error' => $exception->getMessage(),
        ]);
    }

    private function envelope(?string $handoffId, string $phone): ?CrmHandoffEnvelope
    {
        if ($handoffId !== null && $handoffId !== '') {
            $match = CrmHandoffEnvelope::query()->where('handoff_id', $handoffId)->first();

            if ($match !== null) {
                return $match;
            }
        }

        return CrmHandoffEnvelope::query()
            ->where('phone', $phone)
            ->orderByDesc('id')
            ->first();
    }
}
