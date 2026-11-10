<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\OperationMedicationInvoice;
use App\Models\TelemedicineListLaboratory;
use App\Models\TelemedicineListSpecialist;
use App\Models\TelemedicineListStudy;
use App\Models\TelemedicinePatientLab;
use App\Models\TelemedicinePatientSpecialty;
use App\Models\TelemedicinePatientStudy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Catálogos de laboratorios, estudios y especialistas, para facturar al paciente lo NO CUBIERTO.
 *
 * El ítem recetado guarda el nombre como texto (no un id del catálogo): la fila del catálogo se
 * busca por nombre exacto, sin distinguir mayúsculas ni espacios. El 06/11/2026 coincidían el
 * 100 % de los laboratorios y estudios y 693 de 698 especialistas; el analista puede elegir otra
 * fila si no hay coincidencia. Precio = costo × (1 + % / 100), con costo y % de la propia fila.
 */
final class ClinicalServiceCatalog
{
    public const KIND_LAB = 'lab';

    public const KIND_STUDY = 'study';

    public const KIND_SPECIALTY = 'specialty';

    public const MAX_PROFIT_PERCENTAGE = 1000;

    /**
     * @return array<string, array{catalog: class-string<Model>, item: class-string<Model>, name_column: string, invoice_type: string, label: string, plural: string}>
     */
    public static function kinds(): array
    {
        return [
            self::KIND_LAB => [
                'catalog' => TelemedicineListLaboratory::class,
                'item' => TelemedicinePatientLab::class,
                'name_column' => 'laboratory',
                'invoice_type' => OperationMedicationInvoice::TYPE_LABORATORIES,
                'label' => 'Laboratorio',
                'plural' => 'Laboratorios',
            ],
            self::KIND_STUDY => [
                'catalog' => TelemedicineListStudy::class,
                'item' => TelemedicinePatientStudy::class,
                'name_column' => 'study',
                'invoice_type' => OperationMedicationInvoice::TYPE_IMAGING,
                'label' => 'Estudio',
                'plural' => 'Estudios de imagen',
            ],
            self::KIND_SPECIALTY => [
                'catalog' => TelemedicineListSpecialist::class,
                'item' => TelemedicinePatientSpecialty::class,
                'name_column' => 'specialty',
                'invoice_type' => OperationMedicationInvoice::TYPE_SPECIALIST,
                'label' => 'Especialista',
                'plural' => 'Consultas con especialista',
            ],
        ];
    }

    public static function isServiceKind(?string $kind): bool
    {
        return $kind !== null && array_key_exists($kind, self::kinds());
    }

    /**
     * «lab:12» → «lab». Medicamentos devuelven «medication».
     */
    public static function kindFromKey(string $key): ?string
    {
        if (! str_contains($key, ':')) {
            return null;
        }

        $kind = explode(':', $key, 2)[0];

        return $kind === 'medication' || self::isServiceKind($kind) ? $kind : null;
    }

    public static function idFromKey(string $key): int
    {
        return str_contains($key, ':') ? max(0, (int) explode(':', $key, 2)[1]) : 0;
    }

    /**
     * @return array{catalog: class-string<Model>, item: class-string<Model>, name_column: string, invoice_type: string, label: string, plural: string}
     */
    public static function definition(string $kind): array
    {
        return self::kinds()[$kind];
    }

    /**
     * Fila del catálogo con el mismo nombre que el ítem recetado.
     */
    public static function matchId(string $kind, ?string $name): ?int
    {
        $name = mb_strtoupper(trim((string) $name));

        if ($name === '' || ! self::isServiceKind($kind)) {
            return null;
        }

        $id = self::definition($kind)['catalog']::query()
            ->whereRaw('UPPER(TRIM(name)) = ?', [$name])
            ->orderBy('id')
            ->value('id');

        return $id !== null ? (int) $id : null;
    }

    /**
     * Opciones del catálogo: id => «Nombre · US$ precio» (o «sin precio»). Catálogos cortos.
     *
     * @return array<int, string>
     */
    public static function options(string $kind): array
    {
        if (! self::isServiceKind($kind)) {
            return [];
        }

        return self::definition($kind)['catalog']::query()
            ->orderBy('name')
            ->limit(1000)
            ->get(['id', 'name', 'cost', 'profit_percentage'])
            ->mapWithKeys(function (Model $entry): array {
                $price = self::price($entry);

                return [(int) $entry->getKey() => trim((string) $entry->getAttribute('name')).' · '.($price !== null ? 'US$ '.number_format($price, 2, '.', ',') : 'sin precio: cárguelo al elegirlo')];
            })
            ->all();
    }

    public static function find(string $kind, mixed $id): ?Model
    {
        $id = (int) $id;

        if ($id <= 0 || ! self::isServiceKind($kind)) {
            return null;
        }

        return self::definition($kind)['catalog']::query()->find($id, ['id', 'name', 'cost', 'profit_percentage']);
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, Model>
     */
    public static function findMany(string $kind, array $ids): Collection
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

        if ($ids === [] || ! self::isServiceKind($kind)) {
            return collect();
        }

        return self::definition($kind)['catalog']::query()->whereIn('id', $ids)->get(['id', 'name', 'cost', 'profit_percentage'])->keyBy('id');
    }

    public static function needsCost(?Model $entry): bool
    {
        $cost = $entry?->getAttribute('cost');

        return ! is_numeric($cost) || (float) $cost <= 0;
    }

    public static function needsPercentage(?Model $entry): bool
    {
        return ! is_numeric($entry?->getAttribute('profit_percentage'));
    }

    /**
     * Precio al paciente con lo guardado en la fila o, si falta, con lo que cargó el analista.
     */
    public static function price(?Model $entry, mixed $costOverride = null, mixed $percentageOverride = null): ?float
    {
        if ($entry === null) {
            return null;
        }

        $cost = self::needsCost($entry) ? $costOverride : $entry->getAttribute('cost');
        $percentage = self::needsPercentage($entry) ? $percentageOverride : $entry->getAttribute('profit_percentage');

        $price = OperationInventoryProductPricing::finalPrice($cost, $percentage);

        return $price !== null && $price > 0 ? $price : null;
    }
}
