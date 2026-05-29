<?php

declare(strict_types=1);

namespace App\Support\Filament\ProjectManagement;

use App\Models\RrhhColaborador;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;

final class ProjectManagementCollaboratorSelect
{
    public const EXCLUDED_COLLABORATOR_NAME = 'CAYETANO BATRES';

    public static function make(string $name, string $label = 'Colaboradores'): Select
    {
        return Select::make($name)
            ->label($label)
            ->prefixIcon('heroicon-m-users')
            ->multiple()
            ->searchable()
            ->preload()
            ->default([])
            ->options(fn (): array => self::options())
            ->getSearchResultsUsing(fn (string $search): array => self::searchOptions($search))
            ->helperText('Selecciona uno o varios colaboradores (se excluye '.self::EXCLUDED_COLLABORATOR_NAME.').');
    }

    /**
     * @return array<int, string>
     */
    public static function options(): array
    {
        return RrhhColaborador::query()
            ->where('fullName', '!=', self::EXCLUDED_COLLABORATOR_NAME)
            ->orderBy('fullName', 'asc')
            ->pluck('fullName', 'id')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function searchOptions(string $search): array
    {
        return RrhhColaborador::query()
            ->where('fullName', '!=', self::EXCLUDED_COLLABORATOR_NAME)
            ->where(fn (Builder $query): Builder => $query->where('fullName', 'like', "%{$search}%"))
            ->orderBy('fullName', 'asc')
            ->limit(50)
            ->pluck('fullName', 'id')
            ->all();
    }
}
