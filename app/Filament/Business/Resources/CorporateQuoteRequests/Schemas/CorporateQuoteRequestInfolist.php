<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\CorporateQuoteRequests\Schemas;

use App\Models\CorporateQuoteRequest;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class CorporateQuoteRequestInfolist
{
    private const TABS_CONTAINER = 'rounded-[1.75rem] border border-slate-200/85 bg-gradient-to-br from-white via-slate-50/90 to-white p-2 shadow-[0_24px_60px_-26px_rgba(15,23,42,0.2)] ring-1 ring-slate-200/55 dark:border-white/10 dark:from-slate-900/95 dark:via-slate-950/95 dark:to-slate-900/95 dark:ring-white/10 dark:shadow-[0_24px_60px_-24px_rgba(0,0,0,0.55)]';

    private const IOS_SECTION_CLASS = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    private const IOS_INNER_CLASS = 'rounded-[1.25rem] border border-slate-200/80 bg-white/80 p-4 shadow-inner dark:border-white/10 dark:bg-white/5 sm:p-5';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('corporateQuoteRequestInfolistTabs')
                    ->columnSpanFull()
                    ->persistTab()
                    ->extraAttributes([
                        'class' => self::TABS_CONTAINER,
                    ])
                    ->tabs([
                        Tab::make('Solicitud')
                            ->icon(Heroicon::OutlinedClipboardDocumentList)
                            ->schema([
                                Section::make('Solicitud de cotización corporativa')
                                    ->heading('Solicitud de cotización corporativa tipo Dress Taylor')
                                    ->description('Detalle de la solicitud de cotización')
                                    ->icon(Heroicon::OutlinedPencilSquare)
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
                                                        TextEntry::make('code')
                                                            ->label('Código')
                                                            ->badge()
                                                            ->color('primary'),
                                                        TextEntry::make('agent.name')
                                                            ->label('Agente')
                                                            ->icon(Heroicon::OutlinedUser)
                                                            ->placeholder('-'),
                                                        TextEntry::make('agency.name_corporative')
                                                            ->label('Código de la agencia')
                                                            ->default(fn (CorporateQuoteRequest $record): string => self::getAgencyName($record))
                                                            ->icon(Heroicon::OutlinedBuildingOffice2)
                                                            ->placeholder('-'),
                                                        TextEntry::make('full_name')
                                                            ->label('Solicitada por')
                                                            ->placeholder('-'),
                                                        TextEntry::make('rif')
                                                            ->label('RIF')
                                                            ->placeholder('----'),
                                                        TextEntry::make('email')
                                                            ->label('Correo electrónico')
                                                            ->icon(Heroicon::OutlinedEnvelope)
                                                            ->badge()
                                                            ->color('primary')
                                                            ->placeholder('----'),
                                                        TextEntry::make('phone')
                                                            ->label('Teléfono')
                                                            ->icon(Heroicon::OutlinedPhone)
                                                            ->badge()
                                                            ->color('primary')
                                                            ->placeholder('----'),
                                                        TextEntry::make('state.definition')
                                                            ->label('Estado')
                                                            ->placeholder('----'),
                                                        TextEntry::make('region')
                                                            ->label('Región')
                                                            ->placeholder('----'),
                                                        TextEntry::make('created_by')
                                                            ->label('Creado por'),
                                                        TextEntry::make('created_at')
                                                            ->label('Fecha de solicitud')
                                                            ->belowContent(fn ($state) => $state->diffForHumans())
                                                            ->dateTime()
                                                            ->placeholder('----'),
                                                    ]),
                                            ]),
                                    ]),
                            ]),
                        Tab::make('Observaciones')
                            ->icon(Heroicon::OutlinedChatBubbleBottomCenterText)
                            ->schema([
                                Section::make('Observaciones')
                                    ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(1)
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                TextEntry::make('poblation')
                                                    ->label('Población / número de personas')
                                                    ->suffix(' personas')
                                                    ->numeric()
                                                    ->placeholder('-'),
                                                TextEntry::make('observations')
                                                    ->label('Observaciones')
                                                    ->placeholder('----')
                                                    ->columnSpanFull(),
                                            ]),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    public static function getAgencyName(CorporateQuoteRequest $record): string
    {
        return $record->code_agency == 'TDG-100' ? 'TU DR EN CASA' : $record->agency->name_corporative;
    }
}
