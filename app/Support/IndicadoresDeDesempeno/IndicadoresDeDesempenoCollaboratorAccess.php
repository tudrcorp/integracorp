<?php

declare(strict_types=1);

namespace App\Support\IndicadoresDeDesempeno;

use App\Support\Filament\Operations\OperationsSuperAdmin;
use Illuminate\Support\Facades\Auth;

final class IndicadoresDeDesempenoCollaboratorAccess
{
    public static function isSuperAdmin(): bool
    {
        return OperationsSuperAdmin::check();
    }

    public static function currentCollaboratorName(): ?string
    {
        $user = Auth::user();

        if ($user === null) {
            return null;
        }

        $name = trim((string) ($user->name ?? ''));

        return $name !== '' ? $name : null;
    }

    /**
     * Nombre del colaborador al que se restringen los datos, o null si puede ver todos (super admin).
     */
    public static function restrictToCollaborator(): ?string
    {
        if (self::isSuperAdmin()) {
            return null;
        }

        return self::currentCollaboratorName();
    }

    /**
     * @param  list<string>  $allLabels
     * @return list<string>
     */
    public static function visibleCollaboratorLabels(array $allLabels): array
    {
        if (self::isSuperAdmin()) {
            return array_values($allLabels);
        }

        $mine = self::currentCollaboratorName();

        if ($mine === null) {
            return [];
        }

        return [$mine];
    }

    /**
     * @param  array{labels: list<string>, juridicos: list<int>, naturales: list<int>}  $series
     * @return array{labels: list<string>, juridicos: list<int>, naturales: list<int>}
     */
    public static function filterDualSeriesToCollaborator(array $series, ?string $collaborator): array
    {
        if ($collaborator === null) {
            return $series;
        }

        $index = array_search($collaborator, $series['labels'], true);

        if ($index === false) {
            return [
                'labels' => [$collaborator],
                'juridicos' => [0],
                'naturales' => [0],
            ];
        }

        return [
            'labels' => [$collaborator],
            'juridicos' => [(int) $series['juridicos'][$index]],
            'naturales' => [(int) $series['naturales'][$index]],
        ];
    }
}
