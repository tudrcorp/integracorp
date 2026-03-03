<?php

namespace App\Filament\Operations\Resources\OperationInventoryMovements\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class OperationInventoryMovementInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('operation_inventory_id')
                    ->numeric(),
                TextEntry::make('telemedicine_patient_id')
                    ->numeric(),
                TextEntry::make('telemedicine_case_id')
                    ->numeric(),
                TextEntry::make('telemedicine_consultation_id')
                    ->numeric(),
                TextEntry::make('telemedicine_doctor_id')
                    ->numeric(),
                TextEntry::make('business_unit_id')
                    ->numeric(),
                TextEntry::make('business_line_id')
                    ->numeric(),
                TextEntry::make('quantity')
                    ->numeric(),
                TextEntry::make('unit'),
                TextEntry::make('type'),
                TextEntry::make('created_by'),
                TextEntry::make('status'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
