<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\CrmHandoffAttachment;
use App\Models\CrmHandoffEnvelope;
use App\Models\CrmHandoffMessage;
use App\Support\CrmInbox\CrmHandoffMedia;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Registra el archivo que el cliente mandó por WhatsApp. El archivo ya está en el disco privado (lo guardó
 * la puerta); aquí solo se crean el adjunto y su burbuja en el hilo.
 *
 * El bot también manda el mismo mensaje como texto («[El cliente envió una imagen…]», la leyenda o la
 * transcripción del audio) con el mismo message_id. Llegue antes uno u otro, queda una sola burbuja: la
 * de archivo.
 */
class StoreCrmHandoffMediaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 5;

    public int $timeout = 20;

    /**
     * @param  array{message_id: string, handoff_id: ?string, phone: string, media_type: string, mime_type: string, extension: string, original_name: string, caption: ?string, size: int, sha256: string, disk: string, path: string}  $media
     */
    public function __construct(public array $media)
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
        $messageId = (string) ($this->media['message_id'] ?? '');
        $phone = (string) ($this->media['phone'] ?? '');

        if ($messageId === '' || $phone === '' || (string) ($this->media['path'] ?? '') === '') {
            return;
        }

        DB::transaction(function () use ($messageId, $phone): void {
            $attachment = CrmHandoffAttachment::query()->where('message_id', $messageId)->lockForUpdate()->first();

            if ($attachment !== null) {
                if ($attachment->path !== $this->media['path']) {
                    $this->deleteFile();
                }

                return;
            }

            $envelope = $this->envelope($this->media['handoff_id'] ?? null, $phone);
            $handoffId = $this->threadHandoffId($envelope, $this->media['handoff_id'] ?? null);
            $caption = $this->media['caption'] ?? null;

            $attachment = CrmHandoffAttachment::query()->create([
                'message_id' => $messageId,
                'handoff_id' => $handoffId,
                'phone' => $phone,
                'media_type' => $this->media['media_type'],
                'mime_type' => $this->media['mime_type'],
                'original_name' => $this->media['original_name'],
                'extension' => $this->media['extension'],
                'size_bytes' => (int) $this->media['size'],
                'sha256' => $this->media['sha256'],
                'disk' => $this->media['disk'],
                'path' => $this->media['path'],
                'caption' => $caption,
            ]);

            $message = CrmHandoffMessage::query()->where('message_id', $messageId)->lockForUpdate()->first();
            $meta = ['attachment_id' => $attachment->id];

            if ($message === null) {
                $message = CrmHandoffMessage::query()->create([
                    'message_id' => $messageId,
                    'handoff_id' => $handoffId,
                    'phone' => $phone,
                    'body' => (string) ($caption ?? ''),
                    'received_at' => now(),
                    'direction' => 'in',
                    'kind' => CrmHandoffMedia::MESSAGE_KIND,
                    'status' => 'received',
                    'meta' => $meta,
                ]);
            } else {
                $message->forceFill([
                    'kind' => CrmHandoffMedia::MESSAGE_KIND,
                    'body' => CrmHandoffMedia::bodyFor((string) $this->media['media_type'], $caption, (string) $message->body),
                    'handoff_id' => $message->handoff_id ?? $handoffId,
                    'meta' => [...(is_array($message->meta) ? $message->meta : []), ...$meta],
                ])->save();
            }

            $attachment->forceFill(['crm_handoff_message_id' => $message->id])->save();

            if ($envelope !== null) {
                $envelope->forceFill([
                    'last_customer_at' => $message->received_at ?? now(),
                    'last_customer_text' => mb_substr(CrmHandoffMedia::preview($attachment), 0, 200),
                ])->save();
            }
        });
    }

    public function failed(Throwable $exception): void
    {
        $messageId = (string) ($this->media['message_id'] ?? '');

        try {
            $registered = $messageId !== '' && CrmHandoffAttachment::query()
                ->where('message_id', $messageId)
                ->where('path', (string) ($this->media['path'] ?? ''))
                ->exists();

            if (! $registered) {
                $this->deleteFile();
            }
        } catch (Throwable) {
        }

        Log::error('CRM handoff: no se pudo registrar el archivo del cliente', [
            'message_id' => $messageId !== '' ? $messageId : null,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * El caso del hilo: el de la derivación que mandó el bot o, sin ella, el último del teléfono.
     */
    private function envelope(?string $handoffId, string $phone): ?CrmHandoffEnvelope
    {
        if ($handoffId !== null && $handoffId !== '') {
            $match = CrmHandoffEnvelope::query()->where('handoff_id', $handoffId)->first();

            if ($match !== null) {
                return $match;
            }
        }

        return CrmHandoffEnvelope::query()->where('phone', $phone)->orderByDesc('id')->first();
    }

    /**
     * Igual que los mensajes de texto: si la derivación se unió a un caso abierto, el archivo va al hilo de ese caso.
     */
    private function threadHandoffId(?CrmHandoffEnvelope $envelope, ?string $handoffId): ?string
    {
        $handoffId = $handoffId !== null && $handoffId !== '' ? $handoffId : null;

        if ($envelope === null) {
            return $handoffId;
        }

        if ($handoffId === null || ($handoffId !== $envelope->handoff_id && $envelope->released_at === null && $envelope->closed_at === null)) {
            return $envelope->handoff_id;
        }

        return $handoffId;
    }

    private function deleteFile(): void
    {
        try {
            Storage::disk((string) $this->media['disk'])->delete((string) $this->media['path']);
        } catch (Throwable) {
        }
    }
}
