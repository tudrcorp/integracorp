<?php

declare(strict_types=1);

namespace App\Support\ApiPartners;

use App\Models\TelemedicinePatientMedications;
use App\Support\Operations\MedicationInventoryStockStatus;
use App\Support\Telemedicine\TelemedicineMedicationCoverage;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Medicamentos NO CUBIERTOS en gestión de Operaciones, tal como se exponen a los aliados.
 *
 * Entran todos los medicamentos no cubiertos ligados a una coordinación de servicio, en
 * cualquier estatus de gestión. Se excluyen los de casos eliminados. La regla de cobertura
 * es la misma del resto del sistema ({@see TelemedicineMedicationCoverage::whereNotCovered()}).
 */
final class NonCoveredMedicationFeed
{
    /** @var list<string> */
    public const STATUSES = ['PENDIENTE', 'EN GESTION', 'FINALIZADO', 'CANCELADA'];

    public const DEFAULT_PER_PAGE = 25;

    public const MAX_PER_PAGE = 100;

    /**
     * @return Builder<TelemedicinePatientMedications>
     */
    public static function query(): Builder
    {
        $query = TelemedicinePatientMedications::query()
            ->whereNotNull('operation_coordination_service_id')
            ->whereHas('operationCoordinationService')
            ->where(function (Builder $case): void {
                // Los casos eliminados quedan fuera por el scope global de TelemedicineCase.
                $case->whereNull('telemedicine_case_id')->orWhereHas('telemedicineCase');
            });

        return TelemedicineMedicationCoverage::whereNotCovered($query)
            ->with([
                'telemedicinePatient:id,full_name,nro_identificacion,phone,state_id,city_id',
                'telemedicinePatient.state:id,definition',
                'telemedicinePatient.city:id,definition',
                'telemedicineCase:id,code,status',
                'telemedicinePriority:id,name',
                'operationCoordinationService:id,reference_number,status,patient,ci_patient,phone_holder,state_id,city_id',
                'operationCoordinationService.state:id,definition',
                'operationCoordinationService.city:id,definition',
            ]);
    }

    /**
     * @param  array{estatus?: list<string>, desde?: string, hasta?: string, actualizado_desde?: string, caso?: string, coordinacion?: int|string, por_pagina?: int|string, pagina?: int|string}  $filters
     * @return LengthAwarePaginator<int, TelemedicinePatientMedications>
     */
    public static function paginate(array $filters): LengthAwarePaginator
    {
        $query = self::query();

        if (($filters['estatus'] ?? []) !== []) {
            $query->whereIn('status', $filters['estatus']);
        }

        if (filled($filters['desde'] ?? null)) {
            $query->where('created_at', '>=', CarbonImmutable::parse($filters['desde'])->startOfDay());
        }

        if (filled($filters['hasta'] ?? null)) {
            $query->where('created_at', '<=', CarbonImmutable::parse($filters['hasta'])->endOfDay());
        }

        if (filled($filters['caso'] ?? null)) {
            $code = (string) $filters['caso'];
            $query->whereHas('telemedicineCase', fn (Builder $case): Builder => $case->where('code', $code));
        }

        if (filled($filters['coordinacion'] ?? null)) {
            $query->where('operation_coordination_service_id', (int) $filters['coordinacion']);
        }

        if (filled($filters['actualizado_desde'] ?? null)) {
            // Sincronización incremental: del más viejo al más nuevo, para avanzar página a página sin saltos.
            $query->where('updated_at', '>=', CarbonImmutable::parse($filters['actualizado_desde']))
                ->orderBy('updated_at')
                ->orderBy('id');
        } else {
            $query->orderByDesc('id');
        }

        $perPage = (int) ($filters['por_pagina'] ?? self::DEFAULT_PER_PAGE);
        $perPage = max(1, min(self::MAX_PER_PAGE, $perPage));

        return $query->paginate(
            perPage: $perPage,
            pageName: 'pagina',
            page: max(1, (int) ($filters['pagina'] ?? 1)),
        );
    }

    public static function find(int $id): ?TelemedicinePatientMedications
    {
        return self::query()->whereKey($id)->first();
    }

    /**
     * @param  iterable<TelemedicinePatientMedications>  $medications
     * @return list<array<string, mixed>>
     */
    public static function present(iterable $medications): array
    {
        $input = [];

        foreach ($medications as $medication) {
            $input[(int) $medication->getKey()] = [
                'name' => $medication->medicine,
                'operation_inventory_id' => $medication->operation_inventory_id !== null ? (int) $medication->operation_inventory_id : null,
            ];
        }

        $statuses = MedicationInventoryStockStatus::forMedications($input);
        $payload = [];

        foreach ($medications as $medication) {
            $payload[] = self::payload(
                $medication,
                $statuses[(int) $medication->getKey()] ?? MedicationInventoryStockStatus::MISSING,
            );
        }

        return $payload;
    }

    /**
     * Forma pública de un medicamento. Solo los datos acordados con el aliado:
     * nunca diagnóstico, historia clínica ni montos.
     *
     * @return array<string, mixed>
     */
    public static function payload(TelemedicinePatientMedications $medication, string $inventoryStatus): array
    {
        $patient = $medication->telemedicinePatient;
        $coordination = $medication->operationCoordinationService;

        return [
            'id' => (int) $medication->getKey(),
            'caso' => [
                'numero' => self::text($medication->telemedicineCase?->code),
                'coordinacion_id' => $coordination !== null ? (int) $coordination->getKey() : null,
                'coordinacion_referencia' => self::text($coordination?->reference_number),
                'coordinacion_estatus' => self::text($coordination?->status),
            ],
            'paciente' => [
                'nombre' => self::text($patient?->full_name) ?? self::text($coordination?->patient),
                'cedula' => self::text($patient?->nro_identificacion) ?? self::text($coordination?->ci_patient),
                'telefono' => self::text($patient?->phone) ?? self::text($coordination?->phone_holder),
                'estado' => self::text($patient?->state?->definition) ?? self::text($coordination?->state?->definition),
                'ciudad' => self::text($patient?->city?->definition) ?? self::text($coordination?->city?->definition),
            ],
            'medicamento' => [
                'nombre' => self::text($medication->medicine),
                'indicaciones' => self::text($medication->indications),
                'cantidad' => self::text($medication->quantity),
                'duracion' => self::text($medication->duration),
                'prioridad' => self::text($medication->telemedicinePriority?->name),
                'estatus_gestion' => self::text($medication->status),
                'cubierto' => false,
            ],
            'inventario' => [
                'estado' => $inventoryStatus,
            ],
            'registrado_en' => $medication->created_at?->toIso8601String(),
            'actualizado_en' => $medication->updated_at?->toIso8601String(),
        ];
    }

    private static function text(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
