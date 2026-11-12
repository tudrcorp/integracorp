<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\User;
use App\Support\Affiliations\ActiveBilling\ActivePopulationBilling;
use App\Support\SecurityAudit;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Deja cada afiliación (individual y corporativa, ACTIVA o PRE-APROBADA) y sus avisos POR PAGAR
 * del año contra su población activa ({@see ActivePopulationBilling}). Sin --apply solo muestra.
 */
class SyncActivePopulationBillingCommand extends Command
{
    protected $signature = 'affiliations:sync-active-population
        {--apply : Escribe los cambios; sin esta opción solo muestra qué cambiaría}
        {--type=all : individual, corporate o all}
        {--code=* : Solo estas afiliaciones (código)}
        {--actor= : ID del usuario que autoriza (obligatorio con --apply)}
        {--reason= : Motivo para la traza (obligatorio con --apply)}';

    protected $description = 'Alinea totales y avisos POR PAGAR del año con la población activa de cada afiliación';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $actor = null;
        $reason = trim((string) $this->option('reason'));

        if ($apply) {
            $actor = User::query()->find((int) $this->option('actor'));
            if (! $actor instanceof User || mb_strlen($reason) < 15) {
                $this->error('Con --apply indique --actor=<id de usuario> y --reason="motivo de al menos 15 caracteres".');

                return self::FAILURE;
            }
        }

        $type = (string) $this->option('type');
        $codes = array_filter((array) $this->option('code'));
        $owners = collect();

        if (in_array($type, ['all', 'corporate'], true)) {
            $owners = $owners->concat(AffiliationCorporate::query()->whereIn('status', ['ACTIVA', 'PRE-APROBADA'])->when($codes !== [], fn ($q) => $q->whereIn('code', $codes))->orderBy('id')->get());
        }
        if (in_array($type, ['all', 'individual'], true)) {
            $owners = $owners->concat(Affiliation::query()->whereIn('status', ['ACTIVA', 'PRE-APROBADA'])->when($codes !== [], fn ($q) => $q->whereIn('code', $codes))->orderBy('id')->get());
        }

        $rows = [];
        $counts = ['differ' => 0, 'applied' => 0, 'blocked' => 0, 'collections' => 0];
        $batch = (string) Str::uuid();

        foreach ($owners as $owner) {
            try {
                $plan = ActivePopulationBilling::plan($owner);
                if ($plan['issues'] !== []) {
                    if ($plan['differs']) {
                        $counts['blocked']++;
                        $rows[] = [$owner->code, $plan['stored']['persons'].' → '.$plan['live']['persons'], '—', '—', 'REVISAR DATOS: '.Str::limit(implode(' ', $plan['issues']), 110)];
                    }

                    continue;
                }

                if ($apply && ($plan['differs'] || $plan['blocker'] !== null)) {
                    $plan = DB::transaction(fn (): array => $this->applyOne($owner, $actor, $reason, $batch));
                }
            } catch (Throwable $exception) {
                $counts['blocked']++;
                $rows[] = [$owner->code, '—', '—', '—', 'NO: '.Str::limit($exception->getMessage(), 90)];

                continue;
            }

            if (! $plan['differs'] && $plan['blocker'] === null) {
                continue;
            }

            if ($plan['blocker'] !== null && ! $apply) {
                $counts['blocked']++;
            }

            $counts['differ']++;
            $counts['collections'] += count($plan['collections']);
            if ($apply) {
                $counts['applied']++;
            }

            $rows[] = [
                $owner->code,
                $plan['stored']['persons'].' → '.$plan['live']['persons'],
                number_format($plan['stored']['fee_anual'], 2, ',', '.').' → '.number_format($plan['live']['fee_anual'], 2, ',', '.'),
                number_format($plan['stored']['total_amount'], 2, ',', '.').' → '.number_format($plan['live']['total_amount'], 2, ',', '.'),
                $plan['blocker'] ?? (count($plan['collections']).' aviso(s)'.($plan['skipped'] !== [] ? ' · '.count($plan['skipped']).' con abono' : '')),
            ];
        }

        $this->table(['Afiliación', 'Personas', 'Tarifa anual', 'Cuota', 'Avisos POR PAGAR del año'], $rows);
        $this->line('Revisadas: '.$owners->count().' · con diferencias: '.$counts['differ'].' · avisos a recalcular: '.$counts['collections'].' · bloqueadas: '.$counts['blocked']);

        if (! $apply) {
            $this->warn('Simulación: no se guardó nada. Repita con --apply --actor=<id> --reason="…" para aplicar.');

            return self::SUCCESS;
        }

        SecurityAudit::log('AUDIT_ACTIVE_POPULATION_BILLING_SYNC', 'console.affiliations.sync-active-population', ['batch' => $batch, 'counts' => $counts, 'reason' => $reason, 'codes' => $codes, 'type' => $type], $actor);
        $this->info('Aplicado a '.$counts['applied'].' afiliación(es). Lote '.$batch.'.');

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function applyOne(Model $owner, User $actor, string $reason, string $batch): array
    {
        $owner = $owner::query()->lockForUpdate()->findOrFail($owner->getKey());
        $plan = ActivePopulationBilling::sync($owner, $actor, 'Alineación con la población activa: '.$reason, $batch);

        if ($plan['differs']) {
            \App\Support\Affiliations\DateCorrection\AffiliationDateCorrector::log(
                $owner,
                \App\Support\Affiliations\DateCorrection\DateCorrectionSubject::for($owner),
                'POBLACION ACTIVA',
                mb_substr('Totales y avisos alineados con la población activa: '.$plan['live']['persons'].' pers., anual US$ '.$plan['live']['fee_anual'].', cuota US$ '.$plan['live']['total_amount'].'. Motivo: '.$reason, 0, 250),
                $actor,
            );
        }

        return $plan;
    }
}
