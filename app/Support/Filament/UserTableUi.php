<?php

declare(strict_types=1);

namespace App\Support\Filament;

use App\Models\User;

final class UserTableUi
{
    public static function moduleShortLabel(string $module): string
    {
        return match (strtoupper($module)) {
            'ADMINISTRACION' => 'Administración',
            'NEGOCIOS' => 'Negocios',
            'MARKETING' => 'Marketing',
            'OPERACIONES' => 'Operaciones',
            'PROYECTOS' => 'Proyectos',
            'SISTEMAS' => 'Sistemas',
            'SUPERADMIN' => 'Superadmin',
            'TELEMEDICINA' => 'Telemedicina',
            default => $module,
        };
    }

    /**
     * @return list<string>
     */
    public static function moduleBadgeLabels(mixed $departments): array
    {
        if (is_string($departments) && trim($departments) !== '') {
            return [self::moduleShortLabel($departments)];
        }

        if (! is_array($departments) || $departments === []) {
            return ['Sin módulos'];
        }

        $labels = array_values(array_filter(array_map(
            function (mixed $department): ?string {
                if (! is_string($department) || trim($department) === '') {
                    return null;
                }

                return self::moduleShortLabel($department);
            },
            $departments,
        )));

        return $labels === [] ? ['Sin módulos'] : $labels;
    }

    public static function statusBadgeColor(?string $status): string
    {
        return match (strtoupper(trim((string) ($status ?? '')))) {
            'ACTIVO' => 'success',
            'INACTIVO' => 'danger',
            'PENDIENTE' => 'warning',
            default => 'gray',
        };
    }

    public static function commercialSummary(User $user): ?string
    {
        if ($user->is_agency && filled($user->code_agency)) {
            $type = filled($user->agency_type) ? ' · '.$user->agency_type : '';

            return 'Agencia '.$user->code_agency.$type;
        }

        if (($user->is_agent || $user->is_subagent) && filled($user->code_agent)) {
            return 'Agente '.$user->code_agent;
        }

        return null;
    }
}
