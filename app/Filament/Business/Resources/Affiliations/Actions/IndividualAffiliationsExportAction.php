<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\Affiliations\Actions;

use App\Support\Exports\IndividualAffiliationsReportExportAction;
use Filament\Actions\Action;

final class IndividualAffiliationsExportAction
{
    public static function make(): Action
    {
        return IndividualAffiliationsReportExportAction::make(
            planHelperText: 'Filtra por el plan asociado a la afiliación o del afiliado.',
        );
    }
}
