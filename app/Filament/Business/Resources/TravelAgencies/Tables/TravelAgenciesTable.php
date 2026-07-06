<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\TravelAgencies\Tables;

use App\Http\Controllers\TravelAgencyExportCsvController;
use App\Models\TravelAgency;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class TravelAgenciesTable
{
    /**
     * @return array<string, string>
     */
    private static function distinctColumnOptions(string $column): array
    {
        return TravelAgency::query()
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->distinct()
            ->orderBy($column)
            ->pluck($column, $column)
            ->all();
    }

    private static function travelAgencyStatusColor(?string $state): string
    {
        return match (strtoupper((string) $state)) {
            'ACTIVO', 'ACTIVA', 'APROBADO', 'APROBADA' => 'success',
            'INACTIVO', 'INACTIVA', 'SUSPENDIDO', 'RECHAZADO' => 'danger',
            'PENDIENTE', 'POR REVISAR', 'EN REVISIÓN', 'EN REVISION' => 'warning',
            default => 'gray',
        };
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->query(function (Builder $query) {
                if (Auth::user()->is_accountManagers) {
                    // dd(Auth::user()->id);
                    return TravelAgency::query()->where('ownerAccountManagers', Auth::user()->id);
                }

                return TravelAgency::query();
            })
            ->heading('Agencias de viaje')
            ->description('Directorio comercial: identidad, ubicación, clasificación y montos de crédito.')
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->emptyStateHeading('Sin agencias de viaje')
            ->emptyStateDescription('Crea la primera agencia o ajusta los filtros para ver resultados.')
            ->columns([
                ImageColumn::make('logo')
                    ->label('Logo')
                    ->disk('public')
                    ->imageSize(48)
                    ->circular()
                    ->extraImgAttributes([
                        'class' => 'object-cover ring-1 ring-slate-200/80 dark:ring-white/10',
                    ]),
                TextColumn::make('name')
                    ->label('Nombre')
                    ->icon(Heroicon::OutlinedBuildingOffice2)
                    ->weight('semibold')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record): ?string => filled($record->nameSecundario ?? null) ? (string) $record->nameSecundario : null),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (?string $state): string => self::travelAgencyStatusColor($state))
                    ->searchable()
                    ->sortable(),
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
                TextColumn::make('phoneAdditional')
                    ->label('Tel. adicional')
                    ->icon(Heroicon::OutlinedPhone)
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('country.name')
                    ->label('País')
                    ->icon(Heroicon::OutlinedGlobeAmericas)
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('state.definition')
                    ->label('Estado / provincia')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('city.definition')
                    ->label('Ciudad')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('address')
                    ->label('Dirección')
                    ->icon(Heroicon::OutlinedMapPin)
                    ->searchable()
                    ->limit(40)
                    ->tooltip(fn (?string $state): ?string => filled($state) && strlen($state) > 40 ? $state : null)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('classification')
                    ->label('Clasificación')
                    ->badge()
                    ->color('info')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('nivel')
                    ->label('Nivel')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('travel_agents_count')
                    ->label('Agentes')
                    ->icon(Heroicon::OutlinedUserGroup)
                    ->counts('travelAgents')
                    ->alignCenter()
                    ->badge()
                    ->color(fn (?int $state): string => ($state ?? 0) > 0 ? 'info' : 'gray')
                    ->sortable(),
                TextColumn::make('comision')
                    ->label('Comisión')
                    ->numeric(decimalPlaces: 2)
                    ->suffix(' %')
                    ->alignEnd()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('montoCreditoAprobado')
                    ->label('Crédito aprobado')
                    ->money('USD')
                    ->alignEnd()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('fechaIngreso')
                    ->label('Fecha de ingreso')
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('representante')
                    ->label('Representante')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('idRepresentante')
                    ->label('ID representante')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('FechaNacimientoRepresentante')
                    ->label('Nac. representante')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('typeIdentification')
                    ->label('Tipo identificación')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('numberIdentification')
                    ->label('Nº identificación')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('userPortalWeb')
                    ->label('Usuario portal')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('aniversary')
                    ->label('Aniversario')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('userInstagram')
                    ->label('Instagram')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('agenteSuperiorNivel3')
                    ->label('Agente sup. nivel 3')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('agenciaSuperiorNivel2')
                    ->label('Agencia sup. nivel 2')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('agenciaPpalNivel1')
                    ->label('Agencia principal niv. 1')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('emailSecundario')
                    ->label('Correo secundario')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('phoneSecundario')
                    ->label('Tel. secundario')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_by')
                    ->label('Creado por')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_by')
                    ->label('Actualizado por')
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
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(fn (): array => self::distinctColumnOptions('status'))
                    ->placeholder('Todos'),
                SelectFilter::make('country_id')
                    ->label('País')
                    ->relationship('country', 'name')
                    ->searchable()
                    ->preload()
                    ->placeholder('Todos'),
                SelectFilter::make('classification')
                    ->label('Clasificación')
                    ->options(fn (): array => self::distinctColumnOptions('classification'))
                    ->placeholder('Todas'),
                SelectFilter::make('nivel')
                    ->label('Nivel')
                    ->options(fn (): array => self::distinctColumnOptions('nivel'))
                    ->placeholder('Todos'),
            ])
            ->recordActions([
                ViewAction::make()
                    ->icon(Heroicon::OutlinedEye),
                EditAction::make()
                    ->icon(Heroicon::OutlinedPencilSquare),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('exportCsvController')
                        ->label('Exportar CSV')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
                        ->action(function (Collection $records) {
                            if ($records->isEmpty()) {
                                Notification::make()
                                    ->warning()
                                    ->title('Selecciona al menos una agencia de viaje')
                                    ->body('Marca los registros que deseas exportar o usa «Seleccionar todos» en la tabla.')
                                    ->send();

                                return;
                            }

                            $ids = $records->pluck('id')->all();
                            $token = TravelAgencyExportCsvController::storeIdsAndGetToken($ids);

                            return redirect()->route('business.travel-agencies.export-csv', ['token' => $token]);
                        }),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
