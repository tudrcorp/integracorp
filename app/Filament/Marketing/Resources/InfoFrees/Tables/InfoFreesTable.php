<?php

namespace App\Filament\Marketing\Resources\InfoFrees\Tables;

use App\Models\DataNotification;
use App\Models\InfoFree;
use App\Models\MassNotification;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Fieldset;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class InfoFreesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->paginationPageOptions([10, 25, 50, 100])
            ->heading('Data externa (FREE)')
            ->description('Contactos captados fuera del flujo principal: búsqueda, columnas opcionales y asociación masiva a notificaciones.')
            ->emptyStateHeading('Sin registros')
            ->emptyStateDescription('Los contactos importados o creados aparecerán aquí para revisión y asociación a campañas.')
            ->emptyStateIcon(Heroicon::OutlinedUserGroup)
            ->columns([
                TextColumn::make('fullName')
                    ->label('Nombre completo')
                    ->icon(Heroicon::OutlinedUser)
                    ->iconColor('primary')
                    ->weight('font-semibold')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->grow()
                    ->placeholder('—'),
                TextColumn::make('email')
                    ->label('Correo')
                    ->icon(Heroicon::OutlinedEnvelope)
                    ->iconColor('gray')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->copyable()
                    ->copyMessage('Correo copiado')
                    ->limit(32)
                    ->tooltip(fn (InfoFree $record): ?string => $record->email),
                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->icon(Heroicon::OutlinedPhone)
                    ->iconColor('gray')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->copyable()
                    ->copyMessage('Teléfono copiado')
                    ->toggleable(),
                TextColumn::make('sex')
                    ->label('Sexo')
                    ->icon(Heroicon::OutlinedUserCircle)
                    ->iconColor('gray')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (?string $state): string => $state ? mb_strtoupper($state) : '—')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('address')
                    ->label('Dirección')
                    ->icon(Heroicon::OutlinedMapPin)
                    ->iconColor('gray')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('city')
                    ->label('Ciudad')
                    ->icon(Heroicon::OutlinedBuildingOffice2)
                    ->iconColor('gray')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('country')
                    ->label('País')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('state')
                    ->label('Estado / provincia')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('region')
                    ->label('Región')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Alta en sistema')
                    ->icon(Heroicon::OutlinedClock)
                    ->iconColor('gray')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->description(fn (InfoFree $record): ?string => $record->created_at?->diffForHumans()),
                TextColumn::make('updated_at')
                    ->label('Última edición')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->iconColor('gray')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Ver'),
                EditAction::make()
                    ->label('Editar'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('associateInfo')
                        ->label('Asociar información')
                        ->icon('heroicon-s-link')
                        ->form([
                            Fieldset::make('Asociar a notificación masiva')
                                ->columns(1)
                                ->schema([
                                    Select::make('mass_notification_id')
                                        ->label('Notificación')
                                        ->options(fn (): array => MassNotification::query()->orderBy('title')->pluck('title', 'id')->all())
                                        ->searchable()
                                        ->preload()
                                        ->required()
                                        ->native(false),
                                ]),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $rows = $records->all();

                            foreach ($rows as $row) {
                                $dataInfo = new DataNotification;
                                $dataInfo->mass_notification_id = $data['mass_notification_id'];
                                $dataInfo->fullName = $row->fullName;
                                $dataInfo->email = $row->email;
                                $dataInfo->phone = $row->phone;
                                $dataInfo->save();
                            }

                            Notification::make()
                                ->title('Información asociada')
                                ->body(count($rows).' '.(count($rows) === 1 ? 'registro asociado' : 'registros asociados').' correctamente.')
                                ->success()
                                ->send();

                            return redirect()->route('filament.marketing.resources.mass-notifications.view', ['record' => $data['mass_notification_id']]);
                        })
                        ->requiresConfirmation()
                        ->modalHeading('Asociar a notificación')
                        ->modalDescription('Los contactos seleccionados se vincularán a la notificación masiva elegida.')
                        ->modalSubmitActionLabel('Asociar')
                        ->color('primary'),
                ]),
            ]);
    }
}
