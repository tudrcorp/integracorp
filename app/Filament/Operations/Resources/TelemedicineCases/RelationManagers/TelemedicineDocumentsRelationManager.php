<?php

namespace App\Filament\Operations\Resources\TelemedicineCases\RelationManagers;

use BackedEnum;
use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

use Filament\Actions\Action;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Stack;

class TelemedicineDocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'telemedicineDocuments';

    protected static ?string $title = 'Referencia Medicas';

    protected static string|BackedEnum|null $icon = 'heroicon-c-hand-raised';


    public function table(Table $table): Table
    {
        return $table
            ->heading('Documentos consignados del agente')
            ->columns([
                Stack::make([
                    ImageColumn::make('image')
                        ->imageHeight(100)
                        ->square()
                        ->visibility('public'),
                    Stack::make([
                        TextColumn::make('name')
                            ->weight(FontWeight::Bold),
                    ]),
                ])->space(3),
            ])
            ->filters([
                //
            ])
            ->contentGrid([
                'sm' => 1,
                'md' => 2,
                'xl' => 5,
            ])
            ->recordActions([
                Action::make('download')
                    ->label('Descargar')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('verde')
                    ->url(function ($record) {
                        return asset('storage/telemedicina-doc/' . $record->name);
                    })
                    ->button()
                    ->openUrlInNewTab(),
            ]);
    }
}