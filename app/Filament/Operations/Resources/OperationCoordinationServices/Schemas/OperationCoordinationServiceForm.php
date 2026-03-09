<?php

namespace App\Filament\Operations\Resources\OperationCoordinationServices\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class OperationCoordinationServiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('date_solicitud'),
                TextInput::make('date_service'),
                TextInput::make('business_line_id'),
                TextInput::make('business_unit_id'),
                TextInput::make('reference_number'),
                TextInput::make('status'),
                TextInput::make('holder')
                    ->required(),
                TextInput::make('ci_holder'),
                TextInput::make('patient'),
                TextInput::make('ci_patient')
                    ->required(),
                TextInput::make('birth_date_patient'),
                TextInput::make('relationship_patient')
                    ->required(),
                TextInput::make('age_patient'),
                TextInput::make('contractor')
                    ->required(),
                TextInput::make('state_id'),
                TextInput::make('city_id'),
                TextInput::make('address'),
                TextInput::make('phone_holder')
                    ->tel(),
                TextInput::make('symptoms_diagnosis')
                    ->required(),
                TextInput::make('servicie'),
                TextInput::make('specific_service'),
                TextInput::make('type_service'),
                TextInput::make('supplier_service'),
                TextInput::make('farmadoc'),
                TextInput::make('type_negotiation'),
                TextInput::make('status_negotiation'),
                TextInput::make('neto')
                    ->numeric(),
                TextInput::make('porcen_tdec')
                    ->numeric(),
                TextInput::make('quote_price')
                    ->numeric()
                    ->prefix('$'),
                TextInput::make('negotiation'),
                TextInput::make('porcen_discount')
                    ->numeric(),
                TextInput::make('price_discount')
                    ->numeric(),
                TextInput::make('quote_number'),
                TextInput::make('approved_number'),
                TextInput::make('service_order_number'),
                TextInput::make('bill_number'),
                TextInput::make('bill_price')
                    ->required()
                    ->numeric()
                    ->prefix('$'),
                TextInput::make('bill_date'),
                TextInput::make('incidence'),
                TextInput::make('negotiation_description'),
                TextInput::make('qc_description'),
                TextInput::make('observations'),
                TextInput::make('created_by')
                    ->required(),
                TextInput::make('updated_by'),
                TextInput::make('telemedicine_patient_id')
                    ->tel()
                    ->numeric(),
                TextInput::make('telemedicine_case_id')
                    ->tel()
                    ->numeric(),
                TextInput::make('telemedicine_doctor_id')
                    ->tel()
                    ->numeric(),
                TextInput::make('telemedicine_consultation_patient_id')
                    ->tel()
                    ->numeric(),
            ]);
    }
}
