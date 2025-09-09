<?php

namespace App\Filament\Resources\AffiliationCorporates\Pages;

use App\Models\CorporateQuote;
use App\Models\CorporateQuoteData;
use App\Models\AfilliationCorporatePlan;
use Filament\Notifications\Notification;
use App\Http\Controllers\UtilsController;
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

    protected function afterCreate(): void
    {
        //Información de la afiliación corporativa
        $record = $this->getRecord();
        
        //Información de los planes seleccionados por el agente
        $plans_records = session()->get('data_records');

        for ($i = 0; $i < count($plans_records); $i++) {
            AfilliationCorporatePlan::create([
                'affiliation_corporate_id'  => $record->id,
                'code_affiliation'          => $record->code,
                'plan_id'                   => $plans_records[$i]['plan_id'],
                'coverage_id'               => $plans_records[$i]['coverage_id'],
                'fee'                       => $plans_records[$i]['fee'],
                'age_range_id'              => $plans_records[$i]['age_range_id'],
                'total_persons'             => $plans_records[$i]['total_persons'],
                'payment_frequency'         => $record->payment_frequency,
                'subtotal_anual'            => $plans_records[$i]['subtotal_anual'],
                'subtotal_quarterly'        => $plans_records[$i]['subtotal_quarterly'],
                'subtotal_biannual'         => $plans_records[$i]['subtotal_biannual'],
                'status'                    => 'PRE-APROBADA',
            ]);
        }

        //Agregamos la data de la cotizacion corporativa a la tabla de afiliafos corporativos
        $data_to_affiliation_corporate = CorporateQuoteData::where('corporate_quote_id', $record->corporate_quote_id)->get();

        // Preparar un array de rangos de edad para búsqueda rápida
        $rangos = [];
        foreach ($plans_records as $plan) {
            $ageRange = $plan['age_range']; // array con 'from_age', 'to_age', etc.
            $rangos[] = [
                'plan' => $plan,
                'age_init' => (int) $ageRange['age_init'],
                'age_end' => (int) $ageRange['age_end'],
            ];
        }

        // Crear el array combinado
        $afiliadosConPlan = [];

        $afiliados = $data_to_affiliation_corporate->toArray();

        foreach ($afiliados as $afiliado) {
            // dd($afiliado);
            $edad = (int) $afiliado['age'];
            $planAsignado = null;

            foreach ($rangos as $rango) {
                if ($edad >= $rango['age_init'] && $edad <= $rango['age_end']) {
                    $planAsignado = $rango['plan'];
                    break;
                }
            }
            // Añadir información del plan y rango al afiliado
            $afiliado['plan_id']            = $planAsignado['plan_id'] ?? null;
            $afiliado['coverage_id']        = $planAsignado['coverage_id'] ?? null;
            $afiliado['fee']                = $planAsignado['fee'] ?? null;
            $afiliado['subtotal_anual']     = $afiliado['fee'] * 12 ?? null;
            $afiliado['payment_frequency']  = $record->payment_frequency;
            if($afiliado['payment_frequency'] == 'TRIMESTRAL'){
                $afiliado['subtotal_payment_frequency'] = $afiliado['subtotal_anual'] / 4;
            }
            if ($afiliado['payment_frequency'] == 'SEMESTRAL') {
                $afiliado['subtotal_payment_frequency'] = $afiliado['subtotal_anual'] / 2;
            }
            if ($afiliado['payment_frequency'] == 'ANUAL') {
                $afiliado['subtotal_payment_frequency'] = $afiliado['subtotal_anual'];
            }
            
            $afiliado['status'] = 'PRE-AFILIADO';

            // Añadir datos del rango
            $afiliado['age_range'] = $planAsignado['age_range'] ?? null;

            $afiliadosConPlan[] = $afiliado;
        }

        $record->corporateAffiliates()->createMany($afiliadosConPlan);

        // dd($record->corporateAffiliates);

        //Eliminamos la poblacion asociada a la cotizacion porque ya esta afiliada
        CorporateQuoteData::where('corporate_quote_id', $record->corporate_quote_id)->delete();

        //Actualizamos el estatus de la cotizacion corporativa
        $update_status = CorporateQuote::find($record->corporate_quote_id);
        $update_status->status = 'APROBADA';
        $update_status->save();

        /**
         * Generate Certificado de Afiliación
         */
        UtilsController::createCertificateCorporate($record, $record->corporateAffiliates);
        // $this->getRecord()->sendCertificate($record, $titular, $affiliates);

        Notification::make()
            ->title('Pre-afiliación generada con exito.')
            ->icon('heroicon-s-check-circle')
            ->iconColor('success')
            ->color('success')
            ->send();
    }
}