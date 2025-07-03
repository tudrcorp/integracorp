<?php

namespace App\Filament\Agents\Resources\AffiliationCorporates\Schemas;

use Filament\Schemas\Schema;
use App\Models\CorporateQuote;
use App\Models\AffiliateCorporate;
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
            // Grid::make([
            //     TextEntry::make('code')
            //     ->label('Nro de afiliación')
            //     ->badge()
            //     ->color('success')
            // ])->columnSpanFull(),
            // TextEntry::make('code_corporate_quote'),
            // TextEntry::make('code_agent'),
            // TextEntry::make('code_agency'),
            // TextEntry::make('corporate_quote_id')
            //     ->numeric(),
            // TextEntry::make('plan_id')
            //     ->numeric(),
            // TextEntry::make('agent_id')
            //     ->numeric(),
            // TextEntry::make('coverage_id')
            //     ->numeric(),
            // TextEntry::make('full_name_con'),
            // TextEntry::make('rif'),
            // TextEntry::make('adress_con'),
            // TextEntry::make('city_id_con')
            //     ->numeric(),
            // TextEntry::make('state_id_con')
            //     ->numeric(),
            // TextEntry::make('country_id_con')
            //     ->numeric(),
            // TextEntry::make('region_con'),
            // TextEntry::make('phone_con'),
            // TextEntry::make('email_con'),
            // TextEntry::make('vaucher_ils'),
            // TextEntry::make('date_payment_initial_ils'),
            // TextEntry::make('date_payment_final_ils'),
            // TextEntry::make('document_ils'),
            // IconEntry::make('cuestion_1')
            //     ->boolean(),
            // IconEntry::make('cuestion_2')
            //     ->boolean(),
            // IconEntry::make('cuestion_3')
            //     ->boolean(),
            // IconEntry::make('cuestion_4')
            //     ->boolean(),
            // IconEntry::make('cuestion_5')
            //     ->boolean(),
            // IconEntry::make('cuestion_6')
            //     ->boolean(),
            // IconEntry::make('cuestion_7')
            //     ->boolean(),
            // IconEntry::make('cuestion_8')
            //     ->boolean(),
            // IconEntry::make('cuestion_9')
            //     ->boolean(),
            // IconEntry::make('cuestion_10')
            //     ->boolean(),
            // IconEntry::make('cuestion_11')
            //     ->boolean(),
            // IconEntry::make('cuestion_12')
            //     ->boolean(),
            // IconEntry::make('cuestion_13')
            //     ->boolean(),
            // IconEntry::make('cuestion_14')
            //     ->boolean(),
            // IconEntry::make('cuestion_15')
            //     ->boolean(),
            // TextEntry::make('full_name_applicant'),
            // TextEntry::make('signature_applicant'),
            // TextEntry::make('nro_identificacion_applicant'),
            // TextEntry::make('date_applicant'),
            // TextEntry::make('full_name_agent'),
            // TextEntry::make('signature_agent'),
            // TextEntry::make('payment_frequency'),
            // TextEntry::make('activated_at'),
            // TextEntry::make('corporate_members'),
            // TextEntry::make('document'),
            // TextEntry::make('date_today'),
            // TextEntry::make('created_by'),
            // TextEntry::make('status'),
            // TextEntry::make('created_at')
            //     ->dateTime(),
            // TextEntry::make('updated_at')
            //     ->dateTime(),
            // TextEntry::make('owner_code'),
            // TextEntry::make('type'),

            Section::make()
                ->heading('Información de la solicitud')
                ->description(fn(AffiliationCorporate $record) => 'Solicitud de cotizacion corpotariva generada el: ' . $record->created_at->format('d/m/Y H:ma'))
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
                                ->label('Email:'),
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