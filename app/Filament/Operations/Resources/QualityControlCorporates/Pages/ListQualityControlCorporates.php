<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\QualityControlCorporates\Pages;

use App\Filament\Operations\Resources\QualityControlCorporates\QualityControlCorporateResource;
use App\Filament\Operations\Resources\QualityControlSurveys\Pages\ListQualityControlSurveys;

class ListQualityControlCorporates extends ListQualityControlSurveys
{
    protected static string $resource = QualityControlCorporateResource::class;
}
