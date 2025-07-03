<?php

namespace App\Filament\Agents\Resources\DownloadZones\Tables;

use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Stack;

class DownloadZonesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Stack::make([
                    ImageColumn::make('image_icon')
                        ->visibility('public'),
                    Stack::make([
                        TextColumn::make('description')
                            ->weight(FontWeight::Bold),
                    ]),
                ])->space(3),
            ])
            ->filters([
                //
            ])
            ->contentGrid([
                'md' => 2,
                'xl' => 3,
            ])
            ->recordActions([
                Action::make('download')
                    ->label('Descargar')
                    ->icon('heroicon-o-folder-open')
                    ->color('verde')
                    ->url(function ($record) {
                        return asset('storage/' . $record->document);
                    })
                    ->button()
                    ->openUrlInNewTab(),
            ])
            ->toolbarActions([
                
            ])->striped();
    }
}