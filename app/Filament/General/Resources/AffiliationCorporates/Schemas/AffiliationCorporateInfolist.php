<?php

namespace App\Filament\General\Resources\AffiliationCorporates\Schemas;

use Filament\Schemas\Schema;
use App\Models\AffiliationCorporate;
use Filament\Support\Icons\Heroicon;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;

class AffiliationCorporateInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->heading('Información de la solicitud')
                    ->description(fn(AffiliationCorporate $record) => 'Solicitud de cotización corporativa generada el: ' . $record->created_at->format('d/m/Y H:ma'))
                    ->icon(Heroicon::OutlinedPencil)
                    ->schema([
                        TextEntry::make('code')
                            ->label('Número de solicitud')
                            ->badge()
                            ->color('success')
                            ->numeric(),
                        Grid::make()
                            ->schema([
                                TextEntry::make('plan.description')
                                    ->label('Plan asociado:')
                                    ->badge(),
                                TextEntry::make('coverage.price')
                                    ->label('Cobertura asociada:')
                                    ->badge(),
                            ])->columnSpanFull()->columns(4),
                        Grid::make()
                            ->schema([
                                TextEntry::make('code_agent')
                                    ->label('Código del agente')
                                    ->default(fn(AffiliationCorporate $record) => 'AGT-000' . $record->agent_id)
                                    ->badge(),
                                TextEntry::make('agent.name')
                                    ->label('Agente:')
                                    ->badge(),
                                TextEntry::make('code_agency')
                                    ->label('Codigo de agencia:')
                                    ->badge(),

                            ])->columnSpanFull()->columns(4),
                        Grid::make()
                            ->schema([
                                TextEntry::make('full_name_con')
                                    ->label('Nombre completo/Razón social:'),
                                TextEntry::make('rif')
                                    ->label('Rif:')
                                    ->prefix('J-'),
                                TextEntry::make('email_con')
                                    ->label('Correo Electrónico:'),
                                TextEntry::make('phone_con')
                                    ->label('Telefono:'),
                                TextEntry::make('country.name')
                                    ->label('País:'),
                                TextEntry::make('state.definition')
                                    ->label('Estado'),
                                TextEntry::make('region_con')
                                    ->label('Región:'),
                                TextEntry::make('status')
                                    ->label('Estatus de la solicitud:')
                                    ->badge()
                                    ->color('warning'),
                                TextEntry::make('created_by')
                                    ->label('Registrado por:'),
                                TextEntry::make('created_at')
                                    ->label('Fecha de regitro en sistema:')
                                    ->dateTime(),
                                TextEntry::make('observations')->label('Observaciones del agente:'),
                            ])->columnSpanFull()->columns(4),
                    ])->columnSpanFull(),
            ]);
    }
}