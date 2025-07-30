<?php

namespace App\Filament\Agents\Resources\IndividualQuotes\Schemas;

use Filament\Schemas\Schema;
use App\Models\IndividualQuote;
use Filament\Support\Icons\Heroicon;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Fieldset;
use Filament\Infolists\Components\TextEntry;

class IndividualQuoteInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->description(fn(IndividualQuote $record) => 'Cotización Individual generada el: ' . $record->created_at->format('d/m/Y H:ma'))
                    ->columnSpanFull()
                    ->icon(Heroicon::Bars3BottomLeft)
                    ->schema([
                        Fieldset::make('Solicitud de cotización individual')
                            ->schema([
                                TextEntry::make('code')
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
                                    ->default(fn(IndividualQuote $record) => 'AGT-000' . $record->agent_id . ' : ' . $record->full_name),
                                TextEntry::make('created_at')
                                    ->label('Fecha de solicitud')
                                    ->badge()
                                    ->dateTime(),
                            ])->columnSpanFull()->columns(5),

                        Fieldset::make('Información del Solicitante')
                            ->schema([
                                TextEntry::make('full_name')
                                    ->label('Nombre completo'),
                                TextEntry::make('email')
                                    ->label('Correo electrónico'),
                                TextEntry::make('phone')
                                    ->label('Número de teléfono'),
                                TextEntry::make('status')
                                    ->label('Estatus')
                                    ->badge()
                                    ->color('success'),
                                TextEntry::make('created_by')
                                    ->label('Registrado por:')
                                    ->badge()
                                    ->color('primary'),
                            ])->columnSpanFull()->columns(5),

                    ])->columnSpanFull(),
            ]);
    }
}
