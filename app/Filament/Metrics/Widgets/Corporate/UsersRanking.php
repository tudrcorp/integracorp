<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets\Corporate;

/**
 * Ranking de personas: por defecto en horas con un decimal. Cada fila abre los
 * días de esa persona en el período.
 */
abstract class UsersRanking extends CorporateRanking
{
    protected function isMoney(): bool
    {
        return false;
    }

    protected function dimension(): string
    {
        return 'user_person';
    }

    protected function icon(): string
    {
        return 'heroicon-o-user';
    }

    protected function noun(): string
    {
        return 'h';
    }

    protected function decimals(): int
    {
        return 1;
    }

    protected function suffix(): string
    {
        return ' h';
    }

    protected function emptyText(): string
    {
        return 'No hay actividad registrada en este período.';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [...parent::getViewData(), 'decimals' => $this->decimals(), 'suffix' => $this->suffix()];
    }
}
