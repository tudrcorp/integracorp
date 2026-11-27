<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Affiliation;
use App\Support\Affiliations\AffiliateAmountsBackfill;
use Illuminate\Console\Command;
use Throwable;

class FillAffiliateAmountsCommand extends Command
{
    protected $signature = 'affiliations:fill-affiliate-amounts
                            {codes?* : Códigos de afiliación (ej. TDEC-IND-000463 TDEC-IND-000464)}
                            {--missing : Revisar todas las afiliaciones individuales con afiliados sin tarifa}
                            {--execute : Sin este flag solo muestra la vista previa}';

    protected $description = 'Completa plan, cobertura, rango y tarifa de los afiliados sin montos (mismo cálculo que «Asociar montos»). No cambia la tarifa de la afiliación. Por defecto solo hace dry-run.';

    public function handle(): int
    {
        $codes = array_values(array_unique(array_filter(array_map(
            static fn (mixed $code): string => mb_strtoupper(trim((string) $code)),
            (array) $this->argument('codes'),
        ))));

        if ($codes === [] && ! $this->option('missing')) {
            $this->error('Indique uno o más códigos de afiliación, o use --missing para revisar todas.');

            return self::FAILURE;
        }

        $backfill = AffiliateAmountsBackfill::make();

        if ($codes !== []) {
            $affiliations = Affiliation::query()->whereIn('code', $codes)->orderBy('id')->get();
            $missing = array_diff($codes, $affiliations->pluck('code')->map(fn (mixed $code): string => mb_strtoupper((string) $code))->all());

            if ($missing !== []) {
                $this->error('No se encontraron: '.implode(', ', $missing));

                return self::FAILURE;
            }
        } else {
            $affiliations = $backfill->affiliationsWithMissingAmounts();

            if ($affiliations->isEmpty()) {
                $this->info('No hay afiliaciones individuales con afiliados sin tarifa.');

                return self::SUCCESS;
            }
        }

        $execute = (bool) $this->option('execute');
        $this->line($execute ? '<options=bold>Modo: EJECUTAR</>' : '<options=bold>Modo: vista previa (no se guarda nada). Use --execute para aplicar.</>');

        $summary = ['done' => 0, 'ready' => 0, 'skipped' => 0];

        foreach ($affiliations as $affiliation) {
            try {
                $result = $execute
                    ? $backfill->apply($affiliation, 'SISTEMA (comando affiliations:fill-affiliate-amounts)')
                    : $backfill->plan($affiliation);
            } catch (Throwable $exception) {
                $this->newLine();
                $this->error($affiliation->code.': error, no se guardó nada. '.$exception->getMessage());
                $summary['skipped']++;

                continue;
            }

            $this->report($affiliation, $result);

            if ($result['status'] === AffiliateAmountsBackfill::STATUS_READY) {
                $execute ? $summary['done']++ : $summary['ready']++;
            } else {
                $summary['skipped']++;
            }
        }

        $this->newLine();
        $this->info($execute
            ? 'Completadas: '.$summary['done'].' · Sin cambios: '.$summary['skipped']
            : 'Listas para completar: '.$summary['ready'].' · Requieren revisión o sin pendientes: '.$summary['skipped']);

        return self::SUCCESS;
    }

    /**
     * @param  array{status: string, message: string, lines: list<array<string, mixed>>, affiliation_fee: float, affiliates_sum: float|null}  $result
     */
    private function report(Affiliation $affiliation, array $result): void
    {
        $this->newLine();
        $this->line('<options=bold>'.$affiliation->code.'</> · '.$affiliation->status.' · '.$affiliation->payment_frequency
            .' · tarifa de la afiliación US$ '.number_format($result['affiliation_fee'], 2, ',', '.'));

        if ($result['lines'] !== []) {
            $this->table(
                ['Afiliado', 'Edad', 'Rango', 'Cobertura', 'Tarifa anual', 'Cuota'],
                array_map(fn (array $line): array => [
                    $line['name'],
                    $line['age'] ?? '—',
                    $line['age_range_id'] ?? '—',
                    $line['coverage_id'] ?? '—',
                    $line['annual_fee'] === null ? 'SIN TARIFA' : 'US$ '.number_format((float) $line['annual_fee'], 2, ',', '.'),
                    $line['period_amount'] === null ? '—' : 'US$ '.number_format((float) $line['period_amount'], 2, ',', '.'),
                ], $result['lines']),
            );
        }

        $message = '['.$result['status'].'] '.$result['message'];

        match ($result['status']) {
            AffiliateAmountsBackfill::STATUS_READY => $this->info($message),
            AffiliateAmountsBackfill::STATUS_NOTHING => $this->line($message),
            default => $this->warn($message),
        };
    }
}
