<?php

declare(strict_types=1);

namespace App\Filament\Shared\CommercialStructure;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

final class TdevIntegrationInfolistTab
{
    public const LABEL = 'Integración TuDrEnViajes';

    public static function agent(): Tab
    {
        return self::make('id_agent_tdev', 'ID agente TDEV');
    }

    public static function agency(): Tab
    {
        return self::make('id_agency_tdev', 'ID agencia TDEV');
    }

    private static function make(string $identifier, string $identifierLabel): Tab
    {
        return Tab::make(self::LABEL)
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->schema([
                Section::make('Tu Dr en Viajes')
                    ->key('integracion-tudrenviajes')
                    ->heading(fn (): HtmlString => new HtmlString(self::logoMarkup()))
                    ->description('Identificador, usuario, comisiones y crédito de la integración TDEV.')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(['default' => 1, 'lg' => 2])
                            ->schema([
                                IconEntry::make('tdev')
                                    ->label('TDEV activo')
                                    ->boolean(),
                                TextEntry::make('user_tdev')
                                    ->label('Usuario de Tu Doctor en Viajes (TDEV)')
                                    ->placeholder('—'),
                                TextEntry::make('commission_tdev')
                                    ->label('Comisión TDEV')
                                    ->numeric(decimalPlaces: 2)
                                    ->suffix(' %')
                                    ->placeholder('—'),
                                TextEntry::make('commission_tdev_renewal')
                                    ->label('Comisión renovación TDEV')
                                    ->numeric(decimalPlaces: 2)
                                    ->suffix(' %')
                                    ->placeholder('—'),
                                TextEntry::make($identifier)
                                    ->label($identifierLabel)
                                    ->placeholder('—'),
                                TextEntry::make('amount_asign_credit_tdev')
                                    ->label('Crédito asignado TDEV')
                                    ->prefix('US$')
                                    ->numeric(decimalPlaces: 2)
                                    ->placeholder('0.00'),
                            ]),
                    ]),
            ]);
    }

    private static function logoMarkup(): string
    {
        $src = e(asset('image/logo-tdev.png'));

        return '<img src="'.$src.'" alt="Tu Doctor En Viajes" class="h-16 w-auto max-w-[14rem] object-contain sm:h-20">';
    }
}
