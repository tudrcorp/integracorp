<?php

namespace App\Enums;

enum CorporateAgendaActivityType: string
{
    case ReunionInterna = 'REUNION INTERNA';
    case ReunionExterna = 'REUNION EXTERNA';
    case ActividadFueraDeOficina = 'ACTIVIDAD FUERA DE OFICINA';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type): array => [$type->value => $type->value])
            ->all();
    }
}
