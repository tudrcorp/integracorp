<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\AffiliationCorporates\Actions;

use App\Support\Exports\CorporateAffiliationsReportExportAction;
use Filament\Actions\Action;

final class CorporateAffiliationsExportAction
{
    public static function make(): Action
    {
        return CorporateAffiliationsReportExportAction::make();
    }
}
