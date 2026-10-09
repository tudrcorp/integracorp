<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\CorporateAlly;
use App\Models\DoctorNurse;
use App\Models\Supplier;
use App\Support\Telemedicine\TelemedicineMedicalTeam;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Proveedores que el analista asigna a cada servicio del registro RETAIL:
 * naturales ({@see DoctorNurse}), jurídicos ({@see Supplier}), aliados
 * corporativos ({@see CorporateAlly}) y, para Telemedicina y AMD, los equipos
 * médicos ({@see TelemedicineMedicalTeam}).
 *
 * El valor del campo es una clave «tipo:id» (p. ej. `juridico:15`, `equipo:TDG`)
 * para que un solo select ofrezca los tres catálogos. Toda clave que llega del
 * navegador se vuelve a validar con {@see self::resolve()}.
 */
final class RetailServiceProviderCatalog
{
    public const TYPE_SUPPLIER = 'juridico';

    public const TYPE_DOCTOR_NURSE = 'natural';

    public const TYPE_CORPORATE_ALLY = 'aliado';

    /** Solo el Equipo Médico TDG: los equipos de proveedor son proveedores jurídicos. */
    public const TYPE_MEDICAL_TEAM = 'equipo';

    public const TDG_TEAM_PROVIDER_NAME = 'EQUIPO MÉDICO TDG';

    public const SEARCH_LIMIT = 15;

    public const SUGGESTION_LIMIT = 60;

    /** Servicios que atiende un equipo médico desde el panel de Telemedicina. */
    public const TEAM_SERVICES = ['TELEMEDICINA', 'AMD (ASISTENCIA MEDICA DOMICILIARIA)'];

    /**
     * Columnas de capacidad de `suppliers` que proponen proveedores por servicio.
     *
     * @var array<string, list<string>>
     */
    private const SUPPLIER_CAPABILITIES = [
        'AMD (ASISTENCIA MEDICA DOMICILIARIA)' => ['amd'],
        'TRASLADO EN AMBULANCIA' => ['ambulancias'],
        'URGEN CARE' => ['urgen_care'],
        'APS' => ['consulta_aps'],
        'INGRESO A CLINICA' => ['quirofanos'],
        'LABORATORIOS' => ['laboratorio_centro', 'laboratorio_domicilio'],
        'IMAGENOLOGIA' => ['rx_centro', 'rx_domicilio', 'eco_abdominal_centro', 'eco_abdominal_domicilio'],
    ];

    /** Servicios que suele prestar un médico o enfermero independiente. */
    private const NATURAL_PROVIDER_SERVICES = ['ESPECIALISTA', 'CONSULTA ONLINE CON MEDICO ESPECIALISTA'];

    public static function isTeamService(string $specificService): bool
    {
        return in_array($specificService, self::TEAM_SERVICES, true);
    }

    public static function key(string $type, int|string $id): string
    {
        return $type.':'.$id;
    }

    /**
     * @return array{type: string, id: int|null}|null
     */
    public static function parseKey(mixed $key): ?array
    {
        if (! is_string($key) || ! str_contains($key, ':')) {
            return null;
        }

        [$type, $id] = explode(':', trim($key), 2);

        if ($type === self::TYPE_MEDICAL_TEAM) {
            return $id === TelemedicineMedicalTeam::TDG ? ['type' => $type, 'id' => null] : null;
        }

        if (! in_array($type, [self::TYPE_SUPPLIER, self::TYPE_DOCTOR_NURSE, self::TYPE_CORPORATE_ALLY], true) || ! ctype_digit($id) || (int) $id < 1) {
            return null;
        }

        return ['type' => $type, 'id' => (int) $id];
    }

    /**
     * Opciones que se ven al abrir el select, antes de escribir: los equipos
     * médicos (Telemedicina y AMD) y los proveedores que declaran prestar el
     * servicio, primero los del estado del paciente.
     *
     * @return array<string, array<string, string>>
     */
    public static function suggestions(string $specificService, ?int $patientStateId = null): array
    {
        $groups = [];

        if (self::isTeamService($specificService)) {
            $groups['Equipos médicos · lo ve el médico en Telemedicina'] = self::teamOptions();
        }

        $capabilities = self::SUPPLIER_CAPABILITIES[$specificService] ?? [];

        if ($capabilities !== []) {
            $suppliers = Supplier::query()
                ->select(['id', 'name', 'rif', 'state_id', 'integracorp_alias'])
                ->with('state:id,definition')
                ->where(function (Builder $query) use ($capabilities): void {
                    foreach ($capabilities as $column) {
                        $query->orWhere($column, true);
                    }
                })
                ->when($patientStateId !== null, fn (Builder $query): Builder => $query->orderByRaw('state_id = ? DESC', [$patientStateId]))
                ->orderBy('name')
                ->limit(self::SUGGESTION_LIMIT)
                ->get();

            $options = $suppliers
                ->mapWithKeys(fn (Supplier $supplier): array => [self::key(self::TYPE_SUPPLIER, $supplier->id) => self::supplierLabel($supplier)])
                ->diffKeys($groups['Equipos médicos · lo ve el médico en Telemedicina'] ?? [])
                ->all();

            if ($options !== []) {
                $groups['Sugeridos para este servicio'] = $options;
            }
        }

        if (in_array($specificService, self::NATURAL_PROVIDER_SERVICES, true)) {
            $options = DoctorNurse::query()
                ->select(['id', 'name', 'rif', 'speciality', 'state'])
                ->orderBy('name')
                ->limit(self::SUGGESTION_LIMIT)
                ->get()
                ->mapWithKeys(fn (DoctorNurse $doctorNurse): array => [self::key(self::TYPE_DOCTOR_NURSE, $doctorNurse->id) => self::doctorNurseLabel($doctorNurse)])
                ->all();

            if ($options !== []) {
                $groups['Proveedores naturales'] = $options;
            }
        }

        return $groups;
    }

    /**
     * Búsqueda por nombre o RIF en los tres catálogos, agrupada por tipo.
     *
     * @return array<string, array<string, string>>
     */
    public static function search(string $search, string $specificService): array
    {
        $search = Str::squish($search);

        if (mb_strlen($search) < 2) {
            return [];
        }

        $like = '%'.$search.'%';
        $groups = [];

        if (self::isTeamService($specificService)) {
            $teams = array_filter(
                self::teamOptions(),
                fn (string $label): bool => Str::contains(Str::ascii(mb_strtolower($label)), Str::ascii(mb_strtolower($search))),
            );

            if ($teams !== []) {
                $groups['Equipos médicos · lo ve el médico en Telemedicina'] = $teams;
            }
        }

        $groups['Proveedores jurídicos'] = Supplier::query()
            ->select(['id', 'name', 'rif', 'state_id', 'integracorp_alias'])
            ->with('state:id,definition')
            ->where(fn (Builder $query): Builder => $query->where('name', 'like', $like)->orWhere('rif', 'like', $like)->orWhere('integracorp_alias', 'like', $like))
            ->orderBy('name')
            ->limit(self::SEARCH_LIMIT)
            ->get()
            ->mapWithKeys(fn (Supplier $supplier): array => [self::key(self::TYPE_SUPPLIER, $supplier->id) => self::supplierLabel($supplier)])
            ->all();

        $groups['Proveedores naturales'] = DoctorNurse::query()
            ->select(['id', 'name', 'rif', 'speciality', 'state'])
            ->where(fn (Builder $query): Builder => $query->where('name', 'like', $like)->orWhere('rif', 'like', $like)->orWhere('speciality', 'like', $like))
            ->orderBy('name')
            ->limit(self::SEARCH_LIMIT)
            ->get()
            ->mapWithKeys(fn (DoctorNurse $doctorNurse): array => [self::key(self::TYPE_DOCTOR_NURSE, $doctorNurse->id) => self::doctorNurseLabel($doctorNurse)])
            ->all();

        $groups['Aliados corporativos'] = CorporateAlly::query()
            ->select(['id', 'company_name', 'rif', 'state_id'])
            ->with('state:id,definition')
            ->where(fn (Builder $query): Builder => $query->where('company_name', 'like', $like)->orWhere('rif', 'like', $like))
            ->orderBy('company_name')
            ->limit(self::SEARCH_LIMIT)
            ->get()
            ->mapWithKeys(fn (CorporateAlly $ally): array => [self::key(self::TYPE_CORPORATE_ALLY, $ally->id) => self::corporateAllyLabel($ally)])
            ->all();

        return array_filter($groups, fn (array $options): bool => $options !== []);
    }

    public static function label(mixed $key): ?string
    {
        $parsed = self::parseKey($key);

        if ($parsed === null) {
            return null;
        }

        return match ($parsed['type']) {
            self::TYPE_MEDICAL_TEAM => TelemedicineMedicalTeam::LABEL.' TDG',
            self::TYPE_SUPPLIER => ($supplier = Supplier::query()->with('state:id,definition')->find($parsed['id'], ['id', 'name', 'rif', 'state_id', 'integracorp_alias'])) ? self::supplierLabel($supplier) : null,
            self::TYPE_DOCTOR_NURSE => ($doctorNurse = DoctorNurse::query()->find($parsed['id'], ['id', 'name', 'rif', 'speciality', 'state'])) ? self::doctorNurseLabel($doctorNurse) : null,
            self::TYPE_CORPORATE_ALLY => ($ally = CorporateAlly::query()->with('state:id,definition')->find($parsed['id'], ['id', 'company_name', 'rif', 'state_id'])) ? self::corporateAllyLabel($ally) : null,
            default => null,
        };
    }

    /**
     * Valida la clave contra la base y, en Telemedicina y AMD, contra los equipos
     * que el usuario en sesión puede asignar.
     *
     * `team` no es nulo cuando el servicio lo atiende un equipo médico: TDG o un
     * proveedor con «Habilitar gestión en Integracorp» y médicos registrados.
     *
     * @return array{key: string, type: string, supplier_id: int|null, doctor_nurse_id: int|null, corporate_ally_id: int|null, name: string, team: array{key: string, supplier_id: int|null, managed_by: string, label: string}|null}|null
     */
    public static function resolve(mixed $key, string $specificService): ?array
    {
        $parsed = self::parseKey($key);

        if ($parsed === null) {
            return null;
        }

        $base = ['key' => (string) $key, 'type' => $parsed['type'], 'supplier_id' => null, 'doctor_nurse_id' => null, 'corporate_ally_id' => null, 'team' => null];

        if ($parsed['type'] === self::TYPE_MEDICAL_TEAM) {
            $team = self::isTeamService($specificService) ? self::resolveTeam(TelemedicineMedicalTeam::TDG) : null;

            return $team === null ? null : [...$base, 'name' => self::TDG_TEAM_PROVIDER_NAME, 'team' => $team];
        }

        if ($parsed['type'] === self::TYPE_SUPPLIER) {
            $name = Supplier::query()->whereKey($parsed['id'])->value('name');

            if ($name === null) {
                return null;
            }

            $team = self::isTeamService($specificService) ? self::resolveTeam((string) $parsed['id']) : null;

            return [...$base, 'supplier_id' => $parsed['id'], 'name' => self::cleanName($name), 'team' => $team];
        }

        if ($parsed['type'] === self::TYPE_DOCTOR_NURSE) {
            $name = DoctorNurse::query()->whereKey($parsed['id'])->value('name');

            return $name === null ? null : [...$base, 'doctor_nurse_id' => $parsed['id'], 'name' => self::cleanName($name)];
        }

        $name = CorporateAlly::query()->whereKey($parsed['id'])->value('company_name');

        return $name === null ? null : [...$base, 'corporate_ally_id' => $parsed['id'], 'name' => self::cleanName($name)];
    }

    public static function typeLabel(?string $type): string
    {
        return match ($type) {
            self::TYPE_SUPPLIER => 'Proveedor jurídico',
            self::TYPE_DOCTOR_NURSE => 'Proveedor natural',
            self::TYPE_CORPORATE_ALLY => 'Aliado corporativo',
            self::TYPE_MEDICAL_TEAM => TelemedicineMedicalTeam::LABEL,
            default => 'Proveedor',
        };
    }

    /**
     * Equipos que el usuario puede asignar, con la clave del select: TDG como
     * `equipo:TDG` y cada proveedor como su clave de proveedor jurídico.
     *
     * @return array<string, string>
     */
    public static function teamOptions(): array
    {
        $options = [];

        foreach (TelemedicineMedicalTeam::optionsForCurrentUser() as $teamKey => $label) {
            $key = (string) $teamKey === TelemedicineMedicalTeam::TDG
                ? self::key(self::TYPE_MEDICAL_TEAM, TelemedicineMedicalTeam::TDG)
                : self::key(self::TYPE_SUPPLIER, (int) $teamKey);

            $options[$key] = $label;
        }

        return $options;
    }

    /**
     * @return array{key: string, supplier_id: int|null, managed_by: string, label: string}|null
     */
    private static function resolveTeam(string $teamKey): ?array
    {
        $team = TelemedicineMedicalTeam::resolveForCurrentUser($teamKey);

        if ($team === null) {
            return null;
        }

        return [
            'key' => $teamKey,
            'supplier_id' => $team['supplier_id'],
            'managed_by' => $team['managed_by'],
            'label' => TelemedicineMedicalTeam::optionsForCurrentUser()[$teamKey] ?? TelemedicineMedicalTeam::LABEL,
        ];
    }

    private static function supplierLabel(Supplier $supplier): string
    {
        $alias = Str::squish((string) $supplier->integracorp_alias);
        $name = self::cleanName($supplier->name);

        return collect([
            $alias !== '' && mb_strtoupper($alias) !== mb_strtoupper($name) ? $name.' ('.$alias.')' : $name,
            filled($supplier->rif) ? 'RIF '.Str::squish((string) $supplier->rif) : null,
            $supplier->state?->definition,
        ])->filter()->implode(' · ');
    }

    private static function doctorNurseLabel(DoctorNurse $doctorNurse): string
    {
        return collect([
            self::cleanName($doctorNurse->name),
            Str::squish((string) $doctorNurse->speciality) ?: null,
            filled($doctorNurse->rif) ? 'RIF '.Str::squish((string) $doctorNurse->rif) : null,
            Str::squish((string) $doctorNurse->state) ?: null,
        ])->filter()->implode(' · ');
    }

    private static function corporateAllyLabel(CorporateAlly $ally): string
    {
        return collect([
            self::cleanName($ally->company_name),
            filled($ally->rif) ? 'RIF '.Str::squish((string) $ally->rif) : null,
            $ally->state?->definition,
        ])->filter()->implode(' · ');
    }

    private static function cleanName(mixed $name): string
    {
        $name = Str::squish((string) $name);

        return $name !== '' ? $name : 'Sin nombre';
    }
}
