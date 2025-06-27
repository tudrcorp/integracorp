<?php

namespace App\Filament\Resources\Fees\Pages;

use App\Models\AgeRange;
use App\Models\Coverage;
use Filament\Actions\ViewAction;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use App\Filament\Resources\Fees\FeeResource;

class EditFee extends EditRecord
{
    protected static string $resource = FeeResource::class;

    protected static ?string $title = 'Editar Tarifas';

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['range']          = AgeRange::find($data['age_range_id'])->range;
        $data['coverage']       = isset($data['coverage_id']) == null ? null : Coverage::find($data['coverage_id'])->price;
        return $data;
    }
}