<?php

namespace App\Filament\Administration\Resources\AffiliationCorporates\Pages;

use App\Filament\Administration\Resources\AffiliationCorporates\Actions\AffiliationCorporateFichaPdfActions;
use App\Filament\Administration\Resources\AffiliationCorporates\AffiliationCorporateResource;
use Filament\Resources\Pages\EditRecord;

class EditAffiliationCorporate extends EditRecord
{
    protected static string $resource = AffiliationCorporateResource::class;

    protected static ?string $title = 'Editar Affiliation Corporativa';

    /**
     * Idéntico a proveedores jurídicos (ViewSupplier): .ticket-btn-ios en theme.css.
     */
    private const TICKET_BUTTON_CLASS = 'ticket-btn-ios shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    protected function getFormActions(): array
    {
        return [

        ];
    }

    protected function getHeaderActions(): array
    {
        return array_merge([
            AffiliationCorporateFichaPdfActions::printCorporatePdfAction()
                ->extraAttributes([
                    'class' => self::TICKET_BUTTON_CLASS,
                ]),
        ], parent::getHeaderActions());
    }
}
