<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\GuiaChatFeedbacks\Schemas;

use App\Models\GuiaChatFeedback;
use App\Support\GuiaChat\GuiaChatFeedbackType;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class GuiaChatFeedbackInfolist
{
    private const TABS_CONTAINER = 'rounded-[1.75rem] border border-slate-200/85 bg-gradient-to-br from-white via-slate-50/90 to-white p-2 shadow-[0_24px_60px_-26px_rgba(15,23,42,0.2)] ring-1 ring-slate-200/55 dark:border-white/10 dark:from-slate-900/95 dark:via-slate-950/95 dark:to-slate-900/95 dark:ring-white/10 dark:shadow-[0_24px_60px_-24px_rgba(0,0,0,0.55)]';

    private const IOS_SECTION_CLASS = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    private const IOS_INNER_CLASS = 'rounded-[1.25rem] border border-slate-200/80 bg-white/80 p-4 shadow-inner dark:border-white/10 dark:bg-white/5 sm:p-5';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('guiaChatFeedbackInfolistTabs')
                    ->columnSpanFull()
                    ->persistTab()
                    ->extraAttributes([
                        'class' => self::TABS_CONTAINER,
                    ])
                    ->tabs([
                        Tab::make('Resumen')
                            ->icon(Heroicon::OutlinedClipboardDocumentList)
                            ->schema([
                                Section::make('Resumen del registro')
                                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                                    ->description('Tipo de entrada, fecha de envío y datos del reportante cuando aplica.')
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(1)
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                Grid::make()
                                                    ->columns(['default' => 1, 'lg' => 2])
                                                    ->schema([
                                                        TextEntry::make('type')
                                                            ->label('Tipo de registro')
                                                            ->formatStateUsing(fn (string $state): string => GuiaChatFeedbackType::tryFromString($state)?->label() ?? $state)
                                                            ->icon(fn (string $state): Heroicon => self::typeIcon($state))
                                                            ->badge()
                                                            ->color(fn (string $state): string => GuiaChatFeedbackType::tryFromString($state)?->filamentColor() ?? 'gray')
                                                            ->columnSpan(['default' => 1, 'lg' => 2]),
                                                        TextEntry::make('created_at')
                                                            ->label('Fecha de envío')
                                                            ->icon(Heroicon::OutlinedCalendarDays)
                                                            ->dateTime('d/m/Y H:i')
                                                            ->helperText(fn (GuiaChatFeedback $record): ?string => $record->created_at?->diffForHumans())
                                                            ->placeholder('—'),
                                                        TextEntry::make('reporter_full_name')
                                                            ->label('Reportado por')
                                                            ->icon(Heroicon::OutlinedUser)
                                                            ->state(fn (GuiaChatFeedback $record): ?string => $record->requiresReporterName()
                                                                ? ($record->reporterFullName() ?? 'Sin nombre')
                                                                : null)
                                                            ->badge(fn (GuiaChatFeedback $record): bool => $record->requiresReporterName())
                                                            ->color(fn (GuiaChatFeedback $record): string => match (true) {
                                                                ! $record->requiresReporterName() => 'gray',
                                                                filled($record->reporterFullName()) => 'primary',
                                                                default => 'warning',
                                                            })
                                                            ->visible(fn (GuiaChatFeedback $record): bool => $record->requiresReporterName())
                                                            ->weight('semibold')
                                                            ->columnSpan(['default' => 1, 'lg' => 2])
                                                            ->placeholder('—'),
                                                        TextEntry::make('reporter_first_name')
                                                            ->label('Nombre')
                                                            ->icon(Heroicon::OutlinedUser)
                                                            ->visible(fn (GuiaChatFeedback $record): bool => $record->requiresReporterName())
                                                            ->placeholder('—'),
                                                        TextEntry::make('reporter_last_name')
                                                            ->label('Apellido')
                                                            ->icon(Heroicon::OutlinedUserCircle)
                                                            ->visible(fn (GuiaChatFeedback $record): bool => $record->requiresReporterName())
                                                            ->placeholder('—'),
                                                    ]),
                                            ]),
                                    ]),
                            ]),
                        Tab::make('Contenido')
                            ->icon(Heroicon::OutlinedChatBubbleBottomCenterText)
                            ->schema([
                                Section::make('Mensaje enviado')
                                    ->icon(Heroicon::OutlinedChatBubbleBottomCenterText)
                                    ->description('Texto completo recibido desde el chat público.')
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(1)
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                TextEntry::make('message')
                                                    ->label('Mensaje')
                                                    ->prose()
                                                    ->columnSpanFull(),
                                            ]),
                                    ]),
                            ]),
                        Tab::make('Trazabilidad')
                            ->icon(Heroicon::OutlinedFingerPrint)
                            ->schema([
                                Section::make('Trazabilidad técnica')
                                    ->icon(Heroicon::OutlinedFingerPrint)
                                    ->description('Contexto de sesión y datos técnicos del envío.')
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(1)
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                Grid::make()
                                                    ->columns(['default' => 1, 'lg' => 2])
                                                    ->schema([
                                                        TextEntry::make('public_token')
                                                            ->label('Token de sesión')
                                                            ->icon(Heroicon::OutlinedFingerPrint)
                                                            ->copyable()
                                                            ->copyMessage('Token copiado')
                                                            ->placeholder('—'),
                                                        TextEntry::make('ip_address')
                                                            ->label('Dirección IP')
                                                            ->icon(Heroicon::OutlinedGlobeAlt)
                                                            ->copyable()
                                                            ->copyMessage('IP copiada')
                                                            ->placeholder('—'),
                                                        TextEntry::make('user_agent')
                                                            ->label('Navegador / dispositivo')
                                                            ->icon(Heroicon::OutlinedComputerDesktop)
                                                            ->columnSpanFull()
                                                            ->placeholder('—'),
                                                        TextEntry::make('updated_at')
                                                            ->label('Última actualización')
                                                            ->icon(Heroicon::OutlinedArrowPath)
                                                            ->dateTime('d/m/Y H:i')
                                                            ->placeholder('—'),
                                                    ]),
                                            ]),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    private static function typeIcon(string $state): Heroicon
    {
        return match (GuiaChatFeedbackType::tryFromString($state)) {
            GuiaChatFeedbackType::ServiceSuggestion => Heroicon::OutlinedLightBulb,
            GuiaChatFeedbackType::GuiaChatBug => Heroicon::OutlinedBugAnt,
            GuiaChatFeedbackType::IntegracorpBug => Heroicon::OutlinedExclamationTriangle,
            default => Heroicon::OutlinedChatBubbleLeftRight,
        };
    }
}
