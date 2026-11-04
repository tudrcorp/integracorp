<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\QualityControlIndividuals\Pages;

use App\Filament\Operations\Resources\QualityControlIndividuals\QualityControlIndividualResource;
use App\Filament\Operations\Resources\QualityControlSurveys\Pages\EditQualityControlSurvey;

class EditQualityControlIndividual extends EditQualityControlSurvey
{
    protected static string $resource = QualityControlIndividualResource::class;
}
