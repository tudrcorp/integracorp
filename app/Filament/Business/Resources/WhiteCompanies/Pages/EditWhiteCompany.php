<?php

namespace App\Filament\Business\Resources\WhiteCompanies\Pages;

use Filament\Actions\DeleteAction;
use Illuminate\Support\Facades\Auth;
use Filament\Resources\Pages\EditRecord;
use App\Filament\Business\Resources\WhiteCompanies\WhiteCompanyResource;

class EditWhiteCompany extends EditRecord
{
    protected static string $resource = WhiteCompanyResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {

        $data['updated_by'] = Auth::user()->name;

        return $data;
    }
}