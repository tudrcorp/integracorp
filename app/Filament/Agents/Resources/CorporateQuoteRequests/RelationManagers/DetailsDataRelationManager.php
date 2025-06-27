<?php

namespace App\Filament\Agents\Resources\CorporateQuoteRequests\RelationManagers;

use Filament\Tables\Table;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Illuminate\Validation\Rules\File;
use Filament\Tables\Columns\TextColumn;
use Filament\Resources\RelationManagers\RelationManager;
use App\Filament\Imports\CorporateQuoteRequestDataImporter;
use App\Filament\Agents\Resources\CorporateQuoteRequests\CorporateQuoteRequestResource;
use BackedEnum;

class DetailsDataRelationManager extends RelationManager
{
    protected static string $relationship = 'detailsData';

    protected static ?string $title = 'POBLACIÓN';

    protected static string|BackedEnum|null $icon = 'heroicon-s-user-plus';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('full_name')
                    ->label('Nombre completo')
                    ->searchable(),
                TextColumn::make('birth_date')
                    ->label('Fecha de nacimiento')
                    ->searchable(),
                TextColumn::make('age')
                    ->label('Edad')
                    ->searchable(),

            ])
            ->headerActions([
                /**Importar data de poblacion */
                ImportAction::make()
                    ->importer(CorporateQuoteRequestDataImporter::class)
                    ->label('Importar CSV(Población)')
                    ->color('warning')
                    ->icon('heroicon-s-cloud-arrow-up')
                    ->options(function (RelationManager $livewire) {
                        return [
                            'corporate_quote_request_id' => $livewire->ownerRecord->id,
                        ];
                    })
                    ->fileRules([
                        File::types(['csv', 'txt'])->max(1024),
                    ]),
            ]);
    }
}