<?php

namespace App\Filament\Agents\Resources\CorporateQuotes\Schemas;

use Filament\Schemas\Schema;
use App\Models\CorporateQuote;
use Filament\Support\Icons\Heroicon;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Fieldset;
use Filament\Infolists\Components\TextEntry;

class CorporateQuoteInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->description(fn(CorporateQuote $record) => 'Cotización Corporativa generada el: ' . $record->created_at->format('d/m/Y H:ma'))
                    ->columnSpanFull()
                    ->icon(Heroicon::Bars3BottomLeft)
                    ->schema([
                        Fieldset::make('Cotización Corporativa')
                            ->schema([
                                TextEntry::make('code')
                                    ->label('Número de Cotización')
                                    ->badge()
                                    ->color('success'),

                                TextEntry::make('corporateQuoteRequest.code')
                                    ->label('Número de solicitud')
                                    ->badge()
                                    ->color('success'),
                                // ...
                                TextEntry::make('code_agency')
                                    ->label('Código de agencia')
                                    ->badge()
                                    ->color('primary'),
                                TextEntry::make('registrated_by')
                                    ->label('Registrado por:')
                                    ->badge()
                                    ->color('primary')
                                    ->default(fn(CorporateQuote $record) => 'AGT-000' . $record->agent_id . ' : ' . $record->full_name),
                                TextEntry::make('created_at')
                                    ->label('Fecha de solicitud')
                                    ->badge()
                                    ->dateTime(),
                            ])->columnSpanFull()->columns(5),

                        Fieldset::make()
                            ->schema([
                                TextEntry::make('full_name')
                                    ->label('Nombre completo'),
                                TextEntry::make('rif')
                                    ->label('RIF:')
                                    ->prefix('J-'),
                                TextEntry::make('phone')
                                    ->label('Número de teléfono'),
                                TextEntry::make('email')
                                    ->label('Correo electrónico'),

                                TextEntry::make('status')
                                    ->label('Estatus')
                                    ->badge()
                                    ->color('success'),
                                TextEntry::make('created_by')
                                    ->label('Registrado por:')
                                    ->badge()
                                    ->color('primary'),
                            ])->columnSpanFull()->columns(4),

                        Fieldset::make('Cotización DRESS-TAILOR')
                            ->schema([
                                TextEntry::make('observation_dress_tailor')
                                    ->label('Características de la cotización')
                                    ->hidden(fn(CorporateQuote $record) => $record->observation_dress_tailor == null),
                            ])->columnSpanFull()->columns(5),

                    ])->columnSpanFull(),
            ]);
    }
}