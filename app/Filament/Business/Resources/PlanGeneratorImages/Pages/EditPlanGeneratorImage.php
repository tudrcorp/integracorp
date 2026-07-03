<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\PlanGeneratorImages\Pages;

use App\Filament\Business\Resources\PlanGeneratorImages\PlanGeneratorImageResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPlanGeneratorImage extends EditRecord
{
    protected static string $resource = PlanGeneratorImageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
