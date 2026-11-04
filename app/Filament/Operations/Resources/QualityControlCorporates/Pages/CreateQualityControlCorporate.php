<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\QualityControlCorporates\Pages;

use App\Filament\Operations\Resources\QualityControlCorporates\QualityControlCorporateResource;
use App\Filament\Operations\Resources\QualityControlSurveys\Pages\CreateQualityControlSurvey;

class CreateQualityControlCorporate extends CreateQualityControlSurvey
{
    protected static string $resource = QualityControlCorporateResource::class;
}
