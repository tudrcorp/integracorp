<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\PlanGeneratorImages\Pages;

use App\Filament\Business\Resources\PlanGeneratorImages\PlanGeneratorImageResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePlanGeneratorImage extends CreateRecord
{
    protected static string $resource = PlanGeneratorImageResource::class;
}
