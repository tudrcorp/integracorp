<?php

namespace App\Filament\Administration\Resources\Affiliations\Pages;

use App\Filament\Administration\Resources\Affiliations\AffiliationResource;
use App\Models\Affiliation;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\View;

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
            Action::make('print_affiliation_individual_pdf')
                ->label('Ficha de afiliación individual')
                ->icon('heroicon-o-printer')
                ->color('success')
                ->modalHeading('Ficha de afiliación individual en PDF')
                ->modalDescription('Vista previa de la ficha. La primera generación puede tardar; las siguientes suelen ser más rápidas mientras no cambien los datos de la afiliación ni de los familiares asociados (caché por versión de datos).')
                ->modalWidth(Width::SevenExtraLarge)
                ->modalIcon('heroicon-o-document-text')
                ->modalContent(function (): ViewContract {
                    /** @var Affiliation $record */
                    $record = $this->getRecord();

                    $label = filled($record->full_name_ti)
                        ? $record->full_name_ti
                        : (filled($record->code) ? $record->code : ('Afiliación #'.$record->id));

                    return View::make('filament.administration.affiliations.affiliation-ficha-preview-modal', [
                        'pdfPreviewUrl' => route('administration.affiliations.ficha.preview', $record),
                        'pdfDownloadUrl' => route('administration.affiliations.ficha.download', $record),
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
