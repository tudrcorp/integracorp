<?php

namespace App\Filament\Resources\Sales\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class SaleInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('date'),
                TextEntry::make('owner_code'),
                TextEntry::make('code_agency'),
                TextEntry::make('agent_id')
                    ->numeric(),
                TextEntry::make('invoice_number'),
                TextEntry::make('affiliation_code'),
                TextEntry::make('affiliate_full_name'),
                TextEntry::make('affiliate_contact'),
                TextEntry::make('affiliate_ci_rif'),
                TextEntry::make('affiliate_phone'),
                TextEntry::make('affiliate_email'),
                TextEntry::make('plan_id')
                    ->numeric(),
                TextEntry::make('coverage_id')
                    ->numeric(),
                TextEntry::make('service'),
                TextEntry::make('persons'),
                TextEntry::make('created_by'),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
                TextEntry::make('total_amount')
                    ->numeric(),
                TextEntry::make('type'),
                TextEntry::make('payment_method'),
                TextEntry::make('payment_frequency'),
                TextEntry::make('status_payment_commission'),
                TextEntry::make('pay_amount_usd')
                    ->numeric(),
                TextEntry::make('pay_amount_ves')
                    ->numeric(),
                TextEntry::make('type_roll'),
                TextEntry::make('bank_usd'),
                TextEntry::make('bank_ves'),
                TextEntry::make('payment_date'),
                TextEntry::make('payment_method_usd'),
                TextEntry::make('payment_method_ves'),
            ]);
    }
}
