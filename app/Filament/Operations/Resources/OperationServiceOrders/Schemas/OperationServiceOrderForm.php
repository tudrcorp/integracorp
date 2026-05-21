<?php

namespace App\Filament\Operations\Resources\OperationServiceOrders\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class OperationServiceOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('operation_coordination_service_id')
                    ->required()
                    ->numeric(),
                TextInput::make('supplier_id')
                    ->numeric(),
                TextInput::make('telemedicine_priority_id')
                    ->tel()
                    ->numeric(),
                TextInput::make('order_number')
                    ->required(),
                TextInput::make('supplier_external'),
                TextInput::make('description')
                    ->required(),
                TextInput::make('service_type'),
                TextInput::make('currency'),
                TextInput::make('tasa_bcv')
                    ->numeric(),
                TextInput::make('total_amount_usd')
                    ->numeric(),
                TextInput::make('total_amount_ves')
                    ->numeric(),
                TextInput::make('payment_method'),
                TextInput::make('status'),
                TextInput::make('created_by')
                    ->required(),
                TextInput::make('updated_by'),
                TextInput::make('service_order_pdf_path'),
                TextInput::make('associated_quote_pdf_path'),
                Textarea::make('observations')
                    ->columnSpanFull(),
            ]);
    }
}
