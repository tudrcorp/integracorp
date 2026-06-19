<?php

declare(strict_types=1);

namespace App\Support\Audit;

use App\Filament\Business\Resources\AffiliationCorporates\Pages\ViewAffiliationCorporate;
use App\Filament\Business\Resources\Affiliations\Pages\ViewAffiliation;
use App\Filament\Business\Resources\Agencies\Pages\ViewAgency;
use App\Filament\Business\Resources\Agents\Pages\ViewAgent;
use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\Agency;
use App\Models\Agent;
use Illuminate\Database\Eloquent\Model;

final class AuditCompletionReport
{
    /**
     * Conteo por categoría: total de registros, auditados por completo y pendientes.
     *
     * @return array{
     *     agencies:array{label:string, total:int, audited:int, pending:int},
     *     agents:array{label:string, total:int, audited:int, pending:int},
     *     individual_affiliations:array{label:string, total:int, audited:int, pending:int},
     *     corporate_affiliations:array{label:string, total:int, audited:int, pending:int},
     *     totals:array{total:int, audited:int, pending:int}
     * }
     */
    public static function counts(): array
    {
        $agencies = self::countsFor('Agencias de corretaje', Agency::class, array_keys(ViewAgency::auditItemsCatalog()));
        $agents = self::countsFor('Agentes de corretaje', Agent::class, array_keys(ViewAgent::auditItemsCatalog()));
        $individual = self::countsFor('Afiliaciones individuales', Affiliation::class, array_keys(ViewAffiliation::auditItemsCatalog()));
        $corporate = self::countsFor('Afiliaciones corporativas', AffiliationCorporate::class, array_keys(ViewAffiliationCorporate::auditItemsCatalog()));

        return [
            'agencies' => $agencies,
            'agents' => $agents,
            'individual_affiliations' => $individual,
            'corporate_affiliations' => $corporate,
            'totals' => [
                'total' => $agencies['total'] + $agents['total'] + $individual['total'] + $corporate['total'],
                'audited' => $agencies['audited'] + $agents['audited'] + $individual['audited'] + $corporate['audited'],
                'pending' => $agencies['pending'] + $agents['pending'] + $individual['pending'] + $corporate['pending'],
            ],
        ];
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<int, string>  $catalogKeys
     * @return array{label:string, total:int, audited:int, pending:int}
     */
    public static function countsFor(string $label, string $modelClass, array $catalogKeys): array
    {
        $total = (int) $modelClass::query()->count();
        $audited = self::countFullyAudited($modelClass, $catalogKeys);

        return [
            'label' => $label,
            'total' => $total,
            'audited' => $audited,
            'pending' => max($total - $audited, 0),
        ];
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<int, string>  $catalogKeys
     */
    public static function countFullyAudited(string $modelClass, array $catalogKeys): int
    {
        if ($catalogKeys === []) {
            return 0;
        }

        return $modelClass::query()
            ->whereNotNull('audit_items')
            ->get(['id', 'audit_items'])
            ->filter(fn (Model $record): bool => self::isFullyAudited($record->getAttribute('audit_items'), $catalogKeys))
            ->count();
    }

    /**
     * Una auditoría es completa cuando contiene todas las claves del catálogo.
     *
     * @param  array<int, string>  $catalogKeys
     */
    public static function isFullyAudited(mixed $items, array $catalogKeys): bool
    {
        if ($catalogKeys === [] || ! is_array($items) || $items === []) {
            return false;
        }

        $audited = array_values(array_filter($items, static fn (mixed $key): bool => is_string($key)));

        return array_diff($catalogKeys, $audited) === [];
    }

    /**
     * Cuerpo del mensaje de WhatsApp, explicativo para el analista.
     *
     * @param  array{
     *     agencies:array{label:string, total:int, audited:int, pending:int},
     *     agents:array{label:string, total:int, audited:int, pending:int},
     *     individual_affiliations:array{label:string, total:int, audited:int, pending:int},
     *     corporate_affiliations:array{label:string, total:int, audited:int, pending:int},
     *     totals:array{total:int, audited:int, pending:int}
     * }  $counts
     */
    public static function whatsappBody(array $counts): string
    {
        $date = now()->timezone((string) config('app.timezone'))->format('d/m/Y H:i');

        $categories = [
            $counts['agencies'],
            $counts['agents'],
            $counts['individual_affiliations'],
            $counts['corporate_affiliations'],
        ];

        $lines = [];
        foreach ($categories as $category) {
            $lines[] = "*{$category['label']}*";
            $lines[] = "Total: {$category['total']}  |  Auditados: {$category['audited']}  |  Pendientes: {$category['pending']}";
            $lines[] = '';
        }
        $detail = rtrim(implode(PHP_EOL, $lines));

        $totals = $counts['totals'];

        return <<<TEXT
        *INTEGRACORP · Reporte diario de auditorías*
        Fecha y hora: {$date}

        Resumen del avance de auditorías. «Auditados» son los registros con la totalidad de sus puntos de control verificados; «Pendientes» son los que aún tienen puntos por auditar.

        {$detail}

        ──────────────
        *TOTAL GENERAL*
        Registros: {$totals['total']}  |  Auditados: {$totals['audited']}  |  Pendientes: {$totals['pending']}

        Nota: un registro se cuenta como auditado únicamente cuando todos sus puntos de control fueron verificados.
        TEXT;
    }
}
