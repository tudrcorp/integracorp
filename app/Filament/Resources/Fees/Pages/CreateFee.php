<?php

namespace App\Filament\Resources\Fees\Pages;

use App\Models\AgeRange;
use App\Models\Coverage;
use Filament\Resources\Pages\CreateRecord;
use App\Filament\Resources\Fees\FeeResource;

class CreateFee extends CreateRecord
{
    protected static string $resource = FeeResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['range']          = AgeRange::find($data['age_range_id'])->range;
        $data['coverage']       = Coverage::find($data['coverage_id'])->price;
        return $data;
    }
}