<?php

namespace App\Http\Controllers;

use App\Models\AffiliateCorporate;
use App\Models\AffiliationCorporate;
use App\Services\CorporateAffiliateRemovalService;
use App\Support\Affiliations\PersonIdentity\DuplicatePersonIdentityException;
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
            $data['business_unit_id'] = $data['business_unit_id'] ?? $ownerRelationship->business_unit_id;
            $data['business_line_id'] = $data['business_line_id'] ?? $ownerRelationship->business_line_id;
            $data['specific_business_unit'] = $data['specific_business_unit']
                ?? $ownerRelationship->specific_business_unit;

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
        } catch (DuplicatePersonIdentityException $exception) {
            throw $exception;
        } catch (\Throwable $th) {
            Log::error($th);
            Notification::make()
                ->danger()
                ->title('ERROR AL CREAR AFILIADO CORPORATIVO')
                ->body($th->getMessage())
                ->send();
        }
    }

    /**
     * Crea el afiliado corporativo ACTIVO sin tocar totales: de la afiliación, la fila de plan,
     * la cobranza y operaciones se encarga {@see \App\Support\Affiliations\Movements\AffiliateMovements}.
     *
     * @param  array<string, mixed>  $data
     */
    public static function createAffiliateRecord(array $data, AffiliationCorporate $owner): AffiliateCorporate
    {
        $fee = round((float) ($data['fee'] ?? 0), 2);
        $frequency = (string) ($data['payment_frequency'] ?? $owner->payment_frequency);
        $perPeriod = round(\App\Services\CorporateAffiliateRemovalService::annualFeeToPerPeriodAmount($fee, $frequency), 2);

        $data['total_amount'] = $perPeriod;
        $data['subtotal_anual'] = $fee;
        $data['subtotal_payment_frequency'] = $perPeriod;
        $data['subtotal_daily'] = round($fee / 30, 2);
        $data['payment_frequency'] = $frequency;
        $data['status'] = 'ACTIVO';
        $data['created_by'] = Auth::user()?->id;
        $data['affiliation_corporate_id'] = $owner->getKey();
        $data['business_unit_id'] = $data['business_unit_id'] ?? $owner->business_unit_id;
        $data['business_line_id'] = $data['business_line_id'] ?? $owner->business_line_id;
        $data['specific_business_unit'] = $data['specific_business_unit'] ?? $owner->specific_business_unit;

        return AffiliateCorporate::query()->create($data);
    }

    public static function clearAffiliate(AffiliateCorporate $record, AffiliationCorporate $owner): void
    {
        CorporateAffiliateRemovalService::deactivate($record, $owner);
    }
}
