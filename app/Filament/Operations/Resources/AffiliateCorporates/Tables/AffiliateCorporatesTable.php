<?php

namespace App\Filament\Operations\Resources\AffiliateCorporates\Tables;

use App\Filament\Exports\AffiliateCorporateExporter;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AffiliateCorporatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->heading('LISTA DE AFILIADOS CORPORATIVOS')
            ->description('A continuacion se muestra la lista de afiliados corporativos. La tabla esta ordenada por fecha de registro de forma descendente, los mas recientes se muestran primero')
            ->columns([
                TextColumn::make('first_name')
                    ->color('info')
                    ->badge()
                    ->icon('heroicon-s-user')
                    ->label('Nombre y Apellido')
                    ->searchable(),
                TextColumn::make('nro_identificacion')
                    ->color('info')
                    ->badge()
                    ->icon('heroicon-s-identification')
                    ->label('Nro Identificacion')
                    ->searchable(),
                TextColumn::make('birth_date')
                    ->label('Fecha Nacimiento')
                    ->searchable(),
                TextColumn::make('age')
                    ->label('Edad')
                    ->searchable(),
                TextColumn::make('sex')
                    ->label('Sexo')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Telefono')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                TextColumn::make('full_name_emergency')
                    ->label('Nombre Emergencia')
                    ->searchable(),
                TextColumn::make('phone_emergency')
                    ->label('Telefono Emergencia')
                    ->searchable(),
                TextColumn::make('plan.description')
                    ->label('Plan')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('coverage.price')
                    ->label('Cobertura')
                    ->prefix('$')
                    ->numeric()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make()
                ->icon('heroicon-o-eye')
                ->color('info')
                ->label('Ver Detalles'),
                // EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()->exporter(AffiliateCorporateExporter::class)->label('Exportar XLS')->color('info')->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }
}
