<?php

declare(strict_types=1);

namespace App\Support\UserActivity\Workday;

use App\Models\User;
use App\Support\UserActivity\UserActivityProfile;
use Illuminate\Support\Collection;

/**
 * Quién tiene jornada: los internos activos (@tudrencasa.com o con
 * departamento) y todos los médicos, tengan o no correo interno. Agentes,
 * agencias y proveedores se siguen midiendo, pero sin jornada.
 */
final class WorkdaySubjects
{
    /**
     * Columnas de `users` para clasificar y armar la jornada. Sin la migración
     * aplicada `work_schedule` no existe: se piden solo las de antes.
     *
     * @return list<string>
     */
    public static function columns(): array
    {
        return WorkdayConfig::enabled() ? [...UserActivityProfile::COLUMNS, 'work_schedule'] : UserActivityProfile::COLUMNS;
    }

    public static function isSubject(?User $user): bool
    {
        if ($user === null || mb_strtoupper(trim((string) $user->status)) !== 'ACTIVO') {
            return false;
        }

        return (bool) $user->is_doctor || UserActivityProfile::classify($user)['type'] === 'internal';
    }

    /**
     * Todas las personas con jornada, por id.
     *
     * @return Collection<int, User>
     */
    public static function all(): Collection
    {
        return User::query()
            ->where('status', 'ACTIVO')
            ->where(static function ($query): void {
                $query->where('is_doctor', true)
                    ->orWhere('email', 'like', '%@tudrencasa.com')
                    ->orWhere(static function ($departments): void {
                        $departments->whereNotNull('departament')->whereNotIn('departament', ['', '[]', 'null']);
                    });
            })
            ->get(self::columns())
            ->filter(static fn (User $user): bool => self::isSubject($user))
            ->keyBy('id');
    }
}
