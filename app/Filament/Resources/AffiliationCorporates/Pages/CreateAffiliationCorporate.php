<?php

namespace App\Filament\Resources\AffiliationCorporates\Pages;

use App\Models\CorporateQuote;
use Filament\Resources\Pages\CreateRecord;
use App\Filament\Resources\AffiliationCorporates\AffiliationCorporateResource;

class CreateAffiliationCorporate extends CreateRecord
{
    protected static string $resource = AffiliationCorporateResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {

        if (isset($data['corporate_quote_id'])) {

            /**Si la cotizacion fue generada por una solicitud */
            $data_agent_or_agency = CorporateQuote::select('agent_id', 'code_agency', 'owner_code', 'id')
                ->where('id', $data['corporate_quote_id'])
                ->first();
            $data['owner_code']     = $data_agent_or_agency->owner_code;
            $data['agent_id']       = $data_agent_or_agency->owner_code != null ? $data_agent_or_agency->agent_id : null;
            $data['code_agency']    =  $data_agent_or_agency->code_agency;
        } elseif ($data['code_agency'] == null) {
            $data['owner_code']     = 'TDG-100';
            $data['code_agency']    = 'TDG-100';
            $data['agent_id']       = null;
        } else {
            $data['owner_code']     = $data['code_agency'];
            $data['code_agency']    = $data['code_agency'];
            $data['agent_id']       = $data['agent_id'];
        }

        return $data;
    }
}