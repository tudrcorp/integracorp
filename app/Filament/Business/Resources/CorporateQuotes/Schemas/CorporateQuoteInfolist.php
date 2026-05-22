<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\CorporateQuotes\Schemas;

use App\Models\CorporateQuote;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class CorporateQuoteInfolist
{
    private const TABS_CONTAINER = 'rounded-[1.75rem] border border-slate-200/85 bg-gradient-to-br from-white via-slate-50/90 to-white p-2 shadow-[0_24px_60px_-26px_rgba(15,23,42,0.2)] ring-1 ring-slate-200/55 dark:border-white/10 dark:from-slate-900/95 dark:via-slate-950/95 dark:to-slate-900/95 dark:ring-white/10 dark:shadow-[0_24px_60px_-24px_rgba(0,0,0,0.55)]';

    private const IOS_SECTION_CLASS = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    private const IOS_INNER_CLASS = 'rounded-[1.25rem] border border-slate-200/80 bg-white/80 p-4 shadow-inner dark:border-white/10 dark:bg-white/5 sm:p-5';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('corporateQuoteInfolistTabs')
                    ->columnSpanFull()
                    ->persistTab()
                    ->extraAttributes([
                        'class' => self::TABS_CONTAINER,
                    ])
                    ->tabs([
                        Tab::make('Cotización')
                            ->icon(Heroicon::OutlinedClipboardDocumentList)
                            ->schema([
                                Section::make('Cotización corporativa')
                                    ->description(fn (CorporateQuote $record): string => 'Cotización corporativa generada el '.$record->created_at->format('d/m/Y H:i'))
                                    ->icon(Heroicon::OutlinedBuildingOffice2)
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
                                                            ->label('Número de cotización')
                                                            ->badge()
                                                            ->color('success'),
                                                        TextEntry::make('corporateQuoteRequest.code')
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
                                                            ->default(fn (CorporateQuote $record): string => 'AGT-000'.$record->agent_id.' : '.$record->full_name),
                                                        TextEntry::make('created_at')
                                                            ->label('Fecha de solicitud')
                                                            ->badge()
                                                            ->dateTime(),
                                                    ]),
                                            ]),
                                    ]),
                            ]),
                        Tab::make('Cliente')
                            ->icon(Heroicon::OutlinedUserGroup)
                            ->schema([
                                Section::make('Datos del cliente')
                                    ->icon(Heroicon::OutlinedIdentification)
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(1)
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])
                                                    ->schema([
                                                        TextEntry::make('full_name')
                                                            ->label('Nombre completo'),
                                                        TextEntry::make('rif')
                                                            ->label('RIF')
                                                            ->prefix('J-'),
                                                        TextEntry::make('phone')
                                                            ->label('Número de teléfono'),
                                                        TextEntry::make('email')
                                                            ->label('Correo electrónico'),
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
                                Section::make('Cotización Dress-Tailor')
                                    ->icon(Heroicon::OutlinedDocumentMagnifyingGlass)
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(1)
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                TextEntry::make('observation_dress_tailor')
                                                    ->label('Características de la cotización')
                                                    ->hidden(fn (CorporateQuote $record): bool => $record->observation_dress_tailor === null)
                                                    ->columnSpanFull(),
                                            ]),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
