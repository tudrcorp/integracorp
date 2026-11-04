<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\QualityControlCorporates\Pages;

use App\Filament\Operations\Resources\QualityControlCorporates\QualityControlCorporateResource;
use App\Filament\Operations\Resources\QualityControlSurveys\Pages\EditQualityControlSurvey;

class EditQualityControlCorporate extends EditQualityControlSurvey
{
    protected static string $resource = QualityControlCorporateResource::class;
}
