<?php

declare(strict_types=1);

namespace App\Support\OrgStructure;

use App\Models\RrhhCargo;
use App\Models\RrhhColaborador;
use App\Models\RrhhDepartamento;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lectura de RRHH para el organigrama. RRHH es la fuente de las personas: el
 * organigrama nunca guarda nombres, fotos ni teléfonos, solo el vínculo.
 *
 * Nunca se lee ni se expone sueldo, cédula ni datos bancarios.
 */
final class OrgStructureRrhh
{
    /**
     * Columnas que el organigrama puede mostrar. Nada más sale de RRHH.
     *
     * @var list<string>
     */
    public const PUBLIC_COLUMNS = ['id', 'fullName', 'status', 'avatar', 'cargo_id', 'departmento_id', 'telefonoCorporativo', 'emailCorporativo', 'fechaIngreso'];

    public static function isActive(RrhhColaborador $colaborador): bool
    {
        return self::isActiveStatus($colaborador->status);
    }

    public static function isActiveStatus(mixed $status): bool
    {
        return mb_strtolower(trim((string) $status)) === 'activo';
    }

    /**
     * @param  Builder<RrhhColaborador>  $query
     * @return Builder<RrhhColaborador>
     */
    public static function whereActive(Builder $query): Builder
    {
        return $query->whereRaw('LOWER(TRIM(status)) = ?', ['activo']);
    }

    /**
     * @return array<int, string>
     */
    public static function cargoNames(): array
    {
        return RrhhCargo::query()
            ->orderBy('description')
            ->pluck('description', 'id')
            ->mapWithKeys(fn (mixed $name, mixed $id): array => [(int) $id => (string) $name])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function departamentoNames(): array
    {
        return RrhhDepartamento::query()
            ->orderBy('description')
            ->pluck('description', 'id')
            ->mapWithKeys(fn (mixed $name, mixed $id): array => [(int) $id => (string) $name])
            ->all();
    }

    /**
     * Cargos con su departamento, para el selector del editor.
     *
     * @return list<array{id: int, name: string, departamento_id: int|null}>
     */
    public static function cargoOptions(): array
    {
        return RrhhCargo::query()
            ->orderBy('description')
            ->get(['id', 'description', 'departamento_id'])
            ->map(fn (RrhhCargo $cargo): array => [
                'id' => (int) $cargo->id,
                'name' => (string) $cargo->description,
                'departamento_id' => filled($cargo->departamento_id) ? (int) $cargo->departamento_id : null,
            ])
            ->values()
            ->all();
    }

    /**
     * Paleta del estudio: áreas y cargos de RRHH con cuántas personas activas
     * tiene cada uno. Dos consultas agrupadas, sin importar el tamaño de RRHH.
     *
     * @return array{areas: list<array{id: int, name: string, people: int}>, cargos: list<array{id: int, name: string, departamento_id: int|null, people: int}>}
     */
    public static function paletteCatalog(): array
    {
        $byCargo = self::whereActive(RrhhColaborador::query())
            ->selectRaw('cargo_id, COUNT(*) AS total')
            ->groupBy('cargo_id')
            ->pluck('total', 'cargo_id')
            ->mapWithKeys(fn (mixed $total, mixed $id): array => [(int) $id => (int) $total]);

        $byDepartamento = self::whereActive(RrhhColaborador::query())
            ->selectRaw('departmento_id, COUNT(*) AS total')
            ->groupBy('departmento_id')
            ->pluck('total', 'departmento_id')
            ->mapWithKeys(fn (mixed $total, mixed $id): array => [(int) $id => (int) $total]);

        return [
            'areas' => collect(self::departamentoNames())
                ->map(fn (string $name, int $id): array => ['id' => $id, 'name' => $name, 'people' => $byDepartamento->get($id, 0)])
                ->values()
                ->all(),
            'cargos' => collect(self::cargoOptions())
                ->map(fn (array $cargo): array => [...$cargo, 'people' => $byCargo->get($cargo['id'], 0)])
                ->values()
                ->all(),
        ];
    }

    public static function avatarUrl(?string $avatar): ?string
    {
        $avatar = trim((string) $avatar);

        if ($avatar === '') {
            return null;
        }

        if (str_starts_with($avatar, 'http://') || str_starts_with($avatar, 'https://')) {
            return $avatar;
        }

        return url('storage/'.ltrim($avatar, '/'));
    }

    public static function initials(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name)) ?: [];
        $first = $parts[0] ?? '';
        $second = count($parts) > 2 ? ($parts[2] ?? '') : ($parts[1] ?? '');

        $initials = mb_strtoupper(mb_substr($first, 0, 1).mb_substr($second, 0, 1));

        return $initials === '' ? '?' : $initials;
    }

    /**
     * Busca colaboradores activos por nombre para asignarlos a un puesto.
     *
     * @return list<array{id: int, name: string, cargo: string|null, initials: string, avatar: string|null}>
     */
    public static function searchActive(string $term, int $limit = 20): array
    {
        $term = trim($term);
        $cargos = self::cargoNames();

        $query = self::whereActive(RrhhColaborador::query())
            ->select(['id', 'fullName', 'cargo_id', 'avatar'])
            ->orderBy('fullName')
            ->limit($limit);

        if ($term !== '') {
            $query->where('fullName', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%');
        }

        return $query->get()
            ->map(fn (RrhhColaborador $colaborador): array => [
                'id' => (int) $colaborador->id,
                'name' => (string) $colaborador->fullName,
                'cargo' => $cargos[(int) $colaborador->cargo_id] ?? null,
                'initials' => self::initials((string) $colaborador->fullName),
                'avatar' => self::avatarUrl($colaborador->avatar),
            ])
            ->values()
            ->all();
    }
}
