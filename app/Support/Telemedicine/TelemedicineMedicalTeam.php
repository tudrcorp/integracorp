<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use App\Models\Supplier;
use App\Models\TelemedicineCase;
use App\Models\TelemedicineDoctor;
use App\Support\Filament\Operations\OperationsSupplierScope;
use Illuminate\Support\Str;

/**
 * Equipo médico de guardia al que se asigna un caso sin médico particular.
 *
 * Un equipo es TDG o un proveedor con «Habilitar gestión en Integracorp» activo
 * ({@see Supplier::$gestion_integracorp}) y médicos de telemedicina registrados. El caso nace sin médico; lo toma quien esté de guardia y desde
 * la primera consulta {@see TelemedicineCase::$telemedicine_doctor_id} sigue al
 * médico que actualiza (ver {@see self::applyUpdatingDoctor()}).
 */
final class TelemedicineMedicalTeam
{
    public const TDG = 'TDG';

    public const LABEL = 'Equipo Médico';

    /**
     * Equipos que el usuario en sesión puede elegir. El analista TDG elige entre
     * TDG y cualquier proveedor con médicos; el analista de un proveedor solo
     * puede asignar a su propio equipo.
     *
     * @return array<string, string> clave del equipo => etiqueta
     */
    public static function optionsForCurrentUser(): array
    {
        if (OperationsSupplierScope::authenticatedUserIsTdgAnalyst()) {
            return [self::TDG => self::LABEL.' TDG'] + self::supplierOptions();
        }

        $supplierId = OperationsSupplierScope::currentSupplierId();

        if ($supplierId === null) {
            return [];
        }

        return array_intersect_key(self::supplierOptions(), [(string) $supplierId => true]);
    }

    /**
     * Valida en el servidor la clave elegida contra las opciones permitidas al usuario:
     * ocultar el campo en el navegador no basta.
     *
     * @return array{supplier_id: int|null, managed_by: string}|null
     */
    public static function resolveForCurrentUser(mixed $selection): ?array
    {
        $key = is_scalar($selection) ? trim((string) $selection) : '';

        if ($key === '' || ! array_key_exists($key, self::optionsForCurrentUser())) {
            return null;
        }

        if ($key === self::TDG) {
            return ['supplier_id' => null, 'managed_by' => self::TDG];
        }

        $supplierId = (int) $key;

        return ['supplier_id' => $supplierId, 'managed_by' => self::managedByForSupplier($supplierId)];
    }

    /**
     * Etiqueta del responsable del caso: el médico si ya lo tomó alguien, o el equipo si sigue en guardia.
     */
    public static function assigneeLabel(TelemedicineCase $case): string
    {
        $doctorName = trim((string) ($case->telemedicineDoctor?->full_name ?? ''));

        if ($doctorName !== '') {
            return 'Dr(a). '.$doctorName;
        }

        if ($case->assigned_to_medical_team) {
            return self::teamLabel($case);
        }

        return 'Dr(a). —';
    }

    public static function teamLabel(TelemedicineCase $case): string
    {
        if ($case->medical_team_supplier_id === null) {
            return self::LABEL.' TDG';
        }

        $supplierName = self::supplierDisplayName($case->medicalTeamSupplier);

        return $supplierName !== '' ? self::LABEL.' · '.$supplierName : self::LABEL;
    }

    /**
     * Quién gestiona el caso, en corto: «TDG» o el alias del proveedor («ATENMEDI»).
     *
     * Sale de {@see TelemedicineCase::$managed_by}; el proveedor se toma del equipo
     * asignado o del médico del caso para mostrar su alias en lugar del nombre legal,
     * y si no hay proveedor resoluble se muestra el texto de `managed_by` tal cual.
     */
    public static function caseManagerLabel(?TelemedicineCase $case): ?string
    {
        if ($case === null) {
            return null;
        }

        $managedBy = Str::squish((string) $case->managed_by);

        if (mb_strtoupper($managedBy) === self::TDG) {
            return self::TDG;
        }

        $supplier = $case->assigned_to_medical_team
            ? $case->medicalTeamSupplier
            : $case->telemedicineDoctor?->supplier;

        $supplierName = self::supplierDisplayName($supplier);

        if ($supplierName !== '') {
            return $supplierName;
        }

        return $managedBy !== '' ? $managedBy : null;
    }

    /**
     * En un caso de equipo, el médico asignado pasa a ser quien registra cada
     * consulta o seguimiento. Los casos asignados a un médico particular no se tocan.
     * Solo cambia el atributo: el llamador lo persiste en el mismo `save()` del caso,
     * para que observers y estadísticas vean el nuevo responsable.
     */
    public static function applyUpdatingDoctor(?TelemedicineCase $case, ?int $doctorId): void
    {
        if ($case === null || ! $case->assigned_to_medical_team || $doctorId === null || $doctorId < 1) {
            return;
        }

        if ((int) $case->telemedicine_doctor_id === $doctorId) {
            return;
        }

        $case->telemedicine_doctor_id = $doctorId;
        $case->unsetRelation('telemedicineDoctor');
    }

    /**
     * Proveedores con «Habilitar gestión en Integracorp» activo y al menos un médico
     * de telemedicina registrado: sin médicos, un caso asignado a su equipo no lo vería nadie.
     *
     * @return array<string, string>
     */
    private static function supplierOptions(): array
    {
        return Supplier::query()
            ->select(['id', 'name', 'integracorp_alias'])
            ->where('gestion_integracorp', true)
            ->whereIn('id', TelemedicineDoctor::query()->whereNotNull('supplier_id')->select('supplier_id'))
            ->orderByRaw('COALESCE(NULLIF(TRIM(integracorp_alias), \'\'), name)')
            ->get()
            ->mapWithKeys(fn (Supplier $supplier): array => [
                (string) $supplier->id => self::LABEL.' · '.self::supplierDisplayName($supplier),
            ])
            ->all();
    }

    /**
     * Alias de gestión en Integracorp (el que el analista reconoce); el nombre legal solo como respaldo.
     */
    private static function supplierDisplayName(?Supplier $supplier): string
    {
        if ($supplier === null) {
            return '';
        }

        $alias = Str::squish((string) $supplier->integracorp_alias);

        if ($alias !== '') {
            return $alias;
        }

        $name = Str::squish((string) $supplier->name);

        return $name !== '' ? $name : 'Proveedor #'.$supplier->id;
    }

    /**
     * `managed_by` del caso con el mismo valor que tendría al asignarse a un médico
     * de ese proveedor, para que los filtros existentes de Operaciones lo traten igual.
     */
    private static function managedByForSupplier(int $supplierId): string
    {
        $managedBy = TelemedicineDoctor::query()
            ->where('supplier_id', $supplierId)
            ->whereNotNull('managed_by')
            ->where('managed_by', '!=', '')
            ->groupBy('managed_by')
            ->orderByRaw('COUNT(*) DESC')
            ->value('managed_by');

        if (filled($managedBy)) {
            return (string) $managedBy;
        }

        return mb_substr(trim((string) (Supplier::query()->whereKey($supplierId)->value('name') ?? '')), 0, 100);
    }
}
