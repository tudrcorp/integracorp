<?php

declare(strict_types=1);

namespace App\Support\CrmInbox;

use App\Models\CrmHandoffEnvelope;
use App\Models\CrmHandoffMessage;
use Carbon\CarbonInterface;

/**
 * Arma lo que el analista lee de un sobre ya guardado. No consulta otras tablas.
 */
final class CrmInboxCase
{
    /**
     * @return array{
     *     handoff_id: string,
     *     name: string,
     *     phone: string,
     *     phone_label: string,
     *     area: ?string,
     *     area_label: string,
     *     last_line: string,
     *     waiting: string,
     *     waiting_since: ?int,
     *     taken: bool,
     *     taken_by: ?int,
     *     assigned_to: ?int,
     *     assignee: ?string
     * }
     */
    public static function row(CrmHandoffEnvelope $envelope, CarbonInterface $now, ?string $assignee = null): array
    {
        $payload = self::payload($envelope);
        $since = $envelope->last_customer_at ?? $envelope->accepted_at;

        return [
            'handoff_id' => (string) $envelope->handoff_id,
            'name' => self::name($payload),
            'phone' => (string) $envelope->phone,
            'phone_label' => self::phoneLabel((string) $envelope->phone),
            'area' => $envelope->area,
            'area_label' => CrmInboxAreas::label($envelope->area),
            'last_line' => self::excerpt(self::visibleLine($envelope, $payload)),
            'waiting' => self::waiting($since, $now),
            'waiting_since' => $since?->getTimestamp(),
            'taken' => $envelope->taken_at !== null,
            'taken_by' => $envelope->taken_at !== null && $envelope->taken_by !== null ? (int) $envelope->taken_by : null,
            'assigned_to' => $envelope->taken_at === null && $envelope->assigned_to !== null ? (int) $envelope->assigned_to : null,
            'assignee' => $envelope->taken_at === null ? $assignee : null,
        ];
    }

    /**
     * @return array{
     *     handoff_id: string,
     *     name: string,
     *     phone: string,
     *     phone_label: string,
     *     area: ?string,
     *     area_label: string,
     *     last_line: string,
     *     waiting: string,
     *     waiting_since: ?int,
     *     taken: bool,
     *     taken_by: ?int,
     *     assigned_to: ?int,
     *     summary_lines: list<string>,
     *     context: array{motivo: ?string, necesidad: ?string, quote_control: ?string, quote_total: ?string},
     *     messages: list<array{author: string, text: string, time: ?string}>,
     *     quote_label: ?string,
     *     attention: list<array{id: string, text: string, time: string, author: string, role: string, status: string, document: bool, at: ?int, day: ?string, quote: ?array<string, mixed>}>,
     *     assignee: ?string,
     *     taker: ?string,
     *     timeline: list<array{time: string, label: string}>,
     *     accepted_time: ?string
     * }
     */
    public static function detail(CrmHandoffEnvelope $envelope, CarbonInterface $now, ?string $assignee = null, ?string $taker = null): array
    {
        $payload = self::payload($envelope);
        $row = self::row($envelope, $now, $assignee);

        return [
            ...$row,
            'last_line' => self::visibleLine($envelope, $payload),
            'summary_lines' => self::summaryLines($payload, $envelope->motivo),
            'context' => self::context($payload, $envelope->motivo),
            'messages' => self::messages($payload),
            'quote_label' => self::quoteLabel($payload['cotizacion'] ?? null),
            'attention' => self::attention($envelope),
            'taker' => $row['taken'] ? $taker : null,
            'timeline' => self::timeline($envelope, $row['area_label'], $row['assignee'], $taker, $now),
            'accepted_time' => $envelope->accepted_at?->copy()->timezone('America/Caracas')->format('H:i'),
        ];
    }

    /**
     * Lo mismo que el resumen, en piezas: la vista lo muestra como etiquetas y no como texto corrido.
     *
     * @param  array<string, mixed>  $payload
     * @return array{motivo: ?string, necesidad: ?string, quote_control: ?string, quote_total: ?string}
     */
    private static function context(array $payload, ?string $motivo): array
    {
        $quote = is_array($payload['cotizacion'] ?? null) ? $payload['cotizacion'] : [];
        $control = trim((string) ($quote['control'] ?? ''));
        $total = $quote['total'] ?? null;
        $reason = trim((string) ($payload['motivo'] ?? $motivo ?? ''));
        $need = trim((string) ($payload['necesidad'] ?? ''));

        return [
            'motivo' => $reason !== '' ? $reason : null,
            'necesidad' => $need !== '' ? $need : null,
            'quote_control' => $control !== '' ? $control : null,
            'quote_total' => is_numeric($total) ? number_format((float) $total, 2, ',', '.') : null,
        ];
    }

    /**
     * Recorrido del caso con las marcas que ya guarda el sobre. Sin consultas extra.
     *
     * @return list<array{time: string, label: string}>
     */
    private static function timeline(CrmHandoffEnvelope $envelope, string $areaLabel, ?string $assignee, ?string $taker, CarbonInterface $now): array
    {
        $events = [];

        if ($envelope->accepted_at !== null) {
            $events[] = [$envelope->accepted_at, 'SolIA lo pasó a '.$areaLabel];
        }

        if ($envelope->assigned_at !== null && $assignee !== null) {
            $events[] = [$envelope->assigned_at, 'Marcado para '.$assignee];
        }

        if ($envelope->taken_at !== null) {
            $events[] = [$envelope->taken_at, $taker !== null && $taker !== '' ? 'Lo tomó '.$taker : 'Tomado por un analista'];
        }

        if ($envelope->last_customer_at !== null) {
            $events[] = [$envelope->last_customer_at, 'Último mensaje del cliente'];
        }

        usort($events, fn (array $a, array $b): int => $a[0]->getTimestamp() <=> $b[0]->getTimestamp());
        $today = $now->copy()->timezone('America/Caracas')->toDateString();

        return array_map(function (array $event) use ($today): array {
            $local = $event[0]->copy()->timezone('America/Caracas');

            return [
                'time' => $local->toDateString() === $today ? $local->format('H:i') : $local->format('d/m H:i'),
                'label' => $event[1],
            ];
        }, $events);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function visibleLine(CrmHandoffEnvelope $envelope, array $payload): string
    {
        $latest = trim((string) $envelope->last_customer_text);

        if ($latest !== '') {
            return $latest;
        }

        return self::lastLine($payload, $envelope->motivo);
    }

    /**
     * @return list<array{id: string, text: string, time: string, author: string, role: string, status: string, document: bool, at: ?int, day: ?string, quote: ?array<string, mixed>}>
     */
    private static function attention(CrmHandoffEnvelope $envelope): array
    {
        return CrmHandoffMessage::query()
            ->where(function ($query) use ($envelope): void {
                $query->where('handoff_id', $envelope->handoff_id)
                    ->orWhere(function ($query) use ($envelope): void {
                        $query->whereNull('handoff_id')->where('phone', $envelope->phone);
                    });
            })
            ->orderBy('id')
            ->limit(100)
            ->get(['message_id', 'body', 'received_at', 'direction', 'kind', 'status', 'meta'])
            ->map(fn (CrmHandoffMessage $message): array => self::attentionItem($message))
            ->all();
    }

    /**
     * @return array{id: string, text: string, time: string, author: string, role: string, status: string, document: bool, at: ?int, day: ?string, quote: ?array<string, mixed>}
     */
    private static function attentionItem(CrmHandoffMessage $message): array
    {
        $kind = (string) ($message->kind ?: 'message');
        $direction = (string) ($message->direction ?: 'in');
        $role = 'in';
        $author = 'Cliente';

        if ($kind === 'note') {
            $role = 'note';
            $author = 'Nota interna';
        } elseif ($direction === 'out') {
            $role = 'out';
            $author = 'Tú';
        }

        return [
            'id' => (string) $message->message_id,
            'text' => (string) $message->body,
            'time' => $message->received_at?->timezone('America/Caracas')->format('H:i') ?? '',
            'author' => $author,
            'role' => $role,
            'status' => (string) ($message->status ?: 'received'),
            'document' => $kind === 'quote',
            'at' => $message->received_at?->getTimestamp(),
            'day' => $message->received_at?->timezone('America/Caracas')->toDateString(),
            'quote' => $kind === 'quote' && is_array($message->meta) ? $message->meta : null,
        ];
    }

    public static function phoneLabel(string $digits): string
    {
        $digits = preg_replace('/\D+/', '', $digits) ?? '';

        if (strlen($digits) === 12 && str_starts_with($digits, '58')) {
            return '+58 '.substr($digits, 2, 3).' '.substr($digits, 5, 3).' '.substr($digits, 8, 4);
        }

        return $digits;
    }

    /**
     * @return array<string, mixed>
     */
    private static function payload(CrmHandoffEnvelope $envelope): array
    {
        return is_array($envelope->payload) ? $envelope->payload : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function name(array $payload): string
    {
        $name = trim((string) ($payload['name'] ?? ''));

        return $name !== '' ? $name : 'Sin nombre';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function lastLine(array $payload, ?string $motivo): string
    {
        $latest = trim((string) ($payload['ultimo_mensaje'] ?? ''));

        if ($latest !== '') {
            return $latest;
        }

        $messages = self::messages($payload);

        for ($index = count($messages) - 1; $index >= 0; $index--) {
            if ($messages[$index]['author'] === 'Cliente' && $messages[$index]['text'] !== '') {
                return $messages[$index]['text'];
            }
        }

        $motivo = trim((string) $motivo);

        return $motivo !== '' ? $motivo : 'Sin mensaje del cliente';
    }

    private static function excerpt(string $text): string
    {
        if (mb_strlen($text) <= 120) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, 117)).'…';
    }

    private static function waiting(?CarbonInterface $acceptedAt, CarbonInterface $now): string
    {
        if ($acceptedAt === null) {
            return 'sin hora de llegada';
        }

        return $acceptedAt->locale('es')->diffForHumans($now, [
            'syntax' => CarbonInterface::DIFF_RELATIVE_TO_NOW,
            'parts' => 1,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    private static function summaryLines(array $payload, ?string $motivo): array
    {
        $lines = [];
        $motivo = trim((string) ($payload['motivo'] ?? $motivo ?? ''));
        $necesidad = trim((string) ($payload['necesidad'] ?? ''));
        $quote = self::quoteLabel($payload['cotizacion'] ?? null);

        if ($motivo !== '') {
            $lines[] = 'Motivo: '.$motivo;
        }

        if ($necesidad !== '') {
            $lines[] = 'Necesidad: '.$necesidad;
        }

        if ($quote !== null) {
            $lines[] = 'Propuesta: '.$quote;
        }

        if ($lines === []) {
            $lines[] = 'El bot no dejó un resumen.';
        }

        return array_slice($lines, 0, 3);
    }

    private static function quoteLabel(mixed $quote): ?string
    {
        if (! is_array($quote)) {
            return null;
        }

        $control = trim((string) ($quote['control'] ?? ''));
        $total = $quote['total'] ?? null;
        $parts = [];

        if ($control !== '') {
            $parts[] = $control;
        }

        if (is_numeric($total)) {
            $parts[] = number_format((float) $total, 2, ',', '.');
        }

        if ($parts === []) {
            return null;
        }

        return implode(' · ', $parts);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array{author: string, text: string, time: ?string}>
     */
    private static function messages(array $payload): array
    {
        $raw = $payload['mensajes'] ?? null;

        if (! is_array($raw)) {
            return [];
        }

        $messages = [];

        foreach ($raw as $message) {
            if (! is_array($message)) {
                continue;
            }

            $text = trim((string) ($message['text'] ?? ''));

            if ($text === '') {
                continue;
            }

            $time = trim((string) ($message['hora'] ?? ''));

            $messages[] = [
                'author' => self::author($message['direction'] ?? null),
                'text' => $text,
                'time' => $time !== '' ? $time : null,
            ];
        }

        return $messages;
    }

    private static function author(mixed $direction): string
    {
        $direction = strtolower(trim((string) $direction));

        if (in_array($direction, ['in', 'inbound', 'user', 'customer'], true)) {
            return 'Cliente';
        }

        if (in_array($direction, ['out', 'outbound', 'bot', 'assistant'], true)) {
            return 'Bot';
        }

        return 'Mensaje';
    }
}
