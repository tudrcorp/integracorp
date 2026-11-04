<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\QualityControlIndividuals\Pages;

use App\Filament\Operations\Resources\QualityControlIndividuals\QualityControlIndividualResource;
use App\Filament\Operations\Resources\QualityControlSurveys\Pages\ViewQualityControlSurvey;

class ViewQualityControlIndividual extends ViewQualityControlSurvey
{
    protected static string $resource = QualityControlIndividualResource::class;
}
