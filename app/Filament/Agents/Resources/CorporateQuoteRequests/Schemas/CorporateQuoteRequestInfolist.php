<?php

namespace App\Filament\Agents\Resources\CorporateQuoteRequests\Schemas;

use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use App\Models\CorporateQuoteRequest;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Infolists\Components\TextEntry;

class CorporateQuoteRequestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
        ->components([
                Section::make()
                    ->heading('Información de la solicitud')
                    ->description(fn(CorporateQuoteRequest $record) => 'Solicitud de cotización corporativa generada el: ' . $record->created_at->format('d/m/Y H:ma'))
                    ->icon(Heroicon::OutlinedPencil)
                    ->schema([
                        TextEntry::make('code')
                            ->label('Número de solicitud')
                            ->badge()
                            ->color('success')
                            ->numeric(),
                        Grid::make()
                            ->schema([
                                TextEntry::make('code_agent')
                                    ->label('Código del agente')
                                    ->default(fn(CorporateQuoteRequest $record) => 'AGT-000' . $record->agent_id)
                                    ->badge(),
                                TextEntry::make('agent.name')
                                    ->label('Agente:')
                                    ->badge(),
                                TextEntry::make('code_agency')
                                    ->label('Código de agencia:')
                                    ->badge(),
                            ])->columnSpanFull()->columns(4),
                        Grid::make()
                            ->schema([
                                TextEntry::make('full_name')
                                    ->label('Nombre completo/Razón social:'),
                                TextEntry::make('rif')
                                    ->label('Rif:'),
                                TextEntry::make('email')
                                    ->label('Email:'),
                                TextEntry::make('phone')
                                    ->label('Teléfono:'),
                                TextEntry::make('state.definition')
                                    ->label('Estado')
                                    ->numeric(),
                                TextEntry::make('region')
                                    ->label('Región:'),
                                TextEntry::make('status')
                                    ->label('Estatus de la solicitud:')
                                    ->badge()
                                    ->color('warning'),
                                TextEntry::make('created_by')
                                    ->label('Registrado por:'),
                                TextEntry::make('created_at')
                                    ->label('Fecha de registro en sistema:')
                                    ->dateTime(),
                                TextEntry::make('observations')->label('Observaciones del agente:'),
                            ])->columnSpanFull()->columns(4),
                    ])->columnSpanFull(),

            ]);
    }
}