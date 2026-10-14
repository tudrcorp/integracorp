<?php

declare(strict_types=1);

namespace App\Support\CrmInbox;

use App\Enums\CrmCaseOutcome;
use App\Models\CrmHandoffEnvelope;
use App\Models\CrmHandoffMessage;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * Conversaciones anteriores del mismo teléfono: cuándo, con qué equipo, quién lo atendió y cómo terminó.
 * El hilo de cada una se carga solo si el analista lo abre.
 */
final class CrmInboxHistory
{
    public const LIMIT = 6;

    public const THREAD_LIMIT = 40;

    /**
     * @return list<array{handoff_id: string, date: string, area: string, motivo: string, by: ?string, outcome: ?string, note: ?string, open: bool}>
     */
    public static function forPhone(string $phone, string $exceptHandoffId): array
    {
        $rows = CrmHandoffEnvelope::query()
            ->where('phone', $phone)
            ->where('handoff_id', '!=', $exceptHandoffId)
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get(['id', 'handoff_id', 'area', 'motivo', 'accepted_at', 'taken_by', 'released_at', 'closed_at', 'outcome', 'outcome_note']);

        if ($rows->isEmpty()) {
            return [];
        }

        $names = [];
        $ids = $rows->pluck('taken_by')->filter()->unique()->values()->all();

        if ($ids !== [] && Schema::hasTable('users')) {
            foreach (User::query()->whereIn('id', $ids)->pluck('name', 'id') as $id => $name) {
                $given = CrmAnalystName::given((string) $name);
                $names[(int) $id] = $given !== '' ? $given : (string) $name;
            }
        }

        return $rows->map(fn (CrmHandoffEnvelope $envelope): array => [
            'handoff_id' => (string) $envelope->handoff_id,
            'date' => $envelope->accepted_at?->copy()->timezone('America/Caracas')->format('d/m/Y') ?? '',
            'area' => CrmInboxAreas::label($envelope->area),
            'motivo' => mb_strimwidth(trim((string) $envelope->motivo), 0, 90, '…'),
            'by' => $envelope->taken_by !== null ? ($names[(int) $envelope->taken_by] ?? 'Analista') : null,
            'outcome' => CrmCaseOutcome::labelFor($envelope->outcome),
            'note' => $envelope->outcome_note !== null ? mb_strimwidth((string) $envelope->outcome_note, 0, 120, '…') : null,
            'open' => $envelope->released_at === null && $envelope->closed_at === null,
        ])->all();
    }

    /**
     * Mensajes de una conversación anterior, solo si es del mismo teléfono que el caso abierto.
     *
     * @return list<array{author: string, text: string, time: string}>|null
     */
    public static function thread(string $phone, string $handoffId): ?array
    {
        if (! preg_match('/^\d{1,18}$/', $handoffId)) {
            return null;
        }

        $envelope = CrmHandoffEnvelope::query()->where('handoff_id', $handoffId)->first(['id', 'handoff_id', 'phone']);

        if ($envelope === null || (string) $envelope->phone !== $phone) {
            return null;
        }

        return CrmHandoffMessage::query()
            ->where('handoff_id', $handoffId)
            ->orderByDesc('id')
            ->limit(self::THREAD_LIMIT)
            ->get(['id', 'body', 'received_at', 'direction', 'kind'])
            ->reverse()
            ->map(fn (CrmHandoffMessage $message): array => [
                'author' => match (true) {
                    $message->kind === 'note' => 'Nota interna',
                    $message->direction === 'out' => 'Equipo',
                    default => 'Cliente',
                },
                'text' => mb_strimwidth((string) $message->body, 0, 600, '…'),
                'time' => $message->received_at?->copy()->timezone('America/Caracas')->format('d/m H:i') ?? '',
            ])
            ->values()
            ->all();
    }
}
