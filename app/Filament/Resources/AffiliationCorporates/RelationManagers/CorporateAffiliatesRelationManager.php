<?php

namespace App\Filament\Resources\AffiliationCorporates\RelationManagers;

use BackedEnum;
use Filament\Tables\Table;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\ImportAction;
use Filament\Support\Icons\Heroicon;
use Filament\Actions\BulkActionGroup;
use Illuminate\Validation\Rules\File;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use App\Filament\Imports\AffiliateCorporateImporter;
use Filament\Resources\RelationManagers\RelationManager;
use App\Filament\Imports\CorporateQuoteRequestDataImporter;
use App\Filament\Resources\AffiliationCorporates\AffiliationCorporateResource;

class CorporateAffiliatesRelationManager extends RelationManager
{
    protected static string $relationship = 'corporateAffiliates';

    protected static ?string $title = 'Poblacion';

    protected static string|BackedEnum|null $icon = 'heroicon-s-user-group';

    public function table(Table $table): Table
    {
        return $table
            ->heading('EMPLEADOS ASOCIADOS')
            ->description('Lista de empleados afiliados')
            ->recordTitleAttribute('affiliation_corporate_id')
            ->columns([
                TextColumn::make('first_name')
                    ->label('Nombre')
                    ->searchable(),
                TextColumn::make('last_name')
                    ->label('Apellido')
                    ->searchable(),
                TextColumn::make('birth_date')
                    ->label('Fecha de nacimiento')
                    ->searchable(),
                TextColumn::make('nro_identificacion')
                    ->label('C.I.'),
                TextColumn::make('email')
                    ->label('Email'),
                TextColumn::make('age')
                    ->label('Edad')
                    ->searchable(),
                TextColumn::make('sex')
                    ->label('Sexo'),
                TextColumn::make('phone')
                    ->label('Telefono'),
                TextColumn::make('condition_medical')
                    ->label('Condicion Medica'),
                TextColumn::make('initial_date')
                    ->label('Fecha de Ingreso'),
                TextColumn::make('address')
                    ->label('Direccion'),
                TextColumn::make('full_name_emergency')
                    ->label('Contacto de Emergencia'),
                TextColumn::make('phone_emergency')
                    ->label('Telefono de Emergencia'),
            ])
            ->headerActions([
                /**Importar data de poblacion */
                ImportAction::make()
                    ->importer(AffiliateCorporateImporter::class)
                    ->label('Importar CSV(Población)')
                    ->color('warning')
                    ->icon('heroicon-s-cloud-arrow-up')
                    ->options(function (RelationManager $livewire) {
                        return [
                            'affiliation_corporate_id' => $livewire->ownerRecord->id,
                        ];
                    })
                    ->fileRules([
                        File::types(['csv', 'txt'])->max(1024),
                    ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->modalHeading('INTEGRACORP eliminar el/los registros seleccionados!')
                        ->requiresConfirmation()
                        ->color('danger')
                        ->icon(Heroicon::Trash),
                        
                ]),
            ])->striped()->poll('5s');
    }
}