<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\Affiliations\Actions;

use App\Models\Affiliation;
use Filament\Actions\Action;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\View;

class AffiliationFichaPdfActions
{
    public static function printIndividualPdfAction(): Action
    {
        return Action::make('print_affiliation_individual_pdf')
            ->label('Ficha Técnica de la Afiliación')
            ->icon('heroicon-o-printer')
            ->color('primary')
            ->modalHeading('Ficha técnica de la afiliación en PDF')
            ->modalDescription('Vista previa de la ficha técnica. La primera generación puede tardar; las siguientes suelen ser más rápidas mientras no cambien los datos de la afiliación ni de los familiares asociados (caché por versión de datos).')
            ->modalWidth(Width::SevenExtraLarge)
            ->modalIcon('heroicon-o-document-text')
            ->modalContent(function (Affiliation $record): ViewContract {
                $label = filled($record->full_name_ti)
                    ? $record->full_name_ti
                    : (filled($record->code) ? $record->code : ('Afiliación #'.$record->id));

                return View::make('filament.administration.affiliations.affiliation-ficha-preview-modal', [
                    'pdfPreviewUrl' => route('business.affiliations.ficha.preview', $record),
                    'pdfDownloadUrl' => route('business.affiliations.ficha.download', $record),
                    'recordLabel' => $label,
                ]);
            })
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Cerrar')
            ->action(fn () => null);
    }
}
