<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\IndividualQuotes\Schemas;

use App\Models\IndividualQuote;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class IndividualQuoteInfolist
{
    private const TABS_CONTAINER = 'rounded-[1.75rem] border border-slate-200/85 bg-gradient-to-br from-white via-slate-50/90 to-white p-2 shadow-[0_24px_60px_-26px_rgba(15,23,42,0.2)] ring-1 ring-slate-200/55 dark:border-white/10 dark:from-slate-900/95 dark:via-slate-950/95 dark:to-slate-900/95 dark:ring-white/10 dark:shadow-[0_24px_60px_-24px_rgba(0,0,0,0.55)]';

    private const IOS_SECTION_CLASS = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    private const IOS_INNER_CLASS = 'rounded-[1.25rem] border border-slate-200/80 bg-white/80 p-4 shadow-inner dark:border-white/10 dark:bg-white/5 sm:p-5';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('individualQuoteInfolistTabs')
                    ->columnSpanFull()
                    ->persistTab()
                    ->extraAttributes([
                        'class' => self::TABS_CONTAINER,
                    ])
                    ->tabs([
                        Tab::make('Cotización')
                            ->icon(Heroicon::OutlinedClipboardDocumentList)
                            ->schema([
                                Section::make('Solicitud de cotización individual')
                                    ->description(fn (IndividualQuote $record): string => 'Cotización individual generada el '.$record->created_at->format('d/m/Y H:i'))
                                    ->icon(Heroicon::OutlinedDocumentText)
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(1)
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                Grid::make(['default' => 1, 'sm' => 2, 'lg' => 5])
                                                    ->schema([
                                                        TextEntry::make('code')
                                                            ->label('Número de solicitud')
                                                            ->badge()
                                                            ->color('success'),
                                                        TextEntry::make('code_agency')
                                                            ->label('Código de agencia')
                                                            ->badge()
                                                            ->color('primary'),
                                                        TextEntry::make('registrated_by')
                                                            ->label('Registrado por')
                                                            ->badge()
                                                            ->color('primary')
                                                            ->default(fn (IndividualQuote $record): string => 'AGT-000'.$record->agent_id.' : '.$record->full_name),
                                                        TextEntry::make('created_at')
                                                            ->label('Fecha de solicitud')
                                                            ->badge()
                                                            ->dateTime(),
                                                    ]),
                                            ]),
                                    ]),
                            ]),
                        Tab::make('Solicitante')
                            ->icon(Heroicon::OutlinedUser)
                            ->schema([
                                Section::make('Información del solicitante')
                                    ->icon(Heroicon::OutlinedUserCircle)
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(1)
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                Grid::make(['default' => 1, 'sm' => 2, 'lg' => 5])
                                                    ->schema([
                                                        TextEntry::make('full_name')
                                                            ->label('Nombre completo'),
                                                        TextEntry::make('email')
                                                            ->label('Correo electrónico'),
                                                        TextEntry::make('phone')
                                                            ->label('Número de teléfono'),
                                                        TextEntry::make('status')
                                                            ->label('Estatus')
                                                            ->badge()
                                                            ->color('success'),
                                                        TextEntry::make('created_by')
                                                            ->label('Registrado por')
                                                            ->badge()
                                                            ->color('primary'),
                                                    ]),
                                            ]),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
