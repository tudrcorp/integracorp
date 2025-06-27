<?php

namespace App\Filament\Resources\Coverages\Pages;

use App\Filament\Resources\Coverages\CoverageResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditCoverage extends EditRecord
{
    protected static string $resource = CoverageResource::class;

    protected static ?string $title = 'EDITAR';

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}