<?php

namespace App\Filament\Business\Resources\AccountManagers\Tables;

use App\Models\AccountManager;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AccountManagersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->heading('Account managers')
            ->description('Ejecutivos vinculados al ecosistema. Busca por nombre, correo, teléfono o dirección; los contadores resumen agencias y agentes asignados.')
            ->emptyStateHeading('No hay account managers')
            ->emptyStateDescription('Crea el primero con «Crear Account Manager» en la parte superior.')
            ->recordTitle(fn (AccountManager $record): string => $record->full_name)
            ->columns([
                TextColumn::make('user_id')
                    ->label('ID IntegraCorp')
                    ->icon('heroicon-m-finger-print')
                    ->badge()
                    ->color('primary')
                    ->sortable()
                    ->alignCenter(),
                TextColumn::make('full_name')
                    ->label('Nombre completo')
                    ->icon('heroicon-m-user')
                    ->weight(FontWeight::SemiBold)
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->extraCellAttributes(fn (): array => [
                        'class' => 'min-w-44 sm:min-w-56',
                    ]),
                TextColumn::make('email')
                    ->label('Correo')
                    ->icon('heroicon-m-envelope')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Correo copiado al portapapeles')
                    ->copyMessageDuration(2500)
                    ->limit(32)
                    ->tooltip(fn (AccountManager $record): string => $record->email),
                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->icon('heroicon-m-phone')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Teléfono copiado')
                    ->copyMessageDuration(2500)
                    ->fontFamily(FontFamily::Mono),
                TextColumn::make('address')
                    ->label('Dirección')
                    ->icon('heroicon-m-map-pin')
                    ->searchable()
                    ->wrap()
                    ->lineClamp(2)
                    ->tooltip(fn (AccountManager $record): string => $record->address),
                TextColumn::make('agencies_count')
                    ->label('Agencias')
                    ->icon('heroicon-m-building-office-2')
                    ->counts('agencies')
                    ->alignCenter()
                    ->badge()
                    ->color(fn (?int $state): string => ($state ?? 0) > 0 ? 'success' : 'gray')
                    ->sortable(),
                TextColumn::make('agents_count')
                    ->label('Agentes')
                    ->icon('heroicon-m-user-group')
                    ->counts('agents')
                    ->alignCenter()
                    ->badge()
                    ->color(fn (?int $state): string => ($state ?? 0) > 0 ? 'info' : 'gray')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Alta')
                    ->icon('heroicon-m-calendar-days')
                    ->description(fn (AccountManager $record): string => $record->created_at->diffForHumans())
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('creator.name')
                    ->label('Creado por')
                    ->icon('heroicon-m-user-circle')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Última actualización')
                    ->icon('heroicon-m-arrow-path')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('with_agencies')
                    ->label('Agencias')
                    ->placeholder('Todas las filas')
                    ->trueLabel('Con al menos una agencia')
                    ->falseLabel('Sin agencias')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereHas('agencies'),
                        false: fn (Builder $query): Builder => $query->whereDoesntHave('agencies'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                TernaryFilter::make('with_agents')
                    ->label('Agentes')
                    ->placeholder('Todas las filas')
                    ->trueLabel('Con al menos un agente')
                    ->falseLabel('Sin agentes')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereHas('agents'),
                        false: fn (Builder $query): Builder => $query->whereDoesntHave('agents'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Ver')
                    ->icon('heroicon-m-eye'),
                EditAction::make()
                    ->label('Editar')
                    ->icon('heroicon-m-pencil-square'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->striped();
    }
}
