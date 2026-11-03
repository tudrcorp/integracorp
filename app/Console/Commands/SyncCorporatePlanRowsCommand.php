<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AffiliationCorporate;
use App\Support\AffiliationCorporates\CorporatePlanRowSync;
use App\Support\SecurityAudit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Realinea las filas del plan de una afiliación corporativa con la población que se
 * cobra (tarifa vigente de cada fila × personas). Sin --apply solo muestra el cambio.
 *
 * Pensado para reparar afiliaciones renovadas antes de que la renovación sincronizara
 * las filas (el aviso mostraba la línea del plan distinta al Monto Total).
 */
class SyncCorporatePlanRowsCommand extends Command
{
    protected $signature = 'affiliations:sync-corporate-plan-rows
        {code : Código de la afiliación corporativa (p. ej. TDEC-COR-00010)}
        {--apply : Escribe los cambios; sin esta opción solo simula}
        {--actor= : Nombre de quien autoriza, para la traza}';

    protected $description = 'Recalcula personas y subtotales de las filas de plan de una afiliación corporativa';

    public function handle(): int
    {
        $code = trim((string) $this->argument('code'));
        $affiliation = AffiliationCorporate::query()->where('code', $code)->first();

        if ($affiliation === null) {
            $this->error("No existe la afiliación corporativa {$code}.");

            return self::FAILURE;
        }

        $preview = CorporatePlanRowSync::sync($affiliation, persist: false);

        $this->line("{$affiliation->code} · {$affiliation->name_corporate} · frecuencia {$affiliation->payment_frequency} · total anual de la afiliación {$affiliation->fee_anual}");
        $this->table(['Fila', 'Plan', 'Cobertura', 'Rango', 'Tarifa antes → después', 'Personas antes → después', 'Anual antes → después'], array_map(fn (array $row): array => [
            $row['id'] ?? 'nueva',
            $row['plan_id'],
            $row['coverage_id'] ?? '—',
            $row['age_range_id'] ?? '—',
            $row['before']['fee'].' → '.$row['after']['fee'],
            $row['before']['persons'].' → '.$row['after']['persons'],
            $row['before']['annual'].' → '.$row['after']['annual'],
        ], $preview['rows']));

        if ($preview['unmatched'] !== []) {
            $this->warn('Afiliados sin fila de plan que les corresponda (no se cuentan): '.implode(', ', $preview['unmatched']));
        }

        $rowsAnnual = round(array_sum(array_map(fn (array $row): float => $row['after']['annual'], $preview['rows'])), 2);

        if (abs($rowsAnnual - (float) $affiliation->fee_anual) >= 0.01) {
            $this->warn("Tras el ajuste las filas suman {$rowsAnnual} y la afiliación {$affiliation->fee_anual}: la diferencia puede ser de upgrades o de afiliados sin fila. Revísela antes de aplicar.");
        }

        if ($preview['updated'] === 0 && $preview['created'] === 0) {
            $this->info('Las filas ya están alineadas. No se cambia nada.');

            return self::SUCCESS;
        }

        if (! $this->option('apply')) {
            $this->warn('Simulación: no se guardó nada. Repita con --apply para aplicar.');

            return self::SUCCESS;
        }

        $actor = trim((string) $this->option('actor'));

        if ($actor === '') {
            $this->error('Indique --actor con el nombre de quien autoriza el cambio.');

            return self::FAILURE;
        }

        $result = DB::transaction(fn (): array => CorporatePlanRowSync::sync($affiliation, actor: $actor));

        SecurityAudit::log('AUDIT_CORPORATE_PLAN_ROWS_SYNCED', 'console.affiliations.sync-corporate-plan-rows', [
            'affiliation_corporate_id' => $affiliation->getKey(),
            'affiliation_code' => $affiliation->code,
            'actor' => $actor,
            'rows' => $result['rows'],
            'unmatched' => $result['unmatched'],
        ]);

        $this->info("Aplicado: {$result['updated']} fila(s) actualizada(s), {$result['created']} creada(s).");

        return self::SUCCESS;
    }
}
