<?php

namespace App\Filament\Resources\Logs\Tables;

use Carbon\Carbon;
use Filament\Tables\Table;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Filters\Filter;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;

class LogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
        ->defaultSort('created_at', 'desc')
            ->heading('LOGs DEL SISTEMA')
            ->description('Lista de acciones, execuciones y cambios realizadas dentro del sistema')
            ->columns([
                TextColumn::make('user.name')
                    ->badge()
                    ->color('azulOscuro')
                    ->searchable(),
                TextColumn::make('action')
                    ->label('Accion realizada')
                    ->searchable(),
                TextColumn::make('method')
                    ->label('Metodo')
                    ->searchable(),
                TextColumn::make('route')
                    ->label('Ruta de ejecucion')
                    ->searchable(),
                TextColumn::make('ip')
                    ->label('Direccion IP')
                    ->searchable(),
                TextColumn::make('user_agent')
                    ->label('Navegador del usuario')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('created_at')
                    ->form([
                        DatePicker::make('desde'),
                        DatePicker::make('hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['desde'] ?? null,
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['hasta'] ?? null,
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['desde'] ?? null) {
                            $indicators['desde'] = 'Venta desde ' . Carbon::parse($data['desde'])->toFormattedDateString();
                        }
                        if ($data['hasta'] ?? null) {
                            $indicators['hasta'] = 'Venta hasta ' . Carbon::parse($data['hasta'])->toFormattedDateString();
                        }

                        return $indicators;
                    }),
            ])
            ->recordActions([

            ])
            ->toolbarActions([

            ]);
    }
}