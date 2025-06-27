<?php

namespace App\Filament\Agents\Resources\Commissions\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class CommissionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('code'),
                TextEntry::make('date_payment_affiliate'),
                TextEntry::make('agency_id')
                    ->numeric(),
                TextEntry::make('code_agency'),
                TextEntry::make('owner_code'),
                TextEntry::make('agent_id')
                    ->numeric(),
                TextEntry::make('plan_id')
                    ->numeric(),
                TextEntry::make('coverage_id')
                    ->numeric(),
                TextEntry::make('sale_id')
                    ->numeric(),
                TextEntry::make('invoice_number'),
                TextEntry::make('affiliate_full_name'),
                TextEntry::make('amount')
                    ->numeric(),
                TextEntry::make('payment_method'),
                TextEntry::make('veto'),
                TextEntry::make('payment_frequency'),
                TextEntry::make('commission_agency_master')
                    ->numeric(),
                TextEntry::make('commission_agency_general')
                    ->numeric(),
                TextEntry::make('commission_agent')
                    ->numeric(),
                TextEntry::make('total_payment_commission')
                    ->numeric(),
                TextEntry::make('date_payment_commission'),
                TextEntry::make('created_by'),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
                TextEntry::make('commission_agency_master_tdec')
                    ->numeric(),
                TextEntry::make('commission_agency_general_tdec')
                    ->numeric(),
                TextEntry::make('commission_agent_tdec')
                    ->numeric(),
                TextEntry::make('pay_amount_usd')
                    ->numeric(),
                TextEntry::make('pay_amount_ves')
                    ->numeric(),
                TextEntry::make('payment_method_usd'),
                TextEntry::make('payment_method_ves'),
                TextEntry::make('commission_agency_master_usd')
                    ->numeric(),
                TextEntry::make('commission_agency_general_usd')
                    ->numeric(),
                TextEntry::make('commission_agent_usd')
                    ->numeric(),
                TextEntry::make('commission_agency_master_ves')
                    ->numeric(),
                TextEntry::make('commission_agency_general_ves')
                    ->numeric(),
                TextEntry::make('commission_agent_ves')
                    ->numeric(),
                TextEntry::make('date_ini'),
                TextEntry::make('date_end'),
            ]);
    }
}
