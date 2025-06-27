<?php

namespace App\Filament\Resources\CommissionPayrolls\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class CommissionPayrollInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('code'),
                TextEntry::make('code_pcc'),
                TextEntry::make('date_ini'),
                TextEntry::make('date_end'),
                TextEntry::make('type'),
                TextEntry::make('owner_code'),
                TextEntry::make('code_agency'),
                TextEntry::make('agent_id'),
                TextEntry::make('owner_name'),
                TextEntry::make('amount_commission_master_agency')
                    ->numeric(),
                TextEntry::make('amount_commission_master_agency_usd')
                    ->numeric(),
                TextEntry::make('amount_commission_master_agency_ves')
                    ->numeric(),
                TextEntry::make('amount_commission_general_agency')
                    ->numeric(),
                TextEntry::make('amount_commission_general_agency_usd')
                    ->numeric(),
                TextEntry::make('amount_commission_general_agency_ves')
                    ->numeric(),
                TextEntry::make('amount_commission_agent')
                    ->numeric(),
                TextEntry::make('amount_commission_agent_usd')
                    ->numeric(),
                TextEntry::make('amount_commission_agent_ves')
                    ->numeric(),
                TextEntry::make('amount_commission_subagent')
                    ->numeric(),
                TextEntry::make('amount_commission_subagent_usd')
                    ->numeric(),
                TextEntry::make('amount_commission_subagent_ves')
                    ->numeric(),
                TextEntry::make('created_by'),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
                TextEntry::make('total_commission')
                    ->numeric(),
            ]);
    }
}
