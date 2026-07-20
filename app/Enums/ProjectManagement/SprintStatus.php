<?php

declare(strict_types=1);

namespace App\Enums\ProjectManagement;

enum SprintStatus: string
{
    case Planned = 'planned';
    case Active = 'active';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Planned => 'Planificado',
            self::Active => 'Activo',
            self::Completed => 'Completado',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $status): array => [$status->value => $status->label()])
            ->all();
    }
}
