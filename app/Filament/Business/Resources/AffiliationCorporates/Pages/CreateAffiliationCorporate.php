<?php

namespace App\Filament\Business\Resources\AffiliationCorporates\Pages;

use App\Models\User;
use App\Models\Agency;
use App\Models\AffiliateCorporate;
use App\Models\CorporateQuoteData;
use Illuminate\Support\Facades\Log;
use App\Models\DetailCorporateQuote;
use Illuminate\Support\Facades\Auth;
use App\Models\AfilliationCorporatePlan;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use App\Filament\Business\Resources\AffiliationCorporates\AffiliationCorporateResource;

class CreateAffiliationCorporate extends CreateRecord
{
    protected static string $resource = AffiliationCorporateResource::class;

    protected function getFormActions(): array
    {
        return [];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {

        $data['code_agency']    = $data['code_agency'] == null ? 'TDG-100' : $data['code_agency'];
        $data['agent_id']       = $data['agent_id'] == null ? null : $data['agent_id'];

        if ($data['code_agency'] != 'TDG-100') {
            $data['owner_code'] = Agency::where('code', $data['code_agency'])->first()->owner_code;
        } else {
            $data['owner_code'] = 'TDG-100';
        }


        return $data;
    }

    protected function afterCreate(): void
    {
        try {

            $record = $this->getRecord();
            
            /**
             * Actualizacion de la cotizacion
             * Se cambia el estatus de la cobertura de la cotizacion que selecciono el cliente
             * ----------------------------------------------------------------------------------------------------
             */
            $quote = DetailCorporateQuote::select('coverage_id', 'status', 'id')->where('coverage_id', $record->coverage_id)->firstOrFail();
            $quote->status = 'APROBADA';
            $quote->save();


            /**
             * Recupero los planes de afiliados seleccionados por el agente o la agencia
             * en la vista de cotizaciones corporativas
             * ----------------------------------------------------------------------------------------------------
             */
            $data_records = session()->get('data_records');

            for ($i = 0; $i < count($data_records); $i++) {

                /** Guardar los datos en la tabla de afiliados */
                $detailsAfiliationPlans = AfilliationCorporatePlan::create([
                    'affiliation_corporate_id'  => $record->id,
                    'code_affiliation'          => $record->code,
                    'plan_id'                   => $data_records[$i]['plan_id'],
                    'coverage_id'               => $data_records[$i]['coverage_id'],
                    'age_range_id'              => $data_records[$i]['age_range_id'],
                    'total_persons'             => $data_records[$i]['total_persons'],
                    'payment_frequency'         => $record->payment_frequency,
                    'fee'                       => $data_records[$i]['fee'],
                    'subtotal_anual'            => $data_records[$i]['subtotal_anual'],
                    'subtotal_quarterly'        => $data_records[$i]['subtotal_quarterly'],
                    'subtotal_biannual'         => $data_records[$i]['subtotal_biannual'],
                    'subtotal_monthly'          => $data_records[$i]['subtotal_monthly'],
                    'status'                    => 'PRE-AFILIADO',
                    'created_by'                => Auth::user()->name,
                ]);
            }

            //elimino la variable de sesion para evitar sobrecargar dicho contenedor
            session()->forget('data_records');

            //Cargamos los afiliados que son los que se importaron al momento de realizar la cotizacion
            $data_afiliados = CorporateQuoteData::where('corporate_quote_id', $record->corporate_quote_id)->get()->toArray();
            // dd($data_afiliados);
            for ($i = 0; $i < count($data_afiliados); $i++) {
                $afiliados_corporativos = new AffiliateCorporate();
                $afiliados_corporativos->last_name                  = $data_afiliados[$i]['last_name'];
                $afiliados_corporativos->first_name                 = $data_afiliados[$i]['first_name'];
                $afiliados_corporativos->nro_identificacion         = $data_afiliados[$i]['nro_identificacion'];
                $afiliados_corporativos->birth_date                 = $data_afiliados[$i]['birth_date'];
                $afiliados_corporativos->age                        = $data_afiliados[$i]['age'];
                $afiliados_corporativos->sex                        = $data_afiliados[$i]['sex'];
                $afiliados_corporativos->phone                      = $data_afiliados[$i]['phone'];
                $afiliados_corporativos->email                      = $data_afiliados[$i]['email'];
                $afiliados_corporativos->condition_medical          = $data_afiliados[$i]['condition_medical'];
                $afiliados_corporativos->initial_date               = $data_afiliados[$i]['initial_date'];
                $afiliados_corporativos->position_company           = $data_afiliados[$i]['position_company'];
                $afiliados_corporativos->address                    = $data_afiliados[$i]['address'];
                $afiliados_corporativos->full_name_emergency        = $data_afiliados[$i]['full_name_emergency'];
                $afiliados_corporativos->phone_emergency            = $data_afiliados[$i]['phone_emergency'];
                $afiliados_corporativos->affiliation_corporate_id   = $record->id;
                $afiliados_corporativos->status                     = 'PRE-APROBADA';
                $afiliados_corporativos->save();
                
            }

            //Actualizamos la cantidad de personas afiliadas
            $record->poblation = count($data_afiliados);
            $record->save();

        } catch (\Throwable $th) {
            Log::error($th->getMessage());
            Notification::make()
                ->title('Error al crear la afiliación corporativa')
                ->body($th->getMessage())
                ->danger()
                ->send();
        }
    }
}