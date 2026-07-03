<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\PlanGeneratorImages\Pages;

use App\Filament\Business\Resources\PlanGeneratorImages\PlanGeneratorImageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPlanGeneratorImages extends ListRecords
{
    protected static string $resource = PlanGeneratorImageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
