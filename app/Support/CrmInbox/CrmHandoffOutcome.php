<?php

declare(strict_types=1);

namespace App\Support\CrmInbox;

use App\Enums\CrmCaseOutcome;
use Illuminate\Support\Facades\Schema;

/**
 * Valida y prepara el motivo con que termina un caso.
 */
final class CrmHandoffOutcome
{
    private static ?bool $ready = null;

    /**
     * @return array{ok: true, outcome: CrmCaseOutcome, note: ?string}|array{ok: false, reason: string}
     */
    public static function parse(string $value, string $note): array
    {
        $outcome = CrmCaseOutcome::tryFrom(trim($value));
        $note = trim(preg_replace('/\s+/u', ' ', $note) ?? '');

        if ($outcome === null) {
            return ['ok' => false, 'reason' => 'outcome'];
        }

        if ($outcome->needsNote() && $note === '') {
            return ['ok' => false, 'reason' => 'outcome_note'];
        }

        return ['ok' => true, 'outcome' => $outcome, 'note' => $note !== '' ? mb_substr($note, 0, 255) : null];
    }

    /**
     * Columnas a escribir junto con el cierre. Vacío si no hay motivo o la base aún no tiene las columnas.
     *
     * @return array<string, mixed>
     */
    public static function columns(?CrmCaseOutcome $outcome, ?string $note, ?int $userId): array
    {
        if ($outcome === null || ! (self::$ready ??= Schema::hasColumn('crm_handoff_envelopes', 'outcome'))) {
            return [];
        }

        return [
            'outcome' => $outcome->value,
            'outcome_note' => $note,
            'outcome_by' => $userId,
            'outcome_at' => now(),
            'follow_up_at' => null,
        ];
    }

    public static function forgetSchema(): void
    {
        self::$ready = null;
    }
}
