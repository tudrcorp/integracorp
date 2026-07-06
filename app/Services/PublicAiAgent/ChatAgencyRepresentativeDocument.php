<?php

declare(strict_types=1);

namespace App\Services\PublicAiAgent;

use App\Models\Agency;
use Illuminate\Support\Facades\Schema;

final class ChatAgencyRepresentativeDocument
{
    /**
     * @return array{kind: string, number: string, display: string}|null
     */
    public static function parse(string $input): ?array
    {
        return ChatAgentIdentityDocument::parse($input);
    }

    /**
     * @param  array{kind: string, number: string}  $parsed
     */
    public static function existsInAgencies(array $parsed): bool
    {
        if (! Schema::hasTable('agencies')) {
            return false;
        }

        $kind = (string) ($parsed['kind'] ?? '');
        $number = (string) ($parsed['number'] ?? '');

        if ($number === '') {
            return false;
        }

        if ($kind === ChatAgentIdentityDocument::KIND_RIF && Schema::hasColumn('agencies', 'rif')) {
            $compact = 'J'.$number;

            return Agency::query()
                ->where(function ($query) use ($number, $compact): void {
                    $query->where('rif', $number)
                        ->orWhere('rif', 'J-'.$number)
                        ->orWhere('rif', $compact)
                        ->orWhereRaw('UPPER(REPLACE(rif, "-", "")) = ?', [$compact]);
                })
                ->exists();
        }

        if ($kind === ChatAgentIdentityDocument::KIND_CI && Schema::hasColumn('agencies', 'ci_responsable')) {
            return Agency::query()->where('ci_responsable', $number)->exists();
        }

        return false;
    }

    public static function existsByRawInput(string $taxId): bool
    {
        $parsed = self::parse($taxId);

        if ($parsed !== null) {
            return self::existsInAgencies($parsed);
        }

        $normalized = mb_strtoupper(trim($taxId));

        if ($normalized === '') {
            return false;
        }

        if (preg_match('/^[JVEGPRC]\-?\d/u', $normalized) === 1 && Schema::hasColumn('agencies', 'rif')) {
            $compact = str_replace('-', '', $normalized);

            return Agency::query()
                ->where(function ($query) use ($normalized, $compact): void {
                    $query->whereRaw('UPPER(TRIM(rif)) = ?', [$normalized])
                        ->orWhereRaw('UPPER(REPLACE(rif, "-", "")) = ?', [$compact]);
                })
                ->exists();
        }

        if (! Schema::hasColumn('agencies', 'ci_responsable')) {
            return false;
        }

        $digits = preg_replace('/\D+/', '', $normalized) ?? '';

        if ($digits === '') {
            return false;
        }

        return Agency::query()->where('ci_responsable', $digits)->exists();
    }

    /**
     * @param  array{kind: string, number: string}  $parsed
     */
    public static function applyToAgency(Agency $agency, array $parsed): void
    {
        $kind = (string) ($parsed['kind'] ?? '');
        $number = (string) ($parsed['number'] ?? '');

        if ($number === '') {
            return;
        }

        if ($kind === ChatAgentIdentityDocument::KIND_RIF && Schema::hasColumn('agencies', 'rif')) {
            $agency->rif = $number;

            return;
        }

        if ($kind === ChatAgentIdentityDocument::KIND_CI && Schema::hasColumn('agencies', 'ci_responsable')) {
            $agency->ci_responsable = $number;
        }
    }

    public static function applyRawInputToAgency(Agency $agency, string $taxId): void
    {
        $parsed = self::parse($taxId);

        if ($parsed !== null) {
            self::applyToAgency($agency, $parsed);

            return;
        }

        $normalized = mb_strtoupper(trim($taxId));

        if ($normalized === '') {
            return;
        }

        if (preg_match('/^[JVEGPRC]\-?\d/u', $normalized) === 1) {
            $number = preg_replace('/^[JVEGPRC]\-?/u', '', $normalized) ?? $normalized;

            if (preg_match('/^J/u', $normalized) === 1 && Schema::hasColumn('agencies', 'rif')) {
                $agency->rif = $number;

                return;
            }

            if (preg_match('/^[VE]/u', $normalized) === 1 && Schema::hasColumn('agencies', 'ci_responsable')) {
                $agency->ci_responsable = $number;

                return;
            }

            if (Schema::hasColumn('agencies', 'rif')) {
                $agency->rif = $normalized;
            }

            return;
        }

        if (Schema::hasColumn('agencies', 'ci_responsable')) {
            $agency->ci_responsable = preg_replace('/\D+/', '', $normalized) ?? $normalized;
        }
    }
}
