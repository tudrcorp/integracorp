<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\QualityControlIndividuals\Pages;

use App\Filament\Operations\Resources\QualityControlIndividuals\QualityControlIndividualResource;
use App\Filament\Operations\Resources\QualityControlSurveys\Pages\CreateQualityControlSurvey;

class CreateQualityControlIndividual extends CreateQualityControlSurvey
{
    protected static string $resource = QualityControlIndividualResource::class;
}
