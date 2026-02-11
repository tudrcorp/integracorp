<?php

namespace App\Filament\Agents\Resources\CorporateQuoteRequests\Schemas;

use App\Models\CorporateQuoteRequest;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CorporateQuoteRequestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->heading('SOLICITUD DE COTIZACION CORPORATIVA TIPO DRESS TYLOR')
                    ->icon('heroicon-o-pencil-square')
                    ->description('Detallado de solicitud de cotizacion')
                    ->schema([
                        Fieldset::make('Solicitud de cotizacion')
                            ->schema([
                                TextEntry::make('code')
                                    ->label('Codigo')
                                    ->badge()
                                    ->color('primary'),
                                TextEntry::make('agent.name')
                                    ->label('Agente')
                                    ->icon('heroicon-o-user')
                                    ->placeholder('-'),
                                TextEntry::make('agency.name_corporative')
                                    ->label('Codigo de la Agencia')
                                    ->default(fn(CorporateQuoteRequest $record) => self::getAgencyName($record))
                                    ->icon('heroicon-o-building-office')
                                    ->placeholder('-'),
                                TextEntry::make('full_name')
                                    ->label('Solicitada por:')
                                    ->placeholder('-'),
                                TextEntry::make('rif')
                                    ->label('RIF:')
                                    ->placeholder('----'),
                                TextEntry::make('email')
                                    ->label('Correo Electronico:')
                                    ->icon('heroicon-o-envelope')
                                    ->badge()
                                    ->color('primary')
                                    ->placeholder('----'),
                                TextEntry::make('phone')
                                    ->label('Telefono')
                                    ->icon('heroicon-o-phone')
                                    ->badge()
                                    ->color('primary')
                                    ->placeholder('----'),
                                TextEntry::make('state.definition')
                                    ->label('Estado')
                                    ->placeholder('----'),
                                TextEntry::make('region')
                                    ->label('Region')
                                    ->placeholder('----'),
                                TextEntry::make('created_by')
                                    ->label('Creado por'),
                                TextEntry::make('created_at')
                                    ->label('Fecha de Solicitud')
                                    ->belowContent(fn($state) => $state->diffForHumans())
                                    ->dateTime()
                                    ->placeholder('----'),
                            ])->columns(4),
                        Fieldset::make('Observaciones')
                            ->schema([
                                TextEntry::make('poblation')
                                    ->label('Poblacion/Numero de Personas')
                                    ->suffix('Personas')
                                    ->numeric()
                                    ->placeholder('-'),
                                TextEntry::make('observations')
                                    ->label('Observaciones')
                                    ->placeholder('----')
                                    ->columnSpanFull(),

                            ])->columns(1),
                    ])->columnSpanFull(),
            ]);
    }

    public static function getAgencyName(CorporateQuoteRequest $record): string
    {
        return $record->code_agency == 'TDG-100' ? 'TU DR EN CASA' : $record->agency->name_corporative;
    }
}
