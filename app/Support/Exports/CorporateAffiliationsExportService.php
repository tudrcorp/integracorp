<?php

declare(strict_types=1);

namespace App\Support\Exports;

use App\Models\AffiliateCorporate;
use App\Models\AffiliationCorporate;
use App\Models\AfilliationCorporatePlan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;

final class CorporateAffiliationsExportService
{
    /**
     * @throws RuntimeException
     */
    public function create(): ScheduledExportResult
    {
        $startedAt = microtime(true);
        $affiliationCount = 0;
        $affiliateCount = 0;
        $planCount = 0;
        $rowCount = 0;

        $exportConfig = config('scheduled-exports.exports.corporate_affiliations', []);
        $baseDirectory = (string) config('scheduled-exports.directory', 'scheduled-exports');
        $subDirectory = (string) ($exportConfig['directory'] ?? 'corporate-affiliations');
        $directory = trim($baseDirectory.'/'.$subDirectory, '/');
        $filename = $this->buildFilename((string) ($exportConfig['filename_prefix'] ?? 'integracorp_afiliaciones_corporativas'));
        $temporaryPath = storage_path('app/'.$directory.'/tmp/'.$filename);

        File::ensureDirectoryExists(dirname($temporaryPath));

        $writer = new Writer;
        $writer->openToFile($temporaryPath);
        $writer->addRow(Row::fromValues(self::headers()));

        AffiliationCorporate::query()
            ->with([
                'agent:id,name,code_agent',
                'city:id,definition',
                'state:id,definition',
                'country:id,name',
                'region:id,definition',
                'affiliationCorporatePlans.plan:id,description',
                'affiliationCorporatePlans.coverage:id,price',
                'affiliationCorporatePlans.ageRange:id,range',
                'corporateAffiliates.plan:id,description',
                'corporateAffiliates.coverage:id,price',
            ])
            ->orderBy('id')
            ->chunkById(100, function ($affiliations) use (&$affiliationCount, &$affiliateCount, &$planCount, &$rowCount, $writer): void {
                foreach ($affiliations as $affiliation) {
                    /** @var AffiliationCorporate $affiliation */
                    $affiliationCount++;
                    $affiliates = $affiliation->corporateAffiliates;
                    $plans = $affiliation->affiliationCorporatePlans;

                    if ($affiliates->isEmpty() && $plans->isEmpty()) {
                        $writer->addRow(Row::fromValues(self::mapRow($affiliation, null, null)));
                        $rowCount++;

                        continue;
                    }

                    if ($affiliates->isEmpty()) {
                        foreach ($plans as $plan) {
                            /** @var AfilliationCorporatePlan $plan */
                            $planCount++;
                            $writer->addRow(Row::fromValues(self::mapRow($affiliation, $plan, null)));
                            $rowCount++;
                        }

                        continue;
                    }

                    $exportedPlanIds = [];

                    foreach ($affiliates as $affiliate) {
                        /** @var AffiliateCorporate $affiliate */
                        $affiliateCount++;
                        $matchedPlan = $plans->firstWhere('plan_id', $affiliate->plan_id);

                        if ($matchedPlan !== null) {
                            $exportedPlanIds[$matchedPlan->id] = true;
                        }

                        $writer->addRow(Row::fromValues(self::mapRow($affiliation, $matchedPlan, $affiliate)));
                        $rowCount++;
                    }

                    foreach ($plans as $plan) {
                        if (isset($exportedPlanIds[$plan->id])) {
                            continue;
                        }

                        $planCount++;
                        $writer->addRow(Row::fromValues(self::mapRow($affiliation, $plan, null)));
                        $rowCount++;
                    }
                }
            });

        $writer->close();

        if (! is_file($temporaryPath) || filesize($temporaryPath) === 0) {
            throw new RuntimeException('El archivo Excel no se generó o quedó vacío.');
        }

        $bytes = (int) filesize($temporaryPath);
        Storage::disk('public')->makeDirectory($directory);
        Storage::disk('public')->put($directory.'/'.$filename, file_get_contents($temporaryPath) ?: '');

        $publicRelativePath = $directory.'/'.$filename;
        $absolutePath = Storage::disk('public')->path($publicRelativePath);

        File::delete($temporaryPath);

        return new ScheduledExportResult(
            filename: $filename,
            absolutePath: $absolutePath,
            publicRelativePath: $publicRelativePath,
            bytes: $bytes,
            durationSeconds: microtime(true) - $startedAt,
            affiliationCount: $affiliationCount,
            affiliateCount: $affiliateCount,
            rowCount: $rowCount,
            planCount: $planCount,
        );
    }

    public function purgeExpiredExports(): int
    {
        $exportConfig = config('scheduled-exports.exports.corporate_affiliations', []);
        $baseDirectory = (string) config('scheduled-exports.directory', 'scheduled-exports');
        $subDirectory = (string) ($exportConfig['directory'] ?? 'corporate-affiliations');
        $directory = trim($baseDirectory.'/'.$subDirectory, '/');
        $retentionDays = max(1, (int) config('scheduled-exports.retention_days', 7));
        $threshold = now()->subDays($retentionDays)->getTimestamp();
        $deleted = 0;

        if (! Storage::disk('public')->exists($directory)) {
            return 0;
        }

        foreach (Storage::disk('public')->files($directory) as $file) {
            if (! str_ends_with(strtolower($file), '.xlsx')) {
                continue;
            }

            if (Storage::disk('public')->lastModified($file) >= $threshold) {
                continue;
            }

            Storage::disk('public')->delete($file);
            $deleted++;
        }

        return $deleted;
    }

    /**
     * @return list<string>
     */
    public static function headers(): array
    {
        return [
            'ID Afiliación Corporativa',
            'Código Afiliación',
            'Estatus Afiliación',
            'Código Agencia',
            'Código Agente',
            'Nombre Agente',
            'Nombre Corporación',
            'RIF',
            'Dirección Corporación',
            'Ciudad Corporación',
            'Estado Corporación',
            'País Corporación',
            'Región Corporación',
            'Teléfono Corporación',
            'Email Corporación',
            'Nombre Contacto',
            'CI Contacto',
            'Teléfono Contacto',
            'Email Contacto',
            'Frecuencia Pago Afiliación',
            'Fee Anual Afiliación',
            'Monto Total Afiliación',
            'Población',
            'Tipo Afiliación',
            'Fecha Activación',
            'Fecha Vigencia',
            'Creado Por Afiliación',
            'Fecha Creación Afiliación',
            'ID Línea Plan',
            'Plan Contrato',
            'Cobertura Plan Contrato',
            'Rango Edad Plan',
            'Estatus Línea Plan',
            'Tarifa Línea Plan',
            'Subtotal Anual Plan',
            'Subtotal Trimestral Plan',
            'Subtotal Semestral Plan',
            'Subtotal Mensual Plan',
            'Total Personas Plan',
            'Frecuencia Pago Plan',
            'ID Afiliado Corporativo',
            'Nombres Afiliado',
            'Apellidos Afiliado',
            'CI Afiliado',
            'Fecha Nacimiento Afiliado',
            'Edad Afiliado',
            'Sexo Afiliado',
            'Teléfono Afiliado',
            'Email Afiliado',
            'Cargo Afiliado',
            'Dirección Afiliado',
            'Estatus Afiliado',
            'Plan Afiliado',
            'Cobertura Afiliado',
            'Tarifa Afiliado',
            'Subtotal Anual Afiliado',
            'Frecuencia Pago Afiliado',
            'Contacto Emergencia',
            'Teléfono Emergencia',
            'Voucher ILS Afiliado',
            'Fecha Inicio ILS',
            'Fecha Fin ILS',
            'Días Restantes ILS',
            'Fecha Creación Afiliado',
        ];
    }

    /**
     * @return list<string|int|float|null>
     */
    private static function mapRow(
        AffiliationCorporate $affiliation,
        ?AfilliationCorporatePlan $plan,
        ?AffiliateCorporate $affiliate,
    ): array {
        return [
            $affiliation->id,
            self::stringValue($affiliation->code),
            self::stringValue($affiliation->status),
            self::stringValue($affiliation->code_agency),
            self::stringValue($affiliation->agent?->code_agent),
            self::stringValue($affiliation->agent?->name),
            self::stringValue($affiliation->name_corporate),
            self::stringValue($affiliation->rif),
            self::stringValue($affiliation->address),
            self::stringValue($affiliation->city?->definition),
            self::stringValue($affiliation->state?->definition),
            self::stringValue($affiliation->country?->name),
            self::stringValue($affiliation->region?->definition),
            self::stringValue($affiliation->phone),
            self::stringValue($affiliation->email),
            self::stringValue($affiliation->full_name_contact),
            self::stringValue($affiliation->nro_identificacion_contact),
            self::stringValue($affiliation->phone_contact),
            self::stringValue($affiliation->email_contact),
            self::stringValue($affiliation->payment_frequency),
            self::numericValue($affiliation->fee_anual),
            self::numericValue($affiliation->total_amount),
            self::stringValue($affiliation->poblation),
            self::stringValue($affiliation->type),
            self::stringValue($affiliation->activated_at),
            self::stringValue($affiliation->effective_date),
            self::stringValue($affiliation->created_by),
            self::stringValue($affiliation->created_at),
            $plan?->id,
            self::stringValue($plan?->plan?->description),
            self::stringValue($plan?->coverage?->price),
            self::stringValue($plan?->ageRange?->range),
            self::stringValue($plan?->status),
            self::numericValue($plan?->fee),
            self::numericValue($plan?->subtotal_anual),
            self::numericValue($plan?->subtotal_quarterly),
            self::numericValue($plan?->subtotal_biannual),
            self::numericValue($plan?->subtotal_monthly),
            self::numericValue($plan?->total_persons),
            self::stringValue($plan?->payment_frequency),
            $affiliate?->id,
            self::stringValue($affiliate?->first_name),
            self::stringValue($affiliate?->last_name),
            self::stringValue($affiliate?->nro_identificacion),
            self::stringValue($affiliate?->birth_date),
            self::numericValue($affiliate?->age),
            self::stringValue($affiliate?->sex),
            self::stringValue($affiliate?->phone),
            self::stringValue($affiliate?->email),
            self::stringValue($affiliate?->position_company),
            self::stringValue($affiliate?->address),
            self::stringValue($affiliate?->status),
            self::stringValue($affiliate?->plan?->description),
            self::stringValue($affiliate?->coverage?->price),
            self::numericValue($affiliate?->fee),
            self::numericValue($affiliate?->subtotal_anual),
            self::stringValue($affiliate?->payment_frequency),
            self::stringValue($affiliate?->full_name_emergency),
            self::stringValue($affiliate?->phone_emergency),
            self::stringValue($affiliate?->vaucherIls),
            self::stringValue($affiliate?->dateInit),
            self::stringValue($affiliate?->dateEnd),
            self::numericValue($affiliate?->numberDays),
            self::stringValue($affiliate?->created_at),
        ];
    }

    private static function stringValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    private static function numericValue(mixed $value): int|float|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return str_contains((string) $value, '.') ? (float) $value : (int) $value;
        }

        return null;
    }

    private function buildFilename(string $prefix): string
    {
        return sprintf('%s_%s.xlsx', $prefix, now()->format('Y-m-d_His'));
    }
}
