<?php

namespace App\Http\Controllers;

use App\Models\AffiliateCorporate;
use App\Support\SecurityAudit;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AffiliationCorporateController extends Controller
{
    /**
     * @var list<string>
     */
    private const USD_PAYMENT_METHODS = [
        'EFECTIVO US$',
        'ZELLE',
        'TRANSFERENCIA US$',
        'LINK DE PAGO',
    ];

    public static function isUsdPaymentMethod(mixed $method): bool
    {
        return is_string($method) && in_array($method, self::USD_PAYMENT_METHODS, true);
    }

    public static function uploadPayment($record, $data, $type_roll): bool
    {

        try {
            DB::beginTransaction();

            $latestMembershipIdBefore = $record->paid_membership_corporates()->max('id');

            // 1. Actualizamos la tabla de afiliaciones
            $record->update([
                'payment_frequency' => $record['payment_frequency'],
                'poblation' => AffiliateCorporate::select('affiliation_corporate_id')->where('affiliation_corporate_id', $record->id)->count(),
            ]);

            if ($record['payment_frequency'] == 'ANUAL') {

                /** PAGO USD */
                if (self::isUsdPaymentMethod($data['payment_method'] ?? null)) {

                    $record->paid_membership_corporates()->create([
                        'affiliation_corporate_id' => $record->id,
                        'agent_id' => $record->agent_id,
                        'code_agency' => $record->code_agency,
                        // 'plan_id'                   => $data['plan_id'],
                        // 'coverage_id'               => $data['coverage_id'],
                        'total_amount' => $data['total_amount'],
                        'pay_amount_usd' => $data['total_amount'],
                        'pay_amount_ves' => isset($data['pay_amount_ves']) ? $data['pay_amount_ves'] : 0.00,
                        'document_usd' => isset($data['document_usd']) ? $data['document_usd'] : 'N/A',
                        'document_ves' => isset($data['document_ves']) ? $data['document_ves'] : 'N/A',
                        'payment_method' => $data['payment_method'],
                        'payment_method_usd' => 'N/A',
                        'payment_method_ves' => 'N/A',
                        'reference_payment_usd' => isset($data['reference_payment_usd']) ? $data['reference_payment_usd'] : 'N/A',
                        'reference_payment_ves' => isset($data['reference_payment_ves']) ? $data['reference_payment_ves'] : 'N/A',
                        'bank_usd' => isset($data['bank_usd']) ? $data['bank_usd'] : 'N/A',
                        'bank_ves' => isset($data['bank_ves']) ? $data['bank_ves'] : 'N/A',
                        'payment_frequency' => $record['payment_frequency'],
                        'payment_date' => now()->format('d-m-Y'),
                        'prox_payment_date' => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'observations_payment' => $data['observations_payment'] == null ? 'N/A' : $data['observations_payment'],
                        'renewal_date' => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'created_by' => Auth::user()->name,
                        'type_roll' => $type_roll,
                        'date_payment_voucher' => $data['date_payment_voucher'] ?? null,
                    ]);
                }

                /** PAGO BSD */
                if ($data['payment_method'] == 'PAGO MOVIL VES' || $data['payment_method'] == 'TRANSFERENCIA VES') {

                    $record->paid_membership_corporates()->create([
                        'affiliation_corporate_id' => $record->id,
                        'agent_id' => $record->agent_id,
                        'code_agency' => $record->code_agency,
                        // 'plan_id'                   => $data['plan_id'],
                        // 'coverage_id'               => $data['coverage_id'],
                        'total_amount' => $data['total_amount'],
                        'tasa_bcv' => $data['tasa_bcv'],
                        'pay_amount_usd' => isset($data['pay_amount_usd']) ? $data['pay_amount_usd'] : 0.00,
                        'pay_amount_ves' => $data['pay_amount_ves'],
                        'document_ves' => $data['document_ves'],
                        'document_usd' => isset($data['document_usd']) ? $data['document_usd'] : 'N/A',
                        'payment_method' => $data['payment_method'],
                        'payment_method_usd' => 'N/A',
                        'payment_method_ves' => 'N/A',
                        'payment_frequency' => $record['payment_frequency'],
                        'payment_date' => now()->format('d-m-Y'),
                        'prox_payment_date' => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'reference_payment_ves' => $data['reference_payment_ves'],
                        'reference_payment_usd' => isset($data['reference_payment_usd']) ? $data['reference_payment_usd'] : 'N/A',
                        'observations_payment' => $data['observations_payment'] == null ? 'N/A' : $data['observations_payment'],
                        'bank_usd' => isset($data['bank_usd']) ? $data['bank_usd'] : 'N/A',
                        'bank_ves' => $data['bank_ves'],
                        'renewal_date' => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'created_by' => Auth::user()->name,
                        'type_roll' => $type_roll,
                        'date_payment_voucher' => $data['date_payment_voucher'] ?? null,
                    ]);
                }

                /** PAGO MULTIPLE */
                if ($data['payment_method'] == 'MULTIPLE') {

                    $record->paid_membership_corporates()->create([
                        'affiliation_corporate_id' => $record->id,
                        'agent_id' => $record->agent_id,
                        'code_agency' => $record->code_agency,
                        // 'plan_id'                   => $data['plan_id'],
                        // 'coverage_id'               => $data['coverage_id'],
                        'total_amount' => $data['total_amount'],
                        'tasa_bcv' => $data['tasa_bcv'],
                        'pay_amount_usd' => $data['pay_amount_usd'],
                        'pay_amount_ves' => $data['pay_amount_ves'],
                        'document_usd' => $data['document_usd'] == null ? 'N/A' : $data['document_usd'],
                        'document_ves' => $data['document_ves'],
                        'payment_method' => $data['payment_method'],
                        'payment_method_usd' => $data['payment_method_usd'],
                        'payment_method_ves' => $data['payment_method_ves'],
                        'payment_frequency' => $record['payment_frequency'],
                        'payment_date' => now()->format('d-m-Y'),
                        'prox_payment_date' => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'reference_payment_usd' => $data['reference_payment_usd'] == null ? 'N/A' : $data['reference_payment_usd'],
                        'reference_payment_ves' => $data['reference_payment_ves'],
                        'observations_payment' => $data['observations_payment'] == null ? 'N/A' : $data['observations_payment'],
                        'bank_usd' => $data['bank_usd'] == null ? 'N/A' : $data['bank_usd'],
                        'bank_ves' => $data['bank_ves'] ?? 'N/A',
                        'renewal_date' => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'created_by' => Auth::user()->name,
                        'type_roll' => $type_roll,
                        'date_payment_voucher' => $data['date_payment_voucher'] ?? null,
                    ]);
                }
            }

            if ($record['payment_frequency'] == 'TRIMESTRAL') {

                /** PAGO USD */
                if (self::isUsdPaymentMethod($data['payment_method'] ?? null)) {

                    $record->paid_membership_corporates()->create([
                        'affiliation_corporate_id' => $record->id,
                        'agent_id' => $record->agent_id,
                        'code_agency' => $record->code_agency,
                        // 'plan_id'                   => $data['plan_id'],
                        // 'coverage_id'               => $data['coverage_id'],
                        'total_amount' => $data['total_amount'],
                        'pay_amount_usd' => $data['total_amount'],
                        'pay_amount_ves' => isset($data['pay_amount_ves']) ? $data['pay_amount_ves'] : 0.00,
                        'document_usd' => isset($data['document_usd']) ? $data['document_usd'] : 'N/A',
                        'document_ves' => isset($data['document_ves']) ? $data['document_ves'] : 'N/A',
                        'payment_method' => $data['payment_method'],
                        'reference_payment_usd' => isset($data['reference_payment_usd']) ? $data['reference_payment_usd'] : 'N/A',
                        'reference_payment_ves' => isset($data['reference_payment_ves']) ? $data['reference_payment_ves'] : 'N/A',
                        'bank_usd' => isset($data['bank_usd']) ? $data['bank_usd'] : 'N/A',
                        'bank_ves' => isset($data['bank_ves']) ? $data['bank_ves'] : 'N/A',
                        'payment_frequency' => $record['payment_frequency'],
                        'payment_date' => now()->format('d-m-Y'),
                        'prox_payment_date' => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'observations_payment' => $data['observations_payment'] == null ? 'N/A' : $data['observations_payment'],
                        'renewal_date' => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'created_by' => Auth::user()->name,
                        'type_roll' => $type_roll,
                        'date_payment_voucher' => $data['date_payment_voucher'] ?? null,
                    ]);
                }

                /** PAGO BSD */
                if ($data['payment_method'] == 'PAGO MOVIL VES' || $data['payment_method'] == 'TRANSFERENCIA VES') {

                    $record->paid_membership_corporates()->create([
                        'affiliation_corporate_id' => $record->id,
                        'agent_id' => $record->agent_id,
                        'code_agency' => $record->code_agency,
                        // 'plan_id'                   => $data['plan_id'],
                        // 'coverage_id'               => $data['coverage_id'],
                        'total_amount' => $data['total_amount'],
                        'tasa_bcv' => $data['tasa_bcv'],
                        'pay_amount_usd' => isset($data['pay_amount_usd']) ? $data['pay_amount_usd'] : 0.00,
                        'pay_amount_ves' => $data['pay_amount_ves'],
                        'document_ves' => $data['document_ves'],
                        'document_usd' => isset($data['document_usd']) ? $data['document_usd'] : 'N/A',
                        'payment_method' => $data['payment_method'],
                        'payment_frequency' => $record['payment_frequency'],
                        'payment_date' => now()->format('d-m-Y'),
                        'prox_payment_date' => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'reference_payment_ves' => $data['reference_payment_ves'],
                        'reference_payment_usd' => isset($data['reference_payment_usd']) ? $data['reference_payment_usd'] : 'N/A',
                        'observations_payment' => $data['observations_payment'] == null ? 'N/A' : $data['observations_payment'],
                        'bank_usd' => isset($data['bank_usd']) ? $data['bank_usd'] : 'N/A',
                        'bank_ves' => $data['bank_ves'],
                        'renewal_date' => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'created_by' => Auth::user()->name,
                        'type_roll' => $type_roll,
                        'date_payment_voucher' => $data['date_payment_voucher'] ?? null,
                    ]);
                }

                /** PAGO MULTIPLE */
                if ($data['payment_method'] == 'MULTIPLE') {

                    $record->paid_membership_corporates()->create([
                        'affiliation_corporate_id' => $record->id,
                        'agent_id' => $record->agent_id,
                        'code_agency' => $record->code_agency,
                        // 'plan_id'                   => $data['plan_id'],
                        // 'coverage_id'               => $data['coverage_id'],
                        'total_amount' => $data['total_amount'],
                        'tasa_bcv' => $data['tasa_bcv'],
                        'pay_amount_usd' => $data['pay_amount_usd'],
                        'pay_amount_ves' => $data['pay_amount_ves'],
                        'document_usd' => $data['document_usd'] == null ? 'N/A' : $data['document_usd'],
                        'document_ves' => $data['document_ves'],
                        'payment_method' => $data['payment_method'],
                        'payment_method_usd' => $data['payment_method_usd'],
                        'payment_method_ves' => $data['payment_method_ves'],
                        'payment_frequency' => $record['payment_frequency'],
                        'payment_date' => now()->format('d-m-Y'),
                        'prox_payment_date' => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'reference_payment_usd' => $data['reference_payment_usd'] == null ? 'N/A' : $data['reference_payment_usd'],
                        'reference_payment_ves' => $data['reference_payment_ves'],
                        'observations_payment' => $data['observations_payment'] == null ? 'N/A' : $data['observations_payment'],
                        'bank_usd' => $data['bank_usd'] == null ? 'N/A' : $data['bank_usd'],
                        'bank_ves' => $data['bank_ves'] ?? 'N/A',
                        'renewal_date' => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'created_by' => Auth::user()->name,
                        'type_roll' => $type_roll,
                        'date_payment_voucher' => $data['date_payment_voucher'] ?? null,
                    ]);
                }
            }

            if ($record['payment_frequency'] == 'SEMESTRAL') {

                /** PAGO USD */
                if (self::isUsdPaymentMethod($data['payment_method'] ?? null)) {

                    $record->paid_membership_corporates()->create([
                        'affiliation_corporate_id' => $record->id,
                        'agent_id' => $record->agent_id,
                        'code_agency' => $record->code_agency,
                        // 'plan_id'                   => $data['plan_id'],
                        // 'coverage_id'               => $data['coverage_id'],
                        'total_amount' => $data['total_amount'],
                        'pay_amount_usd' => $data['total_amount'],
                        'pay_amount_ves' => isset($data['pay_amount_ves']) ? $data['pay_amount_ves'] : 0.00,
                        'document_usd' => isset($data['document_usd']) ? $data['document_usd'] : 'N/A',
                        'document_ves' => isset($data['document_ves']) ? $data['document_ves'] : 'N/A',
                        'payment_method' => $data['payment_method'],
                        'reference_payment_usd' => isset($data['reference_payment_usd']) ? $data['reference_payment_usd'] : 'N/A',
                        'reference_payment_ves' => isset($data['reference_payment_ves']) ? $data['reference_payment_ves'] : 'N/A',
                        'bank_usd' => isset($data['bank_usd']) ? $data['bank_usd'] : 'N/A',
                        'bank_ves' => isset($data['bank_ves']) ? $data['bank_ves'] : 'N/A',
                        'payment_frequency' => $record['payment_frequency'],
                        'payment_date' => now()->format('d-m-Y'),
                        'prox_payment_date' => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'observations_payment' => $data['observations_payment'] == null ? 'N/A' : $data['observations_payment'],
                        'renewal_date' => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'created_by' => Auth::user()->name,
                        'type_roll' => $type_roll,
                        'date_payment_voucher' => $data['date_payment_voucher'] ?? null,
                    ]);
                }

                /** PAGO BSD */
                if ($data['payment_method'] == 'PAGO MOVIL VES' || $data['payment_method'] == 'TRANSFERENCIA VES') {

                    $record->paid_membership_corporates()->create([
                        'affiliation_corporate_id' => $record->id,
                        'agent_id' => $record->agent_id,
                        'code_agency' => $record->code_agency,
                        // 'plan_id'                   => $data['plan_id'],
                        // 'coverage_id'               => $data['coverage_id'],
                        'total_amount' => $data['total_amount'],
                        'tasa_bcv' => $data['tasa_bcv'],
                        'pay_amount_usd' => isset($data['pay_amount_usd']) ? $data['pay_amount_usd'] : 0.00,
                        'pay_amount_ves' => $data['pay_amount_ves'],
                        'document_ves' => $data['document_ves'],
                        'document_usd' => isset($data['document_usd']) ? $data['document_usd'] : 'N/A',
                        'payment_method' => $data['payment_method'],
                        'payment_frequency' => $record['payment_frequency'],
                        'payment_date' => now()->format('d-m-Y'),
                        'prox_payment_date' => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'reference_payment_ves' => $data['reference_payment_ves'],
                        'reference_payment_usd' => isset($data['reference_payment_usd']) ? $data['reference_payment_usd'] : 'N/A',
                        'observations_payment' => $data['observations_payment'] == null ? 'N/A' : $data['observations_payment'],
                        'bank_usd' => isset($data['bank_usd']) ? $data['bank_usd'] : 'N/A',
                        'bank_ves' => $data['bank_ves'],
                        'renewal_date' => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'created_by' => Auth::user()->name,
                        'type_roll' => $type_roll,
                        'date_payment_voucher' => $data['date_payment_voucher'] ?? null,
                    ]);
                }

                /** PAGO MULTIPLE */
                if ($data['payment_method'] == 'MULTIPLE') {

                    $record->paid_membership_corporates()->create([
                        'affiliation_corporate_id' => $record->id,
                        'agent_id' => $record->agent_id,
                        'code_agency' => $record->code_agency,
                        // 'plan_id'                   => $data['plan_id'],
                        // 'coverage_id'               => $data['coverage_id'],
                        'total_amount' => $data['total_amount'],
                        'tasa_bcv' => $data['tasa_bcv'],
                        'pay_amount_usd' => $data['pay_amount_usd'],
                        'pay_amount_ves' => $data['pay_amount_ves'],
                        'document_usd' => $data['document_usd'] == null ? 'N/A' : $data['document_usd'],
                        'document_ves' => $data['document_ves'],
                        'payment_method' => $data['payment_method'],
                        'payment_method_usd' => $data['payment_method_usd'],
                        'payment_method_ves' => $data['payment_method_ves'],
                        'payment_frequency' => $record['payment_frequency'],
                        'payment_date' => now()->format('d-m-Y'),
                        'prox_payment_date' => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'reference_payment_usd' => $data['reference_payment_usd'] == null ? 'N/A' : $data['reference_payment_usd'],
                        'reference_payment_ves' => $data['reference_payment_ves'],
                        'observations_payment' => $data['observations_payment'] == null ? 'N/A' : $data['observations_payment'],
                        'bank_usd' => $data['bank_usd'] == null ? 'N/A' : $data['bank_usd'],
                        'bank_ves' => $data['bank_ves'] ?? 'N/A',
                        'renewal_date' => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'created_by' => Auth::user()->name,
                        'type_roll' => $type_roll,
                        'date_payment_voucher' => $data['date_payment_voucher'] ?? null,
                    ]);
                }
            }

            if ($record['payment_frequency'] == 'MENSUAL') {

                /** PAGO USD */
                if (self::isUsdPaymentMethod($data['payment_method'] ?? null)) {

                    $record->paid_membership_corporates()->create([
                        'affiliation_corporate_id' => $record->id,
                        'agent_id' => $record->agent_id,
                        'code_agency' => $record->code_agency,
                        // 'plan_id'                   => $data['plan_id'],
                        // 'coverage_id'               => $data['coverage_id'],
                        'total_amount' => $data['total_amount'],
                        'pay_amount_usd' => $data['total_amount'],
                        'pay_amount_ves' => isset($data['pay_amount_ves']) ? $data['pay_amount_ves'] : 0.00,
                        'document_usd' => isset($data['document_usd']) ? $data['document_usd'] : 'N/A',
                        'document_ves' => isset($data['document_ves']) ? $data['document_ves'] : 'N/A',
                        'payment_method' => $data['payment_method'],
                        'reference_payment_usd' => isset($data['reference_payment_usd']) ? $data['reference_payment_usd'] : 'N/A',
                        'reference_payment_ves' => isset($data['reference_payment_ves']) ? $data['reference_payment_ves'] : 'N/A',
                        'bank_usd' => isset($data['bank_usd']) ? $data['bank_usd'] : 'N/A',
                        'bank_ves' => isset($data['bank_ves']) ? $data['bank_ves'] : 'N/A',
                        'payment_frequency' => $record['payment_frequency'],
                        'payment_date' => now()->format('d-m-Y'),
                        'prox_payment_date' => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'observations_payment' => $data['observations_payment'] == null ? 'N/A' : $data['observations_payment'],
                        'renewal_date' => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'created_by' => Auth::user()->name,
                        'type_roll' => $type_roll,
                        'date_payment_voucher' => $data['date_payment_voucher'] ?? null,
                    ]);
                }

                /** PAGO BSD */
                if ($data['payment_method'] == 'PAGO MOVIL VES' || $data['payment_method'] == 'TRANSFERENCIA VES') {

                    $record->paid_membership_corporates()->create([
                        'affiliation_corporate_id' => $record->id,
                        'agent_id' => $record->agent_id,
                        'code_agency' => $record->code_agency,
                        // 'plan_id'                   => $data['plan_id'],
                        // 'coverage_id'               => $data['coverage_id'],
                        'total_amount' => $data['total_amount'],
                        'tasa_bcv' => $data['tasa_bcv'],
                        'pay_amount_usd' => isset($data['pay_amount_usd']) ? $data['pay_amount_usd'] : 0.00,
                        'pay_amount_ves' => $data['pay_amount_ves'],
                        'document_ves' => $data['document_ves'],
                        'document_usd' => isset($data['document_usd']) ? $data['document_usd'] : 'N/A',
                        'payment_method' => $data['payment_method'],
                        'payment_frequency' => $record['payment_frequency'],
                        'payment_date' => now()->format('d-m-Y'),
                        'prox_payment_date' => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'reference_payment_ves' => $data['reference_payment_ves'],
                        'reference_payment_usd' => isset($data['reference_payment_usd']) ? $data['reference_payment_usd'] : 'N/A',
                        'observations_payment' => $data['observations_payment'] == null ? 'N/A' : $data['observations_payment'],
                        'bank_usd' => isset($data['bank_usd']) ? $data['bank_usd'] : 'N/A',
                        'bank_ves' => $data['bank_ves'],
                        'renewal_date' => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'created_by' => Auth::user()->name,
                        'type_roll' => $type_roll,
                        'date_payment_voucher' => $data['date_payment_voucher'] ?? null,
                    ]);
                }

                /** PAGO MULTIPLE */
                if ($data['payment_method'] == 'MULTIPLE') {

                    $record->paid_membership_corporates()->create([
                        'affiliation_corporate_id' => $record->id,
                        'agent_id' => $record->agent_id,
                        'code_agency' => $record->code_agency,
                        // 'plan_id'                   => $data['plan_id'],
                        // 'coverage_id'               => $data['coverage_id'],
                        'total_amount' => $data['total_amount'],
                        'tasa_bcv' => $data['tasa_bcv'],
                        'pay_amount_usd' => $data['pay_amount_usd'],
                        'pay_amount_ves' => $data['pay_amount_ves'],
                        'document_usd' => $data['document_usd'] == null ? 'N/A' : $data['document_usd'],
                        'document_ves' => $data['document_ves'],
                        'payment_method' => $data['payment_method'],
                        'payment_method_usd' => $data['payment_method_usd'],
                        'payment_method_ves' => $data['payment_method_ves'],
                        'payment_frequency' => $record['payment_frequency'],
                        'payment_date' => now()->format('d-m-Y'),
                        'prox_payment_date' => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'reference_payment_usd' => $data['reference_payment_usd'] == null ? 'N/A' : $data['reference_payment_usd'],
                        'reference_payment_ves' => $data['reference_payment_ves'],
                        'observations_payment' => $data['observations_payment'] == null ? 'N/A' : $data['observations_payment'],
                        'bank_usd' => $data['bank_usd'] == null ? 'N/A' : $data['bank_usd'],
                        'bank_ves' => $data['bank_ves'] ?? 'N/A',
                        'renewal_date' => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'created_by' => Auth::user()->name,
                        'type_roll' => $type_roll,
                        'date_payment_voucher' => $data['date_payment_voucher'] ?? null,
                    ]);
                }
            }

            $latestMembershipIdAfter = $record->paid_membership_corporates()->max('id');

            if ($latestMembershipIdAfter === null || (int) $latestMembershipIdAfter === (int) $latestMembershipIdBefore) {
                DB::rollBack();

                SecurityAudit::log('AUDIT_AFFILIATION_CORPORATE_PAYMENT_VOUCHER_UPLOAD_FAILED', 'affiliation-corporates.upload-payment', [
                    'affiliation_corporate_id' => $record->id,
                    'affiliation_code' => $record->code,
                    'payment_frequency' => $record['payment_frequency'] ?? null,
                    'payment_method' => $data['payment_method'] ?? null,
                    'type_roll' => $type_roll,
                    'reason' => 'unsupported_payment_method_or_frequency',
                ]);

                return false;
            }

            DB::commit();

            SecurityAudit::log('AUDIT_AFFILIATION_CORPORATE_PAYMENT_VOUCHER_UPLOADED', 'affiliation-corporates.upload-payment', [
                'affiliation_corporate_id' => $record->id,
                'affiliation_code' => $record->code,
                'payment_frequency' => $record['payment_frequency'] ?? null,
                'payment_method' => $data['payment_method'] ?? null,
                'type_roll' => $type_roll,
                'latest_paid_membership_id' => $latestMembershipIdAfter,
            ]);

            return true;
        } catch (\Throwable $th) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            SecurityAudit::log('AUDIT_AFFILIATION_CORPORATE_PAYMENT_VOUCHER_UPLOAD_FAILED', 'affiliation-corporates.upload-payment', [
                'affiliation_corporate_id' => $record->id ?? null,
                'affiliation_code' => $record->code ?? null,
                'payment_method' => $data['payment_method'] ?? null,
                'error' => $th->getMessage(),
            ]);

            Log::error($th->getMessage(), [
                'exception' => $th,
                'affiliation_corporate_id' => $record->id ?? null,
            ]);

            Notification::make()
                ->title('No se pudo registrar el comprobante')
                ->body($th->getMessage())
                ->danger()
                ->persistent()
                ->send();

            return false;
        }
    }

    /**
     * @param  iterable<int, \App\Models\AffiliationCorporate>  $records
     */
    public static function uploadPaymentMultipleAffiliationCorporates(iterable $records, array $data, string $type_roll): bool
    {
        try {
            DB::beginTransaction();

            foreach ($records as $record) {
                $paymentData = array_merge($data, [
                    'total_amount' => $record->total_amount,
                ]);

                $uploaded = self::uploadPayment($record, $paymentData, $type_roll);

                if ($uploaded !== true) {
                    DB::rollBack();

                    return false;
                }
            }

            DB::commit();

            return true;
        } catch (\Throwable $th) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::error($th->getMessage(), [
                'exception' => $th,
            ]);

            Notification::make()
                ->title('No se pudo registrar el comprobante')
                ->body($th->getMessage())
                ->danger()
                ->persistent()
                ->send();

            return false;
        }
    }
}
