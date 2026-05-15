<?php

namespace App\Filament\Administration\Resources\AffiliationCorporates\Pages;

use App\Filament\Administration\Resources\AffiliationCorporates\AffiliationCorporateResource;
use App\Models\AffiliationCorporate;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\View;

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
            Action::make('print_affiliation_corporate_pdf')
                ->label('Ficha de afiliación corporativa')
                ->icon('heroicon-o-printer')
                ->color('success')
                ->modalHeading('Ficha de afiliación corporativa en PDF')
                ->modalDescription('Vista previa de la ficha. La primera generación puede tardar; las siguientes suelen ser más rápidas mientras no cambien los datos de la afiliación ni de los afiliados asociados (caché por versión de datos).')
                ->modalWidth(Width::SevenExtraLarge)
                ->modalIcon('heroicon-o-document-text')
                ->modalContent(function (): ViewContract {
                    /** @var AffiliationCorporate $record */
                    $record = $this->getRecord();

                    $label = filled($record->name_corporate)
                        ? $record->name_corporate
                        : (filled($record->code) ? $record->code : ('Afiliación #'.$record->id));

                    return View::make('filament.administration.affiliation-corporates.affiliation-corporate-ficha-preview-modal', [
                        'pdfPreviewUrl' => route('administration.affiliation-corporates.ficha.preview', $record),
                        'pdfDownloadUrl' => route('administration.affiliation-corporates.ficha.download', $record),
                        'recordLabel' => $label,
                    ]);
                })
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Cerrar')
                ->action(fn () => null)
                ->extraAttributes([
                    'class' => self::TICKET_BUTTON_CLASS,
                ]),
        ], parent::getHeaderActions());
    }
}
