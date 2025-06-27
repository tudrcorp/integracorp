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

class DetailsDataRelationManager extends RelationManager
{
    protected static string $relationship = 'detailsData';

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
                ImportAction::make()
                    ->importer(CorporateQuoteRequestDataImporter::class)
                    ->label('Importar CSV')
                    ->color('verde')
                    ->outlined()
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