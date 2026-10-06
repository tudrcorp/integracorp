<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AffiliationCorporate;
use App\Models\User;
use App\Support\AffiliationCorporates\CorporatePopulationMissingImporter as Importer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Agrega a un colectivo las personas de un padrón (Excel/CSV) que faltan en
 * `affiliate_corporates`. Por defecto solo muestra qué haría; escribe con --execute.
 *
 * Ejemplo:
 *   php artisan affiliations-corporate:add-missing TDEC-COR-00054 storage/app/padron.xlsx
 *   php artisan affiliations-corporate:add-missing TDEC-COR-00054 storage/app/padron.xlsx \
 *       --execute --motivo="Padrón REPSOL 22/09/2026" --usuario=gcamacho@tudrencasa.com
 */
class AddMissingCorporateAffiliatesCommand extends Command
{
    protected $signature = 'affiliations-corporate:add-missing
        {code : Código de la afiliación corporativa (p. ej. TDEC-COR-00054)}
        {path : Ruta del padrón .xlsx o .csv (NOMBRE_RIESGO, 1ER_APELLIDO_RIESGO, 2DO_APELLIDO_RIESGO, PARENTESCO, TIP_DOCUMENTO, CODIGO_DOCUMENTO)}
        {--execute : Crear de verdad. Sin esta opción solo se muestra la vista previa}
        {--motivo= : Motivo de la carga (obligatorio con --execute; queda en la auditoría)}
        {--usuario= : Email o id del usuario responsable (obligatorio con --execute)}
        {--forzar-fila=* : Filas del Excel con posible duplicado por nombre que, revisadas, sí deben crearse}';

    protected $description = 'Agrega de forma segura a un colectivo las personas de un padrón que no están en affiliate_corporates (vista previa por defecto).';

    public function handle(): int
    {
        $affiliation = AffiliationCorporate::query()->where('code', (string) $this->argument('code'))->first();

        if ($affiliation === null) {
            $this->error('No existe la afiliación corporativa '.$this->argument('code').'.');

            return self::FAILURE;
        }

        try {
            $planRow = Importer::planRow($affiliation);
            $rows = Importer::readRows((string) $this->argument('path'));
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $forced = array_values(array_filter(array_map('intval', (array) $this->option('forzar-fila'))));
        $decisions = Importer::plan($affiliation, $rows, $forced);
        $toCreate = array_values(array_filter($decisions, fn (array $decision): bool => $decision['action'] === Importer::ACTION_CREATE));
        $before = Importer::totals($affiliation, $planRow);

        $this->info($affiliation->code.' · '.$affiliation->name_corporate.' · plan '.$planRow->plan_id.' · tarifa US$ '.number_format((float) $planRow->fee, 2, ',', '.').' por persona');
        $this->line('Filas leídas del padrón: '.count($rows));
        $this->newLine();

        $this->table(['Resultado', 'Personas'], collect($decisions)->countBy('action')->map(fn (int $count, string $action): array => [$action, $count])->values()->all());

        if ($toCreate !== []) {
            $this->line('<options=bold>Se crearían:</>');
            $this->table(['Fila', 'Nombre', 'Tipo', 'Documento', 'Nota'], array_map(fn (array $decision): array => [$decision['excel_row'], $decision['name'], $decision['document_type'], $decision['document'], $decision['detail']], $toCreate));
        }

        $review = array_values(array_filter($decisions, fn (array $decision): bool => $decision['action'] === Importer::ACTION_NAME_MATCH));

        if ($review !== []) {
            $this->warn('Para revisar (no se crean): coinciden por nombre con un afiliado de otro documento.');
            $this->table(['Fila', 'Nombre en el padrón', 'Documento', 'Afiliado parecido'], array_map(fn (array $decision): array => [$decision['excel_row'], $decision['name'], $decision['document'], $decision['match']], $review));
        }

        $count = count($toCreate);
        $fee = (float) $planRow->fee;
        $this->table(['Total', 'Hoy', 'Después'], [
            ['Población', $before['poblation'], $before['poblation'] + $count],
            ['Tarifa anual (US$)', number_format($before['fee_anual'], 2, ',', '.'), number_format($before['fee_anual'] + $fee * $count, 2, ',', '.')],
            ['Personas en la fila de plan', $before['plan_total_persons'], $before['plan_total_persons'] + $count],
        ]);

        $report = $this->writeReport($affiliation, $decisions);
        $this->line('Reporte de cada fila: '.$report);

        if (! $this->option('execute')) {
            $this->newLine();
            $this->comment('Vista previa: no se modificó nada. Para crear, repita con --execute --motivo="…" --usuario=…');

            return self::SUCCESS;
        }

        if ($count === 0) {
            $this->info('No hay personas que crear.');

            return self::SUCCESS;
        }

        $reason = trim((string) $this->option('motivo'));
        $user = $this->resolveUser((string) $this->option('usuario'));

        if ($reason === '' || $user === null) {
            $this->error('Con --execute son obligatorios --motivo y --usuario (email o id de un usuario existente).');

            return self::FAILURE;
        }

        if (! $this->confirm('¿Crear '.$count.' afiliados en '.$affiliation->code.' a nombre de '.$user->name.'?')) {
            $this->warn('Cancelado: no se modificó nada.');

            return self::FAILURE;
        }

        try {
            $result = Importer::execute($affiliation, $decisions, $user, $reason);
        } catch (Throwable $exception) {
            $this->error('No se creó nada: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Creados '.count($result['created']).' afiliados (ids '.min($result['created']).'–'.max($result['created']).').');
        $this->table(['Total', 'Antes', 'Después'], [
            ['Población', $result['before']['poblation'], $result['after']['poblation']],
            ['Tarifa anual (US$)', $result['before']['fee_anual'], $result['after']['fee_anual']],
            ['Monto por cuota (US$)', $result['before']['total_amount'], $result['after']['total_amount']],
            ['Personas en la fila de plan', $result['before']['plan_total_persons'], $result['after']['plan_total_persons']],
        ]);
        $this->comment('Pendiente a mano: fechas de nacimiento, vinculación con telemedicina, certificado/carnets y cobranza del período.');

        return self::SUCCESS;
    }

    private function resolveUser(string $value): ?User
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        return ctype_digit($value)
            ? User::query()->find((int) $value)
            : User::query()->where('email', $value)->first();
    }

    /**
     * @param  list<array<string, mixed>>  $decisions
     */
    private function writeReport(AffiliationCorporate $affiliation, array $decisions): string
    {
        $path = 'reportes/padron-'.$affiliation->code.'-'.now()->format('Ymd-His').'.csv';
        $handle = fopen('php://temp', 'w+');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, ['Fila', 'Nombre', 'Tipo doc.', 'Documento', 'Resultado', 'Detalle', 'Afiliado existente']);

        foreach ($decisions as $decision) {
            fputcsv($handle, [$decision['excel_row'], $decision['name'], $decision['document_type'], $decision['document'], $decision['action'], $decision['detail'], $decision['match'] ?? '']);
        }

        rewind($handle);
        Storage::disk('local')->put($path, (string) stream_get_contents($handle));
        fclose($handle);

        return Storage::disk('local')->path($path);
    }
}
