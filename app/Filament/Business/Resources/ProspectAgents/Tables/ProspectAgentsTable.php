<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\ProspectAgents\Tables;

use App\Filament\Business\Resources\ProspectAgents\ProspectAgentLabels;
use App\Models\ProspectAgent;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColumnGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProspectAgentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Prospectos')
            ->description('Embudo de captación: filtra por estatus o tipo y copia contactos con un clic.')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'state',
                'city',
                'country',
            ]))
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->deferFilters(false)
            ->filtersFormColumns(3)
            ->recordTitleAttribute('name')
            ->emptyStateHeading('Sin prospectos')
            ->emptyStateDescription('Aún no hay registros o no coinciden con los filtros aplicados.')
            ->emptyStateIcon(Heroicon::OutlinedUserPlus)
            ->columns([
                ColumnGroup::make('Prospecto', [
                    TextColumn::make('name')
                        ->label('Nombre')
                        ->icon(Heroicon::OutlinedUser)
                        ->weight('medium')
                        ->searchable()
                        ->sortable()
                        ->limit(40)
                        ->tooltip(fn (ProspectAgent $record): string => $record->name),
                    TextColumn::make('type')
                        ->label('Tipo')
                        ->badge()
                        ->color('gray')
                        ->searchable()
                        ->sortable()
                        ->formatStateUsing(fn (?string $state): string => ProspectAgentLabels::typeLabel($state)),
                    TextColumn::make('classification')
                        ->label('Clasificación')
                        ->icon(Heroicon::OutlinedTag)
                        ->searchable()
                        ->sortable()
                        ->limit(32)
                        ->tooltip(fn (ProspectAgent $record): string => (string) ($record->classification ?? ''))
                        ->placeholder('—')
                        ->toggleable(),
                    TextColumn::make('status')
                        ->label('Estatus')
                        ->badge()
                        ->searchable()
                        ->sortable()
                        ->formatStateUsing(fn (?string $state): string => ProspectAgentLabels::statusLabel($state))
                        ->color(fn (?string $state): string => ProspectAgentLabels::statusColor($state)),
                    TextColumn::make('initial_observ')
                        ->label('Observaciones iniciales')
                        ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                        ->limit(48)
                        ->tooltip(fn (ProspectAgent $record): string => (string) ($record->initial_observ ?? ''))
                        ->placeholder('—')
                        ->toggleable(isToggledHiddenByDefault: true),
                ]),
                ColumnGroup::make('Contacto', [
                    TextColumn::make('phone_1')
                        ->label('Teléfono principal')
                        ->icon(Heroicon::OutlinedPhone)
                        ->searchable()
                        ->copyable()
                        ->copyMessage('Teléfono copiado')
                        ->copyMessageDuration(1500)
                        ->placeholder('—'),
                    TextColumn::make('email')
                        ->label('Correo')
                        ->icon(Heroicon::OutlinedEnvelope)
                        ->searchable()
                        ->copyable()
                        ->copyMessage('Correo copiado')
                        ->copyMessageDuration(1500)
                        ->placeholder('—'),
                    TextColumn::make('instagram')
                        ->label('Instagram')
                        ->icon(Heroicon::OutlinedAtSymbol)
                        ->searchable()
                        ->copyable()
                        ->copyMessage('Usuario copiado')
                        ->copyMessageDuration(1500)
                        ->limit(28)
                        ->tooltip(fn (ProspectAgent $record): string => (string) ($record->instagram ?? ''))
                        ->placeholder('—')
                        ->toggleable(),
                    TextColumn::make('phone_2')
                        ->label('Teléfono alternativo')
                        ->icon(Heroicon::OutlinedPhone)
                        ->searchable()
                        ->copyable()
                        ->copyMessage('Teléfono copiado')
                        ->copyMessageDuration(1500)
                        ->placeholder('—')
                        ->toggleable(isToggledHiddenByDefault: true),
                ]),
                ColumnGroup::make('Ubicación y seguimiento', [
                    TextColumn::make('location')
                        ->label('Ubicación')
                        ->icon(Heroicon::OutlinedMapPin)
                        ->getStateUsing(function (ProspectAgent $record): string {
                            $parts = array_filter([
                                $record->city?->definition,
                                $record->state?->definition,
                                $record->country?->name,
                            ]);

                            return $parts !== [] ? implode(' · ', $parts) : '—';
                        })
                        ->placeholder('—')
                        ->toggleable(),
                    TextColumn::make('reference_by')
                        ->label('Referido por')
                        ->badge()
                        ->color('gray')
                        ->formatStateUsing(fn (?string $state): string => ProspectAgentLabels::referenceLabel($state))
                        ->toggleable(),
                    TextColumn::make('created_at')
                        ->label('Registro')
                        ->dateTime('d/m/Y H:i')
                        ->description(fn (ProspectAgent $record): string => $record->created_at->diffForHumans())
                        ->sortable(),
                ]),
                ColumnGroup::make('Auditoría', [
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
                    TextColumn::make('updated_at')
                        ->label('Última actualización')
                        ->dateTime('d/m/Y H:i')
                        ->description(fn (ProspectAgent $record): string => $record->updated_at->diffForHumans())
                        ->sortable()
                        ->toggleable(isToggledHiddenByDefault: true),
                ]),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estatus')
                    ->options(ProspectAgentLabels::statusOptions())
                    ->placeholder('Todos')
                    ->searchable(),
                SelectFilter::make('type')
                    ->label('Tipo')
                    ->options(ProspectAgentLabels::typeOptions())
                    ->placeholder('Todos')
                    ->searchable(),
                SelectFilter::make('reference_by')
                    ->label('Referido por')
                    ->options(ProspectAgentLabels::referenceOptions())
                    ->placeholder('Todos')
                    ->searchable(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Ver')
                    ->icon(Heroicon::OutlinedEye),
                EditAction::make()
                    ->label('Editar')
                    ->icon(Heroicon::OutlinedPencilSquare),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
