<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\TravelAgents\Tables;

use App\Models\TravelAgent;
use Carbon\Carbon;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class TravelAgentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->query(function (Builder $query) {
                if (Auth::user()->is_accountManagers) {
                    // dd(Auth::user()->id);
                    return TravelAgent::query()->where('ownerAccountManagers', Auth::user()->id);
                }

                return TravelAgent::query();
            })
            ->heading('Agentes de viaje')
            ->description('Personas vinculadas a una agencia de viajes: contacto, cargo y datos básicos.')
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->emptyStateHeading('Sin agentes de viaje')
            ->emptyStateDescription('Aún no hay agentes registrados o no coinciden con los filtros aplicados.')
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->icon(Heroicon::OutlinedUser)
                    ->weight('medium')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record): ?string => $record->cargo ? (string) $record->cargo : null),
                TextColumn::make('travelAgency.name')
                    ->label('Agencia')
                    ->icon(Heroicon::OutlinedBuildingOffice2)
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('email')
                    ->label('Correo')
                    ->icon(Heroicon::OutlinedEnvelope)
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Correo copiado')
                    ->toggleable(),
                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->icon(Heroicon::OutlinedPhone)
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Teléfono copiado')
                    ->toggleable(),
                TextColumn::make('cargo')
                    ->label('Cargo')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('fechaNacimiento')
                    ->label('Fecha de nacimiento')
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->formatStateUsing(function (mixed $state): ?string {
                        if (blank($state)) {
                            return null;
                        }
                        try {
                            return Carbon::parse($state)->format('d/m/Y');
                        } catch (\Throwable) {
                            return (string) $state;
                        }
                    })
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_by')
                    ->label('Creado por')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_by')
                    ->label('Actualizado por')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Alta')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Última edición')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('travel_agency_id')
                    ->label('Agencia de viaje')
                    ->relationship('travelAgency', 'name')
                    ->searchable()
                    ->preload()
                    ->placeholder('Todas'),
            ])
            ->recordActions([
                EditAction::make()
                    ->icon(Heroicon::OutlinedPencilSquare),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
