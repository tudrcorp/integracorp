<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\AffiliationCorporatePaymentFrequencyChanges\Pages;

use App\Filament\Administration\Resources\AffiliationCorporatePaymentFrequencyChanges\AffiliationCorporatePaymentFrequencyChangeResource;
use App\Models\AffiliationCorporatePaymentFrequencyChange;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListAffiliationCorporatePaymentFrequencyChanges extends ListRecords
{
    protected static string $resource = AffiliationCorporatePaymentFrequencyChangeResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'pending' => Tab::make('Por validar')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('status', AffiliationCorporatePaymentFrequencyChange::STATUS_APPLIED)
                    ->whereNull('validated_at')),
            'validated' => Tab::make('Validados')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('status', AffiliationCorporatePaymentFrequencyChange::STATUS_APPLIED)
                    ->whereNotNull('validated_at')),
            'reversed' => Tab::make('Revertidos')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('status', AffiliationCorporatePaymentFrequencyChange::STATUS_REVERSED)),
            'all' => Tab::make('Todos'),
        ];
    }
}
