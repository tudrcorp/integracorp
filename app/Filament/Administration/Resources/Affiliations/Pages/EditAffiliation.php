<?php

namespace App\Filament\Administration\Resources\Affiliations\Pages;

use App\Filament\Administration\Resources\Affiliations\Actions\AffiliationFichaPdfActions;
use App\Filament\Administration\Resources\Affiliations\AffiliationResource;
use Filament\Resources\Pages\EditRecord;

class EditAffiliation extends EditRecord
{
    protected static string $resource = AffiliationResource::class;

    /**
     * Idéntico a proveedores jurídicos / afiliación corporativa: .ticket-btn-ios en theme.css.
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
            AffiliationFichaPdfActions::printIndividualPdfAction()
                ->extraAttributes([
                    'class' => self::TICKET_BUTTON_CLASS,
                ]),
        ], parent::getHeaderActions());
    }
}
