<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\AffiliationCorporates\Actions;

use App\Models\AffiliationCorporate;
use Filament\Actions\Action;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\View;

class AffiliationCorporateFichaPdfActions
{
    public static function printCorporatePdfAction(): Action
    {
        return Action::make('print_affiliation_corporate_pdf')
            ->label('Ficha de afiliación corporativa')
            ->icon('heroicon-o-printer')
            ->color('success')
            ->modalHeading('Ficha de afiliación corporativa en PDF')
            ->modalDescription('Vista previa de la ficha. La primera generación puede tardar; las siguientes suelen ser más rápidas mientras no cambien los datos de la afiliación ni de los afiliados asociados (caché por versión de datos).')
            ->modalWidth(Width::SevenExtraLarge)
            ->modalIcon('heroicon-o-document-text')
            ->modalContent(function (AffiliationCorporate $record): ViewContract {
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
            ->action(fn () => null);
    }
}
