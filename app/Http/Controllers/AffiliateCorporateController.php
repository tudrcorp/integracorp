<?php

namespace App\Http\Controllers;

use App\Models\AffiliateCorporate;
use App\Models\AffiliationCorporate;
use App\Services\CorporateAffiliateRemovalService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

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

            $created_record = AffiliateCorporate::create($data);

            if ($created_record) {
                $ownerRelationship->update([
                    'fee_anual' => $ownerRelationship->fee_anual + $data['fee'],
                    'total_amount' => $ownerRelationship->total_amount + $total_amount,
                    'poblation' => $ownerRelationship->poblation + 1,

                ]);
            }

            $updateAffiliate = $ownerRelationship->affiliationCorporatePlans()
                ->where('plan_id', $data['plan_id'])
                ->where('coverage_id', $data['coverage_id'])
                ->where('age_range_id', $data['age_range_id'])
                ->first();

            if ($updateAffiliate != null) {
                $updateAffiliate->update([
                    'total_persons' => $updateAffiliate->total_persons + 1,
                ]);
            }

            return true;
        } catch (\Throwable $th) {
            Log::error($th);
            Notification::make()
                ->danger()
                ->title('ERROR AL CREAR AFILIADO CORPORATIVO')
                ->body($th->getMessage())
                ->send();
        }
    }

    public static function clearAffiliate(AffiliateCorporate $record, AffiliationCorporate $owner): void
    {
        CorporateAffiliateRemovalService::deactivate($record, $owner);
    }
}
