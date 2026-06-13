<?php

declare(strict_types=1);

namespace App\Support\Filament;

use App\Support\FilamentDateDisplay;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class CorporateAffiliatesTableDisplay
{
    /**
     * @return array<int, TextColumn>
     */
    public static function columns(): array
    {
        return [
            TextColumn::make('first_name')
                ->label('Nombre')
                ->searchable()
                ->sortable(),
            TextColumn::make('last_name')
                ->label('Apellido')
                ->searchable()
                ->sortable(),
            TextColumn::make('nro_identificacion')
                ->label('Cédula')
                ->searchable()
                ->copyable()
                ->copyMessage('Copiado'),
            TextColumn::make('birth_date')
                ->label('Fecha de nacimiento')
                ->formatStateUsing(fn (mixed $state): ?string => FilamentDateDisplay::toDmy($state))
                ->sortable(),
            TextColumn::make('email')
                ->label('Correo')
                ->searchable()
                ->limit(28)
                ->tooltip(fn ($record): ?string => strlen((string) $record->email) > 28 ? $record->email : null),
            TextColumn::make('phone')
                ->label('Teléfono')
                ->searchable(),
            TextColumn::make('affiliationCorporate.state.definition')
                ->label('Estado')
                ->searchable(),
            TextColumn::make('affiliationCorporate.city.definition')
                ->label('Ciudad')
                ->searchable(),
            TextColumn::make('address')
                ->label('Dirección')
                ->searchable()
                ->limit(40)
                ->tooltip(fn ($record): ?string => strlen((string) $record->address) > 40 ? $record->address : null),
        ];
    }

    public static function configureReadOnlyTable(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'affiliationCorporate.state',
                'affiliationCorporate.city',
            ]))
            ->heading('AFILIADOS')
            ->description('Lista de empleados afiliados a esta cotización corporativa')
            ->columns(self::columns())
            ->striped()
            ->defaultSort('last_name');
    }
}
