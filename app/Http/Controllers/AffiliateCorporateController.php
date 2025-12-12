<?php

namespace App\Http\Controllers;

use PgSql\Lob;
use App\Models\AgeRange;
use Illuminate\Http\Request;
use App\Models\AffiliateCorporate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;

class AffiliateCorporateController extends Controller
{
    public static function addAffiliate($data, $ownerRelationship)
    {

        try {

            if ($data['payment_frequency'] == 'ANUAL') {
                $total_amount = $data['fee'];
            }
            if ($data['payment_frequency'] == 'SEMESTRAL') {
                $total_amount = $data['fee'] / 2;
            }
            if ($data['payment_frequency'] == 'TRIMESTRAL') {
                $total_amount = $data['fee'] / 4;
            }

            $subtotal_anual = $data['fee'];
            $subtotal_payment_frequency = $total_amount;
            $subtotal_daily = $data['fee'] / 30;


            $data['total_amount'] = $total_amount;
            $data['subtotal_anual'] = $subtotal_anual;
            $data['subtotal_payment_frequency'] = $subtotal_payment_frequency;
            $data['subtotal_daily'] = $subtotal_daily;

            $data['status'] = 'ACTIVO';
            $data['created_by'] = Auth::user()->id;

            $data['affiliation_corporate_id'] = $ownerRelationship->id;

            //... Guardo el registro nuevo del Afiliado Corporativo
            $created_record = AffiliateCorporate::create($data);

            //... Actualizo el monto de ma Afiliacion corporativa
            if ($created_record) {
                //... Actualizo el monto de ma Afiliacion corporativa
                $ownerRelationship->update([
                    'fee_anual'     => $ownerRelationship->fee_anual + $data['fee'],
                    'total_amount'  => $ownerRelationship->total_amount + $total_amount,
                    'poblation'     => $ownerRelationship->poblation + 1

                ]);
            }

            //...Actualizo el numero de afiliados en la tabla de planes corporativos de acuerdo al rango etareo y la cobertura
            $updateAffiliate = $ownerRelationship->affiliationCorporatePlans()
                ->where('plan_id', $data['plan_id'])
                ->where('coverage_id', $data['coverage_id'])
                ->where('age_range_id', $data['age_range_id'])
                ->first();

            if ($updateAffiliate != null) {
                $updateAffiliate->update([
                    'total_persons' => $updateAffiliate->total_persons + 1
                ]);
            }


            return true;
        } catch (\Throwable $th) {
            dd($th);
            Log::error($th);
            Notification::make()
                ->danger()
                ->title('ERROR AL CREAR AFILIADO CORPORATIVO')
                ->body($th->getMessage())
                ->send();
        }
    }

    public static function clearAffiliate($record, $owner)
    {
        // dd($owner);
        try {

            $rango_edad_id = null;

            //En la Tabla de planes afiliados busco para ver cuales son los rangos de edad que estan afiliados
            $planAffiliates = $owner->affiliationCorporatePlans()->get()->toArray();

            for ($i = 0; $i < count($planAffiliates); $i++) {
                $rango_edad = AgeRange::where('id', $planAffiliates[$i]['age_range_id'])->first();
                if($record->age >= $rango_edad->age_init && $record->age <= $rango_edad->age_end)
                {
                    $rango_edad_id = $rango_edad->id;
                }
                continue;
            }
            
            $update = $owner->affiliationCorporatePlans()->where('age_range_id', $rango_edad_id)->first();
            
            //Actualizo el total de afiliados y actualizo los calculos de la afiliacion
            $update->total_persons      = $update->total_persons - 1;
            $update->subtotal_anual     = $update->fee * $update->total_persons;
            $update->subtotal_quarterly = ($update->fee * $update->total_persons) / 4;
            $update->subtotal_biannual  = ($update->fee * $update->total_persons) / 2;
            $update->subtotal_monthly   = ($update->fee * $update->total_persons) / 12;
            $update->save();

            //Actualizo la informacion de la afiliacion corporativa en la tabla de afiliaciones corporativas
            $owner->fee_anual       = $owner->affiliationCorporatePlans()->sum('subtotal_anual');
            if ($owner->payment_frequency == 'TRIMESTRAL') {
                $owner->total_amount = $owner->fee_anual / 4;
            }
            if ($owner->payment_frequency == 'SEMESTRAL') {
                $owner->total_amount = $owner->fee_anual / 2;
            }
            if ($owner->payment_frequency == 'MENSUAL') {
                $owner->total_amount = $owner->fee_anual / 12;
            }
            if ($owner->payment_frequency == 'ANUAL') {
                $owner->total_amount = $owner->fee_anual;
            }
            $owner->poblation       = $update->total_persons;
            $owner->save();

            //... Actualizo el familiar
            $record->update([
                'status' => 'INACTIVO'
            ]);

            Notification::make()
                ->success()
                ->title('Afiliacion de Baja')
                ->send();

                return true;
                
        } catch (\Throwable $th) {
            Log::error($th);
            Notification::make()
                ->danger()
                ->title('ERROR AL BORRAR AFILIADO CORPORATIVO')
                ->body($th->getMessage())
                ->send();
        }
    }
}