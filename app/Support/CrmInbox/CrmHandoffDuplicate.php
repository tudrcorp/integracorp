<?php

declare(strict_types=1);

namespace App\Support\CrmInbox;

use App\Models\CrmHandoffEnvelope;
use App\Models\CrmHandoffMessage;

/**
 * Una persona, un caso abierto. Si el bot deriva otra vez a un cliente que ya tiene un caso
 * abierto en la bandeja (ni finalizado, ni devuelto al bot, ni cerrado), no se abre otro: lo
 * nuevo queda como nota interna en el caso que ya existe y el caso sube en la cola.
 *
 * La nota usa un id fijo por derivación: un reintento del bot no la repite.
 */
final class CrmHandoffDuplicate
{
    public const TEXT_MAX = 600;

    /**
     * Caso abierto del teléfono, el más reciente. Se bloquea para que dos derivaciones
     * simultáneas no abran dos casos.
     */
    public static function openFor(string $phone): ?CrmHandoffEnvelope
    {
        if ($phone === '') {
            return null;
        }

        return CrmHandoffEnvelope::query()
            ->where('phone', $phone)
            ->whereNull('released_at')
            ->whereNull('closed_at')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();
    }

    /**
     * @param  array<string, mixed>  $envelope  sobre que mandó el bot
     */
    public static function merge(CrmHandoffEnvelope $open, array $envelope): bool
    {
        $handoffId = (string) ($envelope['handoff_id'] ?? '');

        $note = CrmHandoffMessage::query()->firstOrCreate(
            ['message_id' => 'merge:'.$handoffId],
            [
                'handoff_id' => $open->handoff_id,
                'phone' => $open->phone,
                'body' => self::note($open, $envelope),
                'received_at' => now(),
                'direction' => 'out',
                'kind' => 'note',
                'status' => 'stored',
                'user_id' => null,
                'meta' => [
                    'merged_handoff_id' => $handoffId,
                    'area' => self::clean($envelope['area'] ?? null),
                    'motivo' => self::clean($envelope['motivo'] ?? null),
                ],
            ],
        );

        if (! $note->wasRecentlyCreated) {
            return false;
        }

        $latest = self::clean($envelope['ultimo_mensaje'] ?? null);

        if ($latest !== null) {
            $open->forceFill([
                'last_customer_at' => now(),
                'last_customer_text' => mb_substr($latest, 0, 200),
            ])->save();
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $envelope
     */
    public static function note(CrmHandoffEnvelope $open, array $envelope): string
    {
        $area = self::clean($envelope['area'] ?? null);
        $motivo = self::clean($envelope['motivo'] ?? null);
        $latest = self::clean($envelope['ultimo_mensaje'] ?? null);
        $otherArea = $area !== null && $area !== $open->area;

        $text = 'Sol volvió a derivar esta conversación'
            .($otherArea ? ' al área '.CrmInboxAreas::label($area) : '')
            .($motivo !== null ? ': '.rtrim($motivo, '.') : '').'.'
            .($latest !== null ? ' Lo nuevo que escribió el cliente: «'.mb_substr($latest, 0, 400).'».' : '')
            .' No se abrió otro caso'
            .($otherArea ? '; si corresponde, muévelo a '.CrmInboxAreas::label($area).'.' : '.');

        return mb_substr($text, 0, self::TEXT_MAX);
    }

    private static function clean(mixed $value): ?string
    {
        $text = trim(preg_replace('/\s+/u', ' ', is_scalar($value) ? (string) $value : '') ?? '');

        return $text === '' ? null : $text;
    }
}
