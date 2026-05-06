<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\Helpdesks\Schemas;

use App\Models\HelpDesk;
use App\Support\HelpdeskDocumentPaths;
use App\Support\HelpdeskObservationHtmlRenderer;
use App\Support\HelpdeskTaskStatusOptions;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

final class HelpdeskInfolist
{
    private const IOS_SECTION_CLASS = 'fi-helpdesk-ios-section';

    private const IOS_INNER_CLASS = 'fi-helpdesk-ios-inset';

    private static function priorityColor(?string $state): string
    {
        return match ($state) {
            'BAJA' => 'success',
            'MEDIA' => 'warning',
            'ALTA' => 'danger',
            default => 'gray',
        };
    }

    private static function statusColor(?string $state): string
    {
        return match ($state) {
            'PENDIENTE POR INICIAR' => 'warning',
            'EN PROCESO' => 'primary',
            'TERMINADO' => 'success',
            'CANCELADO' => 'danger',
            default => 'gray',
        };
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Ticket de soporte')
                    ->description('Resumen del caso, prioridad y estado.')
                    ->icon('heroicon-o-ticket')
                    ->iconColor('info')
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
                    ])
                    ->schema([
                        Grid::make(1)
                            ->extraAttributes([
                                'class' => self::IOS_INNER_CLASS,
                            ])
                            ->schema([
                                TextEntry::make('id')
                                    ->label('N.º ticket')
                                    ->icon('heroicon-m-hashtag')
                                    ->weight('semibold'),
                                TextEntry::make('description')
                                    ->label('Descripción')
                                    ->icon('heroicon-m-document-text')
                                    ->columnSpanFull()
                                    ->placeholder('—'),
                                Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3])
                                    ->schema([
                                        TextEntry::make('priority')
                                            ->label('Prioridad')
                                            ->icon('heroicon-m-bolt')
                                            ->badge()
                                            ->color(fn (?string $state): string => self::priorityColor($state))
                                            ->formatStateUsing(fn (?string $state): ?string => match ($state) {
                                                'BAJA' => 'Baja',
                                                'MEDIA' => 'Media',
                                                'ALTA' => 'Alta',
                                                default => $state,
                                            }),
                                        TextEntry::make('status')
                                            ->label('Estado')
                                            ->icon('heroicon-m-flag')
                                            ->badge()
                                            ->color(fn (?string $state): string => self::statusColor($state))
                                            ->formatStateUsing(fn (?string $state): ?string => $state !== null && $state !== ''
                                                ? (HelpdeskTaskStatusOptions::all()[$state] ?? $state)
                                                : null),
                                        TextEntry::make('rrhhColaboradores.fullName')
                                            ->label('Asignados')
                                            ->icon('heroicon-m-user')
                                            ->listWithLineBreaks()
                                            ->placeholder('Sin asignar'),
                                        TextEntry::make('created_by')
                                            ->label('Creado por')
                                            ->icon('heroicon-m-user-circle'),
                                        TextEntry::make('updated_by')
                                            ->label('Última modificación por')
                                            ->icon('heroicon-m-pencil-square')
                                            ->placeholder('—'),
                                        TextEntry::make('created_at')
                                            ->label('Creado')
                                            ->icon('heroicon-m-calendar')
                                            ->dateTime('d/m/Y H:i'),
                                        TextEntry::make('updated_at')
                                            ->label('Actualizado')
                                            ->icon('heroicon-m-calendar-days')
                                            ->dateTime('d/m/Y H:i'),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Adjunto')
                    ->description('Archivo enviado con el ticket (imagen o PDF).')
                    ->icon('heroicon-o-paper-clip')
                    ->iconColor('warning')
                    ->hidden(function (?HelpDesk $record): bool {
                        if (! $record instanceof HelpDesk) {
                            return true;
                        }

                        $disk = Storage::disk('public');
                        foreach (HelpdeskDocumentPaths::paths($record) as $path) {
                            if ($disk->exists($path)) {
                                return false;
                            }
                        }

                        return true;
                    })
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
                    ])
                    ->schema([
                        TextEntry::make('image')
                            ->label('Descargar')
                            ->icon('heroicon-m-arrow-down-tray')
                            ->html()
                            ->formatStateUsing(function (?string $state, HelpDesk $record): HtmlString {
                                $disk = Storage::disk('public');
                                $paths = HelpdeskDocumentPaths::paths($record);

                                $resolvedPath = null;
                                $resolvedIndex = null;
                                foreach ($paths as $index => $path) {
                                    $path = trim($path);
                                    if ($path !== '' && $disk->exists($path)) {
                                        $resolvedPath = $path;
                                        $resolvedIndex = $index;
                                        break;
                                    }
                                }

                                if ($resolvedPath === null || $resolvedIndex === null) {
                                    return new HtmlString('<span class="text-gray-500 dark:text-gray-400">—</span>');
                                }

                                $downloadUrl = Route::has('helpdesks.attachments.download')
                                    ? route('helpdesks.attachments.download', ['helpDesk' => $record->getKey(), 'index' => $resolvedIndex])
                                    : $disk->url($resolvedPath);

                                return new HtmlString(
                                    '<a href="'.e($downloadUrl)
                                    .'" class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-xs font-semibold text-gray-600 hover:text-gray-900 hover:bg-gray-100/70 dark:text-gray-300 dark:hover:text-white dark:hover:bg-white/10 transition">'
                                    .'<span class="inline-flex h-5 w-5 items-center justify-center rounded-md bg-gray-100 text-gray-600 ring-1 ring-black/5 dark:bg-white/10 dark:text-gray-200 dark:ring-white/10">↓</span>'
                                    .'<span class="max-w-[16rem] truncate">Descargar '.e(basename($resolvedPath)).'</span>'
                                    .'</a>'
                                );
                            })
                            ->columnSpanFull(),
                        ImageEntry::make('image')
                            ->label('Vista previa')
                            ->disk('public')
                            ->visibility('public')
                            ->imageHeight(280)
                            ->hidden(function (?HelpDesk $record): bool {
                                if (! $record instanceof HelpDesk) {
                                    return true;
                                }

                                $paths = HelpdeskDocumentPaths::paths($record);
                                $firstPath = isset($paths[0]) ? trim((string) $paths[0]) : '';
                                if ($firstPath === '') {
                                    return true;
                                }

                                $ext = strtolower((string) pathinfo($firstPath, PATHINFO_EXTENSION));

                                return ! in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
                            })
                            ->columnSpanFull()
                            ->extraImgAttributes([
                                'class' => 'rounded-xl shadow-sm ring-1 ring-black/5 dark:ring-white/10',
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Notas y seguimiento')
                    ->description('Historial interno con formato enriquecido.')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->iconColor('success')
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
                    ])
                    ->schema([
                        TextEntry::make('observation')
                            ->label('Observaciones')
                            ->columnSpanFull()
                            ->prose()
                            ->formatStateUsing(function (?string $state): HtmlString {
                                $raw = trim((string) ($state ?? ''));
                                if ($raw === '') {
                                    return new HtmlString('<span class="text-gray-500 dark:text-gray-400">Sin notas registradas.</span>');
                                }

                                return new HtmlString(HelpdeskObservationHtmlRenderer::render($raw));
                            }),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
