<?php

namespace App\Filament\Resources\Collections\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class CollectionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('sale_id')
                    ->numeric(),
                TextEntry::make('include_date'),
                TextEntry::make('owner_code'),
                TextEntry::make('code_agency'),
                TextEntry::make('agent_id')
                    ->numeric(),
                TextEntry::make('collection_invoice_number'),
                TextEntry::make('quote_number'),
                TextEntry::make('affiliation_code'),
                TextEntry::make('affiliate_full_name'),
                TextEntry::make('affiliate_contact'),
                TextEntry::make('affiliate_ci_rif'),
                TextEntry::make('affiliate_phone'),
                TextEntry::make('affiliate_email'),
                TextEntry::make('affiliate_status'),
                TextEntry::make('plan_id')
                    ->numeric(),
                TextEntry::make('coverage_id')
                    ->numeric(),
                TextEntry::make('service'),
                TextEntry::make('persons'),
                TextEntry::make('type'),
                TextEntry::make('reference'),
                TextEntry::make('payment_method'),
                TextEntry::make('payment_frequency'),
                TextEntry::make('next_payment_date'),
                TextEntry::make('total_amount')
                    ->numeric(),
                TextEntry::make('expiration_date'),
                TextEntry::make('status'),
                TextEntry::make('days')
                    ->numeric(),
                TextEntry::make('created_by'),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
                TextEntry::make('pay_amount_usd')
                    ->numeric(),
                TextEntry::make('pay_amount_ves')
                    ->numeric(),
                TextEntry::make('bank_usd'),
                TextEntry::make('bank_ves'),
            ]);
    }
}
