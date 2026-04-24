<?php

namespace App\Filament\General\Resources\DownloadZones\Tables;

use App\Models\DownloadZone;
use App\Support\DownloadZoneDocumentDownloader;
use Filament\Actions\Action;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DownloadZonesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Stack::make([
                    ImageColumn::make('image_icon')
                        ->imageWidth(250)
                        ->imageHeight(250),
                    Stack::make([
                        TextColumn::make('description')
                            ->weight(FontWeight::Bold),
                    ]),
                ])->space(3),
            ])
            ->contentGrid([
                'md' => 2,
                'xl' => 5,
            ])
            ->recordActions([
                Action::make('download')
                    ->label('Descargar')
                    ->icon('heroicon-o-folder-open')
                    ->color('verdeOpaco')
                    ->button()
                    ->action(fn (DownloadZone $record): ?\Symfony\Component\HttpFoundation\StreamedResponse => DownloadZoneDocumentDownloader::download(
                        $record,
                        'general',
                    )),
            ])
            ->toolbarActions([])
            ->striped();
    }
}
