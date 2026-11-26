<?php

declare(strict_types=1);

namespace App\Filament\Shared\ClinicalRecord;

use App\Models\TelemedicinePatient;
use App\Support\Telemedicine\ClinicalRecord\ClinicalRecordAccess;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

/**
 * Entrada a la ficha clínica desde la tabla o la vista del paciente.
 */
final class ClinicalRecordAction
{
    /**
     * @param  class-string  $resource
     */
    public static function make(string $resource, string $panel, string $kind = ClinicalRecordAccess::KIND_CLINICAL): Action
    {
        $technical = $kind === ClinicalRecordAccess::KIND_TECHNICAL;

        return Action::make($technical ? 'technical_record' : 'clinical_record')
            ->label($technical ? 'Ficha técnica (uso interno)' : 'Ficha clínica')
            ->icon($technical ? Heroicon::OutlinedIdentification : Heroicon::OutlinedClipboardDocumentList)
            ->color($technical ? 'warning' : 'success')
            ->visible(fn (): bool => ClinicalRecordAccess::canUsePanel(Auth::user(), $panel, $kind))
            ->url(fn (TelemedicinePatient $record): string => $resource::getUrl($technical ? 'technical-record' : 'clinical-record', ['record' => $record]));
    }
}
