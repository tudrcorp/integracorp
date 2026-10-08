<?php

declare(strict_types=1);

namespace App\Support\CrmInbox;

use App\Models\CrmHandoffEnvelope;
use App\Models\CrmHandoffMessage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Guarda lo que escribe el analista y le pide a n8n que lo mande por WhatsApp.
 * La nota interna no sale. Un reintento usa el mismo id y no se envía dos veces.
 */
final class CrmHandoffReply
{
    /**
     * @return array{ok: true, already: bool}|array{ok: false, reason: string}
     */
    public function stage(CrmHandoffEnvelope $envelope, ?int $userId, string $replyId, string $text, string $analystName = ''): array
    {
        if ($envelope->taken_at === null) {
            return ['ok' => false, 'reason' => 'not_taken'];
        }

        $replyId = $this->replyId($replyId);
        $text = $this->signed($envelope, $userId, $this->text($text), $analystName);
        $invalid = $this->invalidText($text);

        if ($replyId === null) {
            return ['ok' => false, 'reason' => 'invalid'];
        }

        if ($invalid !== null) {
            return ['ok' => false, 'reason' => $invalid];
        }

        $existing = CrmHandoffMessage::query()->where('message_id', $replyId)->first();

        if ($existing !== null) {
            if ((string) $existing->handoff_id !== (string) $envelope->handoff_id || $existing->direction !== 'out' || $existing->kind !== 'message') {
                return ['ok' => false, 'reason' => 'invalid'];
            }

            return ['ok' => true, 'already' => true];
        }

        CrmHandoffMessage::query()->create([
            'message_id' => $replyId,
            'handoff_id' => $envelope->handoff_id,
            'phone' => $envelope->phone,
            'body' => $text,
            'received_at' => now(),
            'direction' => 'out',
            'kind' => 'message',
            'status' => 'pending',
            'user_id' => $userId,
        ]);

        return ['ok' => true, 'already' => false];
    }

    /**
     * @return array{ok: true}|array{ok: false, reason: string}
     */
    public function note(CrmHandoffEnvelope $envelope, ?int $userId, string $text): array
    {
        if ($envelope->taken_at === null) {
            return ['ok' => false, 'reason' => 'not_taken'];
        }

        $text = $this->text($text);
        $invalid = $this->invalidText($text);

        if ($invalid !== null) {
            return ['ok' => false, 'reason' => $invalid];
        }

        CrmHandoffMessage::query()->create([
            'message_id' => (string) Str::uuid(),
            'handoff_id' => $envelope->handoff_id,
            'phone' => $envelope->phone,
            'body' => $text,
            'received_at' => now(),
            'direction' => 'out',
            'kind' => 'note',
            'status' => 'stored',
            'user_id' => $userId,
        ]);

        return ['ok' => true];
    }

    /**
     * Una sola presentación por caso. Si WhatsApp no la acepta, el caso sigue tomado.
     *
     * @return array{ok: true, already: bool}|array{ok: false, reason: string}
     */
    public function introduce(CrmHandoffEnvelope $envelope, ?int $userId, string $fullName): array
    {
        return $this->spoken(
            $envelope,
            'intro',
            CrmAnalystName::introduction(CrmAnalystName::given($fullName)),
            $userId,
        );
    }

    /**
     * Avisa el cierre antes de soltar al bot. Un segundo intento no repite la frase.
     *
     * @return array{ok: true, already: bool}|array{ok: false, reason: string}
     */
    public function farewell(CrmHandoffEnvelope $envelope, string $fullName): array
    {
        if ($envelope->released_at !== null) {
            return ['ok' => true, 'already' => true];
        }

        $userId = is_numeric($envelope->taken_by) ? (int) $envelope->taken_by : null;

        return $this->spoken(
            $envelope,
            'closing',
            CrmAnalystName::closing(CrmAnalystName::given($fullName)),
            $userId,
        );
    }

    /**
     * @return array{ok: true, already: bool}|array{ok: false, reason: string}
     */
    public function deliver(CrmHandoffEnvelope $envelope, string $replyId): array
    {
        $replyId = $this->replyId($replyId);

        if ($replyId === null) {
            return ['ok' => false, 'reason' => 'invalid'];
        }

        $message = CrmHandoffMessage::query()
            ->where('message_id', $replyId)
            ->where('handoff_id', $envelope->handoff_id)
            ->first();

        if ($message === null || $message->direction !== 'out' || ! in_array($message->kind, ['message', 'intro', 'closing', 'quote'], true)) {
            return ['ok' => false, 'reason' => 'invalid'];
        }

        if ($message->status === 'sent') {
            return ['ok' => true, 'already' => true];
        }

        if ($envelope->taken_at === null) {
            return ['ok' => false, 'reason' => 'not_taken'];
        }

        $lock = null;

        try {
            $lock = Cache::lock('crm-reply:'.$message->message_id, 20);

            if (! $lock->get()) {
                return ['ok' => false, 'reason' => 'busy'];
            }
        } catch (Throwable) {
            $lock = null;
        }

        try {
            $message->refresh();

            if ($message->status === 'sent') {
                return ['ok' => true, 'already' => true];
            }

            return $message->kind === 'quote'
                ? $this->postDocument($envelope, $message)
                : $this->post($envelope, $message);
        } finally {
            $lock?->release();
        }
    }

    /**
     * Deja la propuesta en el hilo. El PDF ya está en disco; el envío lo hace n8n.
     *
     * @param  array<string, mixed>  $meta  Plan, personas y cobertura para la tarjeta del hilo.
     * @return array{ok: true, already: bool}|array{ok: false, reason: string}
     */
    public function stageQuote(CrmHandoffEnvelope $envelope, ?int $userId, string $replyId, string $caption, array $meta = []): array
    {
        if ($envelope->taken_at === null) {
            return ['ok' => false, 'reason' => 'not_taken'];
        }

        $replyId = $this->replyId($replyId);
        $caption = $this->text($caption);

        if ($replyId === null || $caption === '' || mb_strlen($caption) > 1024) {
            return ['ok' => false, 'reason' => 'invalid'];
        }

        if (! Storage::disk('local')->exists(CrmInboxQuote::path($replyId))) {
            return ['ok' => false, 'reason' => 'missing'];
        }

        $existing = CrmHandoffMessage::query()->where('message_id', $replyId)->first();

        if ($existing !== null) {
            if ((string) $existing->handoff_id !== (string) $envelope->handoff_id || $existing->direction !== 'out' || $existing->kind !== 'quote') {
                return ['ok' => false, 'reason' => 'invalid'];
            }

            return ['ok' => true, 'already' => true];
        }

        CrmHandoffMessage::query()->create([
            'message_id' => $replyId,
            'handoff_id' => $envelope->handoff_id,
            'phone' => $envelope->phone,
            'body' => $caption,
            'received_at' => now(),
            'direction' => 'out',
            'kind' => 'quote',
            'status' => 'pending',
            'user_id' => $userId,
            'meta' => self::quoteMeta($meta),
        ]);

        return ['ok' => true, 'already' => false];
    }

    /**
     * @return array{ok: true, already: bool}|array{ok: false, reason: string}
     */
    private function post(CrmHandoffEnvelope $envelope, CrmHandoffMessage $message): array
    {
        $url = $this->url();
        $key = (string) config('crm-inbox.takeover_key', '');

        if ($url === '' || $key === '') {
            $message->forceFill(['status' => 'failed'])->save();

            return ['ok' => false, 'reason' => 'unconfigured'];
        }

        try {
            $response = Http::timeout(12)
                ->connectTimeout(3)
                ->acceptJson()
                ->withHeaders([
                    'X-Handoff-Key' => $key,
                    'Accept' => 'application/json',
                ])
                ->post($url, [
                    'phone' => $message->phone,
                    'text' => $message->body,
                    'reply_id' => $message->message_id,
                ]);
        } catch (Throwable $exception) {
            $message->forceFill(['status' => 'failed'])->save();

            Log::warning('CRM handoff: n8n no confirmó la respuesta', [
                'handoff_id' => $envelope->handoff_id,
                'error' => $exception->getMessage(),
            ]);

            return ['ok' => false, 'reason' => 'unreachable'];
        }

        if (! $response->successful() || $response->json('ok') !== true) {
            $message->forceFill(['status' => 'failed'])->save();

            Log::warning('CRM handoff: n8n rechazó la respuesta', [
                'handoff_id' => $envelope->handoff_id,
                'status' => $response->status(),
            ]);

            return ['ok' => false, 'reason' => 'rejected'];
        }

        $providerId = trim((string) $response->json('message_id'));

        $message->forceFill([
            'status' => 'sent',
            'provider_message_id' => $providerId !== '' ? $providerId : null,
        ])->save();

        return ['ok' => true, 'already' => $response->json('duplicate') === true];
    }

    /**
     * @return array{ok: true, already: bool}|array{ok: false, reason: string}
     */
    private function postDocument(CrmHandoffEnvelope $envelope, CrmHandoffMessage $message): array
    {
        $url = $this->documentUrl();
        $key = (string) config('crm-inbox.takeover_key', '');
        $path = CrmInboxQuote::path((string) $message->message_id);
        $pdf = Storage::disk('local')->get($path);

        if ($url === '' || $key === '') {
            $message->forceFill(['status' => 'failed'])->save();

            return ['ok' => false, 'reason' => 'unconfigured'];
        }

        if (! is_string($pdf) || ! str_starts_with($pdf, '%PDF')) {
            $message->forceFill(['status' => 'failed'])->save();

            return ['ok' => false, 'reason' => 'missing'];
        }

        $filename = 'Propuesta.pdf';

        if (preg_match('/Propuesta\s+(\S+)/', (string) $message->body, $match) === 1) {
            $safe = preg_replace('/[^A-Za-z0-9._-]/', '', $match[1]) ?? '';
            $filename = 'Propuesta-'.($safe !== '' ? $safe : 'cotizacion').'.pdf';
        }

        try {
            $response = Http::timeout(25)
                ->connectTimeout(3)
                ->acceptJson()
                ->withHeaders([
                    'X-Handoff-Key' => $key,
                    'Accept' => 'application/json',
                ])
                ->post($url, [
                    'phone' => $message->phone,
                    'reply_id' => $message->message_id,
                    'filename' => $filename,
                    'caption' => $message->body,
                    'pdf_base64' => base64_encode($pdf),
                ]);
        } catch (Throwable $exception) {
            $message->forceFill(['status' => 'failed'])->save();

            Log::warning('CRM handoff: n8n no confirmó la propuesta', [
                'handoff_id' => $envelope->handoff_id,
                'error' => $exception->getMessage(),
            ]);

            return ['ok' => false, 'reason' => 'unreachable'];
        }

        if (! $response->successful() || $response->json('ok') !== true) {
            $message->forceFill(['status' => 'failed'])->save();

            Log::warning('CRM handoff: n8n rechazó la propuesta', [
                'handoff_id' => $envelope->handoff_id,
                'status' => $response->status(),
            ]);

            return ['ok' => false, 'reason' => 'rejected'];
        }

        $providerId = trim((string) $response->json('message_id'));

        $message->forceFill([
            'status' => 'sent',
            'provider_message_id' => $providerId !== '' ? $providerId : null,
        ])->save();

        CrmInboxQuote::forget((string) $message->message_id);

        return ['ok' => true, 'already' => $response->json('duplicate') === true];
    }

    private function url(): string
    {
        $configured = trim((string) config('crm-inbox.reply_url', ''));

        if ($configured !== '') {
            return $configured;
        }

        $takeover = trim((string) config('crm-inbox.takeover_url', ''));

        if (str_ends_with($takeover, '/handoff/takeover')) {
            return substr($takeover, 0, -strlen('takeover')).'reply';
        }

        return '';
    }

    private function documentUrl(): string
    {
        $configured = trim((string) config('crm-inbox.document_url', ''));

        if ($configured !== '') {
            return $configured;
        }

        $takeover = trim((string) config('crm-inbox.takeover_url', ''));

        if (str_ends_with($takeover, '/handoff/takeover')) {
            return substr($takeover, 0, -strlen('takeover')).'document';
        }

        return '';
    }

    private function replyId(string $replyId): ?string
    {
        $replyId = strtolower(trim($replyId));

        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $replyId)) {
            return null;
        }

        return $replyId;
    }

    /**
     * @return array{ok: true, already: bool}|array{ok: false, reason: string}
     */
    private function spoken(CrmHandoffEnvelope $envelope, string $kind, string $text, ?int $userId): array
    {
        if ($envelope->taken_at === null) {
            return ['ok' => false, 'reason' => 'not_taken'];
        }

        $lock = null;

        try {
            $lock = Cache::lock('crm-'.$kind.':'.$envelope->handoff_id, 20);

            if (! $lock->get()) {
                return ['ok' => false, 'reason' => 'busy'];
            }
        } catch (Throwable) {
            $lock = null;
        }

        try {
            $existing = CrmHandoffMessage::query()
                ->where('handoff_id', $envelope->handoff_id)
                ->where('kind', $kind)
                ->orderBy('id')
                ->first();

            if ($existing === null) {
                $existing = CrmHandoffMessage::query()->create([
                    'message_id' => (string) Str::uuid(),
                    'handoff_id' => $envelope->handoff_id,
                    'phone' => $envelope->phone,
                    'body' => $text,
                    'received_at' => now(),
                    'direction' => 'out',
                    'kind' => $kind,
                    'status' => 'pending',
                    'user_id' => $userId,
                ]);
            }
        } finally {
            $lock?->release();
        }

        if ($existing->status === 'sent') {
            return ['ok' => true, 'already' => true];
        }

        return $this->deliver($envelope, (string) $existing->message_id);
    }

    private function signed(CrmHandoffEnvelope $envelope, ?int $userId, string $text, string $analystName): string
    {
        if ($text === '' || $userId === null || $envelope->taken_by === null || (int) $envelope->taken_by === $userId) {
            return $text;
        }

        $given = CrmAnalystName::given($analystName);

        if ($given === '') {
            return $text;
        }

        return $given.': '.$text;
    }

    private function text(string $text): string
    {
        return trim(str_replace(["\r\n", "\r"], "\n", $text));
    }

    private function invalidText(string $text): ?string
    {
        if ($text === '') {
            return 'empty';
        }

        if (mb_strlen($text) > 2000) {
            return 'too_long';
        }

        return null;
    }

    /**
     * Solo lo que la tarjeta del hilo necesita, con tipos y largos acotados.
     *
     * @param  array<string, mixed>  $meta
     * @return array{control: string, total: string, plan: string, people: int, ages: string, coverage: ?int}|null
     */
    private static function quoteMeta(array $meta): ?array
    {
        if ($meta === []) {
            return null;
        }

        $coverage = $meta['coverage'] ?? null;

        return [
            'control' => mb_substr(trim((string) ($meta['control'] ?? '')), 0, 20),
            'total' => mb_substr(trim((string) ($meta['total'] ?? '')), 0, 20),
            'plan' => mb_substr(trim((string) ($meta['plan'] ?? '')), 0, 20),
            'people' => max(0, min(99, (int) ($meta['people'] ?? 0))),
            'ages' => mb_substr(trim((string) ($meta['ages'] ?? '')), 0, 60),
            'coverage' => is_numeric($coverage) && (int) $coverage > 0 ? (int) $coverage : null,
        ];
    }
}
