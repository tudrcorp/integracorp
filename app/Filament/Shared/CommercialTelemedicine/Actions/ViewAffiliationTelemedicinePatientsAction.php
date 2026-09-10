<?php

declare(strict_types=1);

namespace App\Filament\Shared\CommercialTelemedicine\Actions;

use App\Filament\Shared\CommercialTelemedicine\CommercialTelemedicinePanel;
use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

final class ViewAffiliationTelemedicinePatientsAction
{
    public static function make(): Action
    {
        return Action::make('view_telemedicine_patients')
            ->label('Ver pacientes')
            ->icon(Heroicon::OutlinedHeart)
            ->color('info')
            ->visible(fn (): bool => CommercialTelemedicinePanel::canViewPatients())
            ->url(fn (?Model $record): ?string => self::urlForRecord($record));
    }

    public static function forRecord(Model $record): Action
    {
        return self::make()->url(self::urlForRecord($record));
    }

    public static function urlForRecord(?Model $record): ?string
    {
        if ($record instanceof AffiliationCorporate) {
            return CommercialTelemedicinePanel::patientsIndexUrl(
                affiliationCorporateId: (int) $record->getKey(),
            );
        }

        if ($record instanceof Affiliation || $record instanceof Model) {
            return CommercialTelemedicinePanel::patientsIndexUrl(
                affiliationId: (int) $record->getKey(),
            );
        }

        return null;
    }
}
