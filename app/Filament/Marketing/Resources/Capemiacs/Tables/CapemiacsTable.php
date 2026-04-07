<?php

namespace App\Filament\Marketing\Resources\Capemiacs\Tables;

use App\Models\Capemiac;
use App\Models\DataNotification;
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
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class CapemiacsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->paginationPageOptions([10, 25, 50, 100])
            ->heading('Capemiac')
            ->description('Búsqueda global, columnas opcionales y edición en línea de teléfono y correo.')
            ->emptyStateHeading('Sin registros')
            ->emptyStateDescription('Importe o cree contactos para asociarlos a una notificación masiva.')
            ->emptyStateIcon(Heroicon::OutlinedRectangleStack)
            ->columns([
                TextColumn::make('cliente')
                    ->label('Cliente')
                    ->icon(Heroicon::OutlinedBuildingOffice2)
                    ->iconColor('primary')
                    ->weight('font-semibold')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->grow()
                    ->placeholder('—'),
                TextColumn::make('segmento')
                    ->label('Segmento')
                    ->icon(Heroicon::OutlinedSquares2x2)
                    ->iconColor('gray')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (?string $state): string => $state ? mb_strtoupper($state) : '—')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('rif')
                    ->label('RIF')
                    ->icon(Heroicon::OutlinedIdentification)
                    ->iconColor('gray')
                    ->badge()
                    ->color('info')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->copyable()
                    ->copyMessage('RIF copiado')
                    ->toggleable(),
                TextInputColumn::make('telefonoUno')
                    ->label('Teléfono')
                    ->type('tel')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->rules(['string', 'max:32']),
                TextInputColumn::make('email')
                    ->label('Correo')
                    ->type('email')
                    ->searchable()
                    ->sortable()
                    ->placeholder('correo@ejemplo.com')
                    ->rules(['email', 'max:255']),
                TextColumn::make('fecha_registro')
                    ->label('Fecha registro')
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->iconColor('gray')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Alta en sistema')
                    ->icon(Heroicon::OutlinedClock)
                    ->iconColor('gray')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->description(fn (Capemiac $record): ?string => $record->created_at?->diffForHumans()),
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
                        ->action(function (Collection $records, array $data): \Illuminate\Http\RedirectResponse {
                            $rows = $records->all();

                            foreach ($rows as $row) {
                                $dataInfo = new DataNotification;
                                $dataInfo->mass_notification_id = $data['mass_notification_id'];
                                $dataInfo->fullName = $row->cliente;
                                $dataInfo->email = $row->email;
                                $dataInfo->phone = $row->telefonoUno;
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
