<?php

namespace App\Filament\Resources\Benefits\Pages;

use App\Filament\Resources\Benefits\BenefitResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditBenefit extends EditRecord
{
    protected static string $resource = BenefitResource::class;

    protected static ?string $title = 'EDITAR';

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}