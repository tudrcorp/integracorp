<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\PlanGeneratorImages\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PlanGeneratorImagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Stack::make([
                    ImageColumn::make('image_path')
                        ->label('Vista previa')
                        ->disk('public')
                        ->imageWidth(220)
                        ->imageHeight(160)
                        ->extraImgAttributes([
                            'class' => 'rounded-lg object-cover',
                        ]),
                    Stack::make([
                        TextColumn::make('name')
                            ->weight(FontWeight::Bold)
                            ->searchable(),
                        TextColumn::make('created_by')
                            ->label('Cargada por')
                            ->placeholder('—')
                            ->color('gray'),
                        TextColumn::make('created_at')
                            ->label('Fecha')
                            ->dateTime('d/m/Y H:i')
                            ->color('gray'),
                    ])->space(1),
                ])->space(3),
            ])
            ->contentGrid([
                'md' => 2,
                'xl' => 4,
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
