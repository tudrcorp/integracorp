<?php

namespace App\Filament\Business\Resources\Coverages\Pages;

use App\Filament\Business\Resources\Coverages\CoverageResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditCoverage extends EditRecord
{
    protected static string $resource = CoverageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
