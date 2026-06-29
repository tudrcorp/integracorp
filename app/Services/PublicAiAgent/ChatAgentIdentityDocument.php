<?php

declare(strict_types=1);

namespace App\Services\PublicAiAgent;

use App\Models\Agent;
use Illuminate\Support\Facades\Schema;

final class ChatAgentIdentityDocument
{
    public const KIND_CI = 'ci';

    public const KIND_RIF = 'rif';

    /**
     * @return array{kind: string, number: string, display: string}|null
     */
    public static function parse(string $input): ?array
    {
        $trimmed = mb_strtolower(trim($input));

        if ($trimmed === '') {
            return null;
        }

        if (preg_match('/^([vje])[\-\s]?(\d+)$/u', $trimmed, $matches) !== 1) {
            return null;
        }

        $prefix = $matches[1];
        $number = $matches[2];

        if ($number === '') {
            return null;
        }

        return [
            'kind' => $prefix === 'j' ? self::KIND_RIF : self::KIND_CI,
            'number' => $number,
            'display' => mb_strtoupper($prefix).'-'.$number,
        ];
    }

    /**
     * @param  array{kind: string, number: string, display?: string}  $parsed
     */
    public static function existsInAgents(array $parsed): bool
    {
        if (! Schema::hasTable('agents')) {
            return false;
        }

        $kind = (string) ($parsed['kind'] ?? '');
        $number = (string) ($parsed['number'] ?? '');

        if ($number === '') {
            return false;
        }

        if ($kind === self::KIND_RIF && Schema::hasColumn('agents', 'rif')) {
            $compact = 'J'.$number;

            return Agent::query()
                ->where(function ($query) use ($number, $compact): void {
                    $query->where('rif', $number)
                        ->orWhere('rif', 'J-'.$number)
                        ->orWhere('rif', $compact)
                        ->orWhereRaw('UPPER(REPLACE(rif, "-", "")) = ?', [$compact]);
                })
                ->exists();
        }

        if ($kind === self::KIND_CI && Schema::hasColumn('agents', 'ci')) {
            return Agent::query()->where('ci', $number)->exists();
        }

        return false;
    }

    /**
     * @param  array{kind: string, number: string}  $parsed
     */
    public static function applyToAgent(Agent $agent, array $parsed): void
    {
        $kind = (string) ($parsed['kind'] ?? '');
        $number = (string) ($parsed['number'] ?? '');

        if ($number === '') {
            return;
        }

        if ($kind === self::KIND_RIF && Schema::hasColumn('agents', 'rif')) {
            $agent->rif = $number;

            return;
        }

        if ($kind === self::KIND_CI && Schema::hasColumn('agents', 'ci')) {
            $agent->ci = $number;
        }
    }
}
