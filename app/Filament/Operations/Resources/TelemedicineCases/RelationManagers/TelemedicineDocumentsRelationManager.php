<?php

namespace App\Filament\Operations\Resources\TelemedicineCases\RelationManagers;

use App\Models\TelemedicineDocument;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Panel;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TelemedicineDocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'telemedicineDocuments';

    protected static ?string $title = 'Referencias médicas';

    protected static string|BackedEnum|null $icon = Heroicon::OutlinedDocumentDuplicate;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Documentos del caso')
            ->description('Archivos consignados por el agente. Las imágenes muestran vista previa; el resto, icono de documento.')
            ->emptyStateHeading('Sin documentos')
            ->emptyStateDescription('Aún no se han adjuntado archivos a este caso.')
            ->emptyStateIcon(Heroicon::OutlinedFolderOpen)
            ->defaultSort('created_at', 'desc')
            ->columns([
                Panel::make([
                    Stack::make([
                        ImageColumn::make('preview')
                            ->label('')
                            ->height(112)
                            ->width('100%')
                            ->extraImgAttributes([
                                'class' => 'rounded-lg object-cover ring-1 ring-gray-950/10 dark:ring-white/10',
                            ])
                            ->visibility('public')
                            ->visible(fn (?TelemedicineDocument $record): bool => $record !== null && self::isPreviewableImage((string) $record->name))
                            ->getStateUsing(function (?TelemedicineDocument $record): string {
                                if ($record === null) {
                                    return '';
                                }

                                return asset('storage/telemedicina-doc/'.$record->name);
                            })
                            ->openUrlInNewTab(),
                        TextColumn::make('name')
                            ->label('Archivo')
                            ->weight(FontWeight::SemiBold)
                            ->wrap()
                            ->searchable()
                            ->icon(fn (?TelemedicineDocument $record): Heroicon => $record !== null && self::isPreviewableImage((string) $record->name)
                                ? Heroicon::OutlinedPhoto
                                : Heroicon::OutlinedDocumentText)
                            ->iconColor('gray'),
                        TextColumn::make('created_at')
                            ->label('Registro')
                            ->dateTime('d/m/Y H:i')
                            ->description(fn (?TelemedicineDocument $record): string => $record?->created_at?->diffForHumans() ?? '')
                            ->color('gray')
                            ->size('sm'),
                    ])->space(3),
                ])
                    ->extraAttributes([
                        'class' => 'rounded-xl border border-gray-200 bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:border-white/10 dark:bg-gray-900 dark:ring-white/10',
                    ]),
            ])
            ->contentGrid([
                'sm' => 1,
                'md' => 2,
                'xl' => 3,
            ])
            ->recordActions([
                Action::make('download')
                    ->label('Descargar')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('success')
                    ->size('sm')
                    ->url(fn (?TelemedicineDocument $record): string => $record !== null
                        ? asset('storage/telemedicina-doc/'.$record->name)
                        : '#')
                    ->openUrlInNewTab(),
            ]);
    }

    private static function isPreviewableImage(string $filename): bool
    {
        $ext = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));

        return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'], true);
    }
}
