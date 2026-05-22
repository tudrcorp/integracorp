<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\Affiliations\Pages;

use App\Filament\Administration\Resources\Affiliations\Actions\AffiliationFichaPdfActions;
use App\Filament\Administration\Resources\Affiliations\AffiliationResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;

class EditAffiliation extends EditRecord
{
    protected static string $resource = AffiliationResource::class;

    /**
     * Idéntico a proveedores jurídicos / afiliación corporativa: .ticket-btn-ios en theme.css.
     */
    private const TICKET_BUTTON_CLASS = 'ticket-btn-ios shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    protected static ?string $title = 'Compensar Pago de Afiliación';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        $this->authorizeAccess();

        $this->previousUrl = url()->previous();
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getRelationManagersContentComponent(),
            ]);
    }

    protected function getFormActions(): array
    {
        return [];
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
