<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\Helpdesks\Schemas;

use App\Models\HelpDesk;
use App\Support\HelpdeskObservationHtmlRenderer;
use App\Support\HelpdeskTaskStatusOptions;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
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
                    ->hidden(fn (?HelpDesk $record): bool => blank($record?->image) || ! Storage::disk('public')->exists((string) $record->image))
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
                    ])
                    ->schema([
                        ImageEntry::make('image')
                            ->label('Vista previa')
                            ->disk('public')
                            ->visibility('public')
                            ->imageHeight(280)
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
