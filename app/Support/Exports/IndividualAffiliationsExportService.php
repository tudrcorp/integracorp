<?php

declare(strict_types=1);

namespace App\Support\Exports;

use App\Models\Affiliate;
use App\Models\Affiliation;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;

final class IndividualAffiliationsExportService
{
    /**
     * @throws RuntimeException
     */
    public function create(): ScheduledExportResult
    {
        $startedAt = microtime(true);
        $affiliationCount = 0;
        $affiliateCount = 0;
        $rowCount = 0;

        $exportConfig = config('scheduled-exports.exports.individual_affiliations', []);
        $baseDirectory = (string) config('scheduled-exports.directory', 'scheduled-exports');
        $subDirectory = (string) ($exportConfig['directory'] ?? 'individual-affiliations');
        $directory = trim($baseDirectory.'/'.$subDirectory, '/');
        $filename = $this->buildFilename((string) ($exportConfig['filename_prefix'] ?? 'integracorp_afiliaciones_individuales'));
        $temporaryPath = storage_path('app/'.$directory.'/tmp/'.$filename);

        File::ensureDirectoryExists(dirname($temporaryPath));

        $writer = new Writer;
        $writer->openToFile($temporaryPath);
        $writer->addRow(Row::fromValues(self::headers()));

        Affiliation::query()
            ->with([
                'agent:id,name,code_agent',
                'plan:id,description',
                'coverage:id,price',
                'city:id,definition',
                'state:id,definition',
                'affiliates.plan:id,description',
                'affiliates.coverage:id,price',
                'affiliates.country:id,name',
                'affiliates.state:id,definition',
                'affiliates.city:id,definition',
            ])
            ->orderBy('id')
            ->chunkById(100, function ($affiliations) use (&$affiliationCount, &$affiliateCount, &$rowCount, $writer): void {
                foreach ($affiliations as $affiliation) {
                    /** @var Affiliation $affiliation */
                    $affiliationCount++;

                    if ($affiliation->affiliates->isEmpty()) {
                        $writer->addRow(Row::fromValues(self::mapRow($affiliation, null)));
                        $rowCount++;

                        continue;
                    }

                    foreach ($affiliation->affiliates as $affiliate) {
                        /** @var Affiliate $affiliate */
                        $affiliateCount++;
                        $writer->addRow(Row::fromValues(self::mapRow($affiliation, $affiliate)));
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
        );
    }

    public function purgeExpiredExports(): int
    {
        $exportConfig = config('scheduled-exports.exports.individual_affiliations', []);
        $baseDirectory = (string) config('scheduled-exports.directory', 'scheduled-exports');
        $subDirectory = (string) ($exportConfig['directory'] ?? 'individual-affiliations');
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
            'ID Afiliación',
            'Código Afiliación',
            'Estatus Afiliación',
            'Código Agencia',
            'Código Agente',
            'Nombre Agente',
            'Plan Afiliación',
            'Cobertura Afiliación',
            'Frecuencia de Pago',
            'Nombre Titular',
            'CI Titular',
            'Sexo Titular',
            'Fecha Nacimiento Titular',
            'Teléfono Titular',
            'Email Titular',
            'Ciudad Titular',
            'Estado Titular',
            'Fee Anual Afiliación',
            'Monto Total Afiliación',
            'Fecha Activación',
            'Fecha Vigencia',
            'Creado Por Afiliación',
            'Fecha Creación Afiliación',
            'ID Afiliado',
            'Nombre Afiliado',
            'CI Afiliado',
            'Relación Afiliado',
            'Sexo Afiliado',
            'Fecha Nacimiento Afiliado',
            'Edad Afiliado',
            'Teléfono Afiliado',
            'Email Afiliado',
            'Estatus Afiliado',
            'Dirección Afiliado',
            'País Afiliado',
            'Estado Afiliado',
            'Ciudad Afiliado',
            'Región Afiliado',
            'Plan Afiliado',
            'Cobertura Afiliado',
            'Tarifa Afiliado',
            'Monto Total Afiliado',
            'Voucher ILS',
            'Fecha Inicio ILS',
            'Fecha Fin ILS',
            'Días Restantes ILS',
            'Fecha Creación Afiliado',
        ];
    }

    /**
     * @return list<string|int|float|null>
     */
    private static function mapRow(Affiliation $affiliation, ?Affiliate $affiliate): array
    {
        return [
            $affiliation->id,
            self::stringValue($affiliation->code),
            self::stringValue($affiliation->status),
            self::stringValue($affiliation->code_agency),
            self::stringValue($affiliation->agent?->code_agent ?? $affiliation->code_agent),
            self::stringValue($affiliation->agent?->name ?? $affiliation->full_name_agent),
            self::stringValue($affiliation->plan?->description),
            self::stringValue($affiliation->coverage?->price),
            self::stringValue($affiliation->payment_frequency),
            self::stringValue($affiliation->full_name_ti),
            self::stringValue($affiliation->nro_identificacion_ti),
            self::stringValue($affiliation->sex_ti),
            self::stringValue($affiliation->birth_date_ti),
            self::stringValue($affiliation->phone_ti),
            self::stringValue($affiliation->email_ti),
            self::stringValue($affiliation->city?->definition),
            self::stringValue($affiliation->state?->definition),
            self::numericValue($affiliation->fee_anual),
            self::numericValue($affiliation->total_amount),
            self::stringValue($affiliation->activated_at),
            self::stringValue($affiliation->effective_date),
            self::stringValue($affiliation->created_by),
            self::stringValue($affiliation->created_at),
            $affiliate?->id,
            self::stringValue($affiliate?->full_name),
            self::stringValue($affiliate?->nro_identificacion),
            self::stringValue($affiliate?->relationship),
            self::stringValue($affiliate?->sex),
            self::stringValue($affiliate?->birth_date),
            self::numericValue($affiliate?->age),
            self::stringValue($affiliate?->phone),
            self::stringValue($affiliate?->email),
            self::stringValue($affiliate?->status),
            self::stringValue($affiliate?->address),
            self::stringValue($affiliate?->country?->name),
            self::stringValue($affiliate?->state?->definition),
            self::stringValue($affiliate?->city?->definition),
            self::stringValue($affiliate?->region),
            self::stringValue($affiliate?->plan?->description),
            self::stringValue($affiliate?->coverage?->price),
            self::numericValue($affiliate?->fee),
            self::numericValue($affiliate?->total_amount),
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
