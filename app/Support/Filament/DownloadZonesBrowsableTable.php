<?php

namespace App\Support\Filament;

use App\Models\DownloadZone;
use Closure;
use Filament\Actions\Action;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class DownloadZonesBrowsableTable
{
    /**
     * @param  Closure(Action): Action  $configureDownload
     */
    public static function configure(Table $table, Closure $configureDownload): Table
    {
        $download = Action::make('download')
            ->label('Descargar')
            ->icon('heroicon-o-folder-open')
            ->color('verdeOpaco')
            ->button();

        $download = $configureDownload($download);

        return $table
            ->columns([
                Stack::make([
                    ImageColumn::make('image_icon')
                        ->imageWidth(250)
                        ->imageHeight(250)
                        ->extraImgAttributes([
                            'class' => 'rounded-xl object-cover w-full',
                        ]),
                    TextColumn::make('description')
                        ->weight(FontWeight::Bold)
                        ->searchable()
                        ->wrap(),
                ])
                    ->space(3)
                    ->visible(fn ($livewire): bool => self::isGridView($livewire)),
                TextColumn::make('list_description')
                    ->label('Nombre')
                    ->state(fn (DownloadZone $record): string => (string) $record->description)
                    ->weight(FontWeight::Medium)
                    ->icon(Heroicon::OutlinedDocument)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where('description', 'like', "%{$search}%"))
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('description', $direction))
                    ->wrap()
                    ->visible(fn ($livewire): bool => ! self::isGridView($livewire)),
            ])
            ->contentGrid(fn ($livewire): ?array => self::isGridView($livewire)
                ? [
                    'md' => 2,
                    'xl' => 4,
                ]
                : null)
            ->recordActions([
                $download,
            ])
            ->toolbarActions([])
            ->striped(fn ($livewire): bool => ! self::isGridView($livewire));
    }

    private static function isGridView(mixed $livewire): bool
    {
        return ($livewire->documentsView ?? 'list') === 'grid';
    }
}
