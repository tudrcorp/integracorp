<?php

namespace App\Filament\Master\Resources\Agents\Pages;

use App\Filament\Master\Resources\Agents\AgentResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditAgent extends EditRecord
{
    protected static string $resource = AgentResource::class;

    protected static ?string $title = 'EDITAR AGENTE';

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    //afterEdit()
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['fir_dig_agent']          = $data['fir_dig_agent']        == '' ? '0' : $data['fir_dig_agent'];
        $data['fir_dig_agency']         = $data['fir_dig_agency']       == '' ? '0' : $data['fir_dig_agency'];
        $data['file_ci_rif']            = $data['file_ci_rif']          == '' ? '0' : $data['file_ci_rif'];
        $data['file_w8_w9']             = $data['file_w8_w9']           == '' ? '0' : $data['file_w8_w9'];
        $data['file_account_usd']       = $data['file_account_usd']     == '' ? '0' : $data['file_account_usd'];
        $data['file_account_bsd']       = $data['file_account_bsd']     == '' ? '0' : $data['file_account_bsd'];
        $data['file_account_zelle']     = $data['file_account_zelle']   == '' ? '0' : $data['file_account_zelle'];

        return $data;
    }
}