<?php

namespace App\Filament\Resources\CommissionPayrolls\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CommissionPayrollForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->required(),
                TextInput::make('code_pcc')
                    ->required(),
                TextInput::make('date_ini'),
                TextInput::make('date_end'),
                TextInput::make('type'),
                TextInput::make('owner_code')
                    ->required(),
                TextInput::make('code_agency')
                    ->required(),
                TextInput::make('agent_id'),
                TextInput::make('owner_name')
                    ->required(),
                TextInput::make('amount_commission_master_agency')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('amount_commission_master_agency_usd')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('amount_commission_master_agency_ves')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('amount_commission_general_agency')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('amount_commission_general_agency_usd')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('amount_commission_general_agency_ves')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('amount_commission_agent')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('amount_commission_agent_usd')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('amount_commission_agent_ves')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('amount_commission_subagent')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('amount_commission_subagent_usd')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('amount_commission_subagent_ves')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('created_by')
                    ->required(),
                TextInput::make('total_commission')
                    ->numeric()
                    ->default(0.0),
            ]);
    }
}
