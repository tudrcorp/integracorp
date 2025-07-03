<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\AffiliateCorporate;
use Illuminate\Support\Facades\Log;
use App\Models\AffiliationCorporate;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;

class AffiliationCorporateController extends Controller
{
    public static function uploadPayment($record, $data, $type_roll)
    {

        try {
            // dd($record);
            // $validate = self::getValidation($record, $data);

            //1. Actualizamos la tabla de afiliaciones
            $record->update([
                'payment_frequency'     => $data['payment_frequency'],
                'plan_id'               => $data['plan_id'],
                'coverage_id'           => $data['coverage_id'],
                'corporate_members'     => AffiliateCorporate::select('affiliation_corporate_id')->where('affiliation_corporate_id', $record->id)->count(),
            ]);

            if ($data['payment_frequency'] == 'ANUAL') {

                /** PAGO USD */
                if ($data['payment_method'] == 'EFECTIVO US$' || $data['payment_method'] == 'ZELLE') {

                    $record->paid_membership_corporates()->create([
                        'affiliation_corporate_id'            => $record->id,
                        'agent_id'                  => $record->agent_id,
                        'code_agency'               => $record->code_agency,
                        'plan_id'                   => $data['plan_id'],
                        'coverage_id'               => $data['coverage_id'],
                        'total_amount'              => $data['total_amount'],
                        'pay_amount_usd'            => $data['total_amount'],
                        'pay_amount_ves'            => isset($data['pay_amount_ves']) ? $data['pay_amount_ves'] : 0.00,
                        'document_usd'              => isset($data['document_usd']) ? $data['document_usd'] : 'N/A',
                        'document_ves'              => isset($data['document_ves']) ? $data['document_ves'] : 'N/A',
                        'payment_method'            => $data['payment_method'],
                        'payment_method_usd'        => 'N/A',
                        'payment_method_ves'        => 'N/A',
                        'reference_payment_zelle'   => isset($data['reference_payment_zelle']) ? $data['reference_payment_zelle'] : 'N/A',
                        'reference_payment_ves'     => isset($data['reference_payment_ves']) ? $data['reference_payment_ves'] : 'N/A',
                        'bank_usd'                  => isset($data['bank_usd']) ? $data['bank_usd'] : 'N/A',
                        'bank_ves'                  => isset($data['bank_ves']) ? $data['bank_ves'] : 'N/A',
                        'payment_frequency'         => $data['payment_frequency'],
                        'payment_date'              => now()->format('d-m-Y'),
                        'prox_payment_date'         => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'observations_payment'      => $data['observations_payment'] == null ? 'N/A' : $data['observations_payment'],
                        'renewal_date'              => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'created_by'                => Auth::user()->name,
                        'type_roll'                 => $type_roll,
                    ]);
                }

                /** PAGO BSD */
                if ($data['payment_method'] == 'PAGO MOVIL VES' || $data['payment_method'] == 'TRANSFERENCIA VES') {

                    $record->paid_membership_corporates()->create([
                        'affiliation_corporate_id'            => $record->id,
                        'agent_id'                  => $record->agent_id,
                        'code_agency'               => $record->code_agency,
                        'plan_id'                   => $data['plan_id'],
                        'coverage_id'               => $data['coverage_id'],
                        'total_amount'              => $data['total_amount'],
                        'tasa_bcv'                  => $data['tasa_bcv'],
                        'pay_amount_usd'            => isset($data['pay_amount_usd']) ? $data['pay_amount_usd'] : 0.00,
                        'pay_amount_ves'            => $data['pay_amount_ves'],
                        'document_ves'              => $data['document_ves'],
                        'document_usd'              => isset($data['document_usd']) ? $data['document_usd'] : 'N/A',
                        'payment_method'            => $data['payment_method'],
                        'payment_method_usd'            => 'N/A',
                        'payment_method_ves'            => 'N/A',
                        'payment_frequency'         => $data['payment_frequency'],
                        'payment_date'              => now()->format('d-m-Y'),
                        'prox_payment_date'         => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'reference_payment_ves'     => $data['reference_payment_ves'],
                        'reference_payment_zelle'   => isset($data['reference_payment_zelle']) ? $data['reference_payment_zelle'] : 'N/A',
                        'observations_payment'      => $data['observations_payment'] == null ? 'N/A' : $data['observations_payment'],
                        'bank_usd'                  => isset($data['bank_usd']) ? $data['bank_usd'] : 'N/A',
                        'bank_ves'                  => $data['bank_ves'],
                        'renewal_date'              => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'created_by'                => Auth::user()->name,
                        'type_roll'                 => $type_roll,
                    ]);
                }

                /** PAGO MULTIPLE */
                if ($data['payment_method'] == 'MULTIPLE') {

                    $record->paid_membership_corporates()->create([
                        'affiliation_corporate_id'            => $record->id,
                        'agent_id'                  => $record->agent_id,
                        'code_agency'               => $record->code_agency,
                        'plan_id'                   => $data['plan_id'],
                        'coverage_id'               => $data['coverage_id'],
                        'total_amount'              => $data['total_amount'],
                        'tasa_bcv'                  => $data['tasa_bcv'],
                        'pay_amount_usd'            => $data['pay_amount_usd'],
                        'pay_amount_ves'            => $data['pay_amount_ves'],
                        'document_usd'              => $data['document_usd'] == null ? 'N/A' : $data['document_usd'],
                        'document_ves'              => $data['document_ves'],
                        'payment_method'            => $data['payment_method'],
                        'payment_method_usd'        => $data['payment_method_usd'],
                        'payment_method_ves'        => $data['payment_method_ves'],
                        'payment_frequency'         => $data['payment_frequency'],
                        'payment_date'              => now()->format('d-m-Y'),
                        'prox_payment_date'         => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'reference_payment_zelle'   => $data['reference_payment_zelle'] == null ? 'N/A' : $data['reference_payment_zelle'],
                        'reference_payment_ves'     => $data['reference_payment_ves'],
                        'observations_payment'      => $data['observations_payment'] == null ? 'N/A' : $data['observations_payment'],
                        'bank_usd'                  => $data['bank_usd'] == null ? 'N/A' : $data['bank_usd'],
                        'bank_ves'                  => $data['bank_ves'] ?? 'N/A',
                        'renewal_date'              => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'created_by'                => Auth::user()->name,
                        'type_roll'                 => $type_roll,
                    ]);
                }
            }

            if ($data['payment_frequency'] == 'TRIMESTRAL') {

                /** PAGO USD */
                if ($data['payment_method'] == 'EFECTIVO US$' || $data['payment_method'] == 'ZELLE') {

                    $record->paid_membership_corporates()->create([
                        'affiliation_corporate_id'            => $record->id,
                        'agent_id'                  => $record->agent_id,
                        'code_agency'               => $record->code_agency,
                        'plan_id'                   => $data['plan_id'],
                        'coverage_id'               => $data['coverage_id'],
                        'total_amount'              => $data['total_amount'],
                        'pay_amount_usd'            => $data['total_amount'],
                        'pay_amount_ves'            => isset($data['pay_amount_ves']) ? $data['pay_amount_ves'] : 0.00,
                        'document_usd'              => isset($data['document_usd']) ? $data['document_usd'] : 'N/A',
                        'document_ves'              => isset($data['document_ves']) ? $data['document_ves'] : 'N/A',
                        'payment_method'            => $data['payment_method'],
                        'reference_payment_zelle'   => isset($data['reference_payment_zelle']) ? $data['reference_payment_zelle'] : 'N/A',
                        'reference_payment_ves'     => isset($data['reference_payment_ves']) ? $data['reference_payment_ves'] : 'N/A',
                        'bank_usd'                  => isset($data['bank_usd']) ? $data['bank_usd'] : 'N/A',
                        'bank_ves'                  => isset($data['bank_ves']) ? $data['bank_ves'] : 'N/A',
                        'payment_frequency'         => $data['payment_frequency'],
                        'payment_date'              => now()->format('d-m-Y'),
                        'prox_payment_date'         => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'observations_payment'      => $data['observations_payment'] == null ? 'N/A' : $data['observations_payment'],
                        'renewal_date'              => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'created_by'                => Auth::user()->name,
                        'type_roll'                 => $type_roll,
                    ]);
                }

                /** PAGO BSD */
                if ($data['payment_method'] == 'PAGO MOVIL VES' || $data['payment_method'] == 'TRANSFERENCIA VES') {

                    $record->paid_membership_corporates()->create([
                        'affiliation_corporate_id'            => $record->id,
                        'agent_id'                  => $record->agent_id,
                        'code_agency'               => $record->code_agency,
                        'plan_id'                   => $data['plan_id'],
                        'coverage_id'               => $data['coverage_id'],
                        'total_amount'              => $data['total_amount'],
                        'tasa_bcv'                  => $data['tasa_bcv'],
                        'pay_amount_usd'            => isset($data['pay_amount_usd']) ? $data['pay_amount_usd'] : 0.00,
                        'pay_amount_ves'            => $data['pay_amount_ves'],
                        'document_ves'              => $data['document_ves'],
                        'document_usd'              => isset($data['document_usd']) ? $data['document_usd'] : 'N/A',
                        'payment_method'            => $data['payment_method'],
                        'payment_frequency'         => $data['payment_frequency'],
                        'payment_date'              => now()->format('d-m-Y'),
                        'prox_payment_date'         => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'reference_payment_ves'     => $data['reference_payment_ves'],
                        'reference_payment_zelle'   => isset($data['reference_payment_zelle']) ? $data['reference_payment_zelle'] : 'N/A',
                        'observations_payment'      => $data['observations_payment'] == null ? 'N/A' : $data['observations_payment'],
                        'bank_usd'                  => isset($data['bank_usd']) ? $data['bank_usd'] : 'N/A',
                        'bank_ves'                  => $data['bank_ves'],
                        'renewal_date'              => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'created_by'                => Auth::user()->name,
                        'type_roll'                 => $type_roll,
                    ]);
                }

                /** PAGO MULTIPLE */
                if ($data['payment_method'] == 'MULTIPLE') {

                    $record->paid_membership_corporates()->create([
                        'affiliation_corporate_id'            => $record->id,
                        'agent_id'                  => $record->agent_id,
                        'code_agency'               => $record->code_agency,
                        'plan_id'                   => $data['plan_id'],
                        'coverage_id'               => $data['coverage_id'],
                        'total_amount'              => $data['total_amount'],
                        'tasa_bcv'                  => $data['tasa_bcv'],
                        'pay_amount_usd'            => $data['pay_amount_usd'],
                        'pay_amount_ves'            => $data['pay_amount_ves'],
                        'document_usd'              => $data['document_usd'] == null ? 'N/A' : $data['document_usd'],
                        'document_ves'              => $data['document_ves'],
                        'payment_method'            => $data['payment_method'],
                        'payment_method_usd'        => $data['payment_method_usd'],
                        'payment_method_ves'        => $data['payment_method_ves'],
                        'payment_frequency'         => $data['payment_frequency'],
                        'payment_date'              => now()->format('d-m-Y'),
                        'prox_payment_date'         => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'reference_payment_zelle'   => $data['reference_payment_zelle'] == null ? 'N/A' : $data['reference_payment_zelle'],
                        'reference_payment_ves'     => $data['reference_payment_ves'],
                        'observations_payment'      => $data['observations_payment'] == null ? 'N/A' : $data['observations_payment'],
                        'bank_usd'                  => $data['bank_usd'] == null ? 'N/A' : $data['bank_usd'],
                        'bank_ves'                  => $data['bank_ves'] ?? 'N/A',
                        'renewal_date'              => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'created_by'                => Auth::user()->name,
                        'type_roll'                 => $type_roll,
                    ]);
                }
            }

            if ($data['payment_frequency'] == 'SEMESTRAL') {

                /** PAGO USD */
                if ($data['payment_method'] == 'EFECTIVO US$' || $data['payment_method'] == 'ZELLE') {

                    $record->paid_membership_corporates()->create([
                        'affiliation_corporate_id'            => $record->id,
                        'agent_id'                  => $record->agent_id,
                        'code_agency'               => $record->code_agency,
                        'plan_id'                   => $data['plan_id'],
                        'coverage_id'               => $data['coverage_id'],
                        'total_amount'              => $data['total_amount'],
                        'pay_amount_usd'            => $data['total_amount'],
                        'pay_amount_ves'            => isset($data['pay_amount_ves']) ? $data['pay_amount_ves'] : 0.00,
                        'document_usd'              => isset($data['document_usd']) ? $data['document_usd'] : 'N/A',
                        'document_ves'              => isset($data['document_ves']) ? $data['document_ves'] : 'N/A',
                        'payment_method'            => $data['payment_method'],
                        'reference_payment_zelle'   => isset($data['reference_payment_zelle']) ? $data['reference_payment_zelle'] : 'N/A',
                        'reference_payment_ves'     => isset($data['reference_payment_ves']) ? $data['reference_payment_ves'] : 'N/A',
                        'bank_usd'                  => isset($data['bank_usd']) ? $data['bank_usd'] : 'N/A',
                        'bank_ves'                  => isset($data['bank_ves']) ? $data['bank_ves'] : 'N/A',
                        'payment_frequency'         => $data['payment_frequency'],
                        'payment_date'              => now()->format('d-m-Y'),
                        'prox_payment_date'         => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'observations_payment'      => $data['observations_payment'] == null ? 'N/A' : $data['observations_payment'],
                        'renewal_date'              => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'created_by'                => Auth::user()->name,
                        'type_roll'                 => $type_roll,
                    ]);
                }

                /** PAGO BSD */
                if ($data['payment_method'] == 'PAGO MOVIL VES' || $data['payment_method'] == 'TRANSFERENCIA VES') {

                    $record->paid_membership_corporates()->create([
                        'affiliation_corporate_id'            => $record->id,
                        'agent_id'                  => $record->agent_id,
                        'code_agency'               => $record->code_agency,
                        'plan_id'                   => $data['plan_id'],
                        'coverage_id'               => $data['coverage_id'],
                        'total_amount'              => $data['total_amount'],
                        'tasa_bcv'                  => $data['tasa_bcv'],
                        'pay_amount_usd'            => isset($data['pay_amount_usd']) ? $data['pay_amount_usd'] : 0.00,
                        'pay_amount_ves'            => $data['pay_amount_ves'],
                        'document_ves'              => $data['document_ves'],
                        'document_usd'              => isset($data['document_usd']) ? $data['document_usd'] : 'N/A',
                        'payment_method'            => $data['payment_method'],
                        'payment_frequency'         => $data['payment_frequency'],
                        'payment_date'              => now()->format('d-m-Y'),
                        'prox_payment_date'         => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'reference_payment_ves'     => $data['reference_payment_ves'],
                        'reference_payment_zelle'   => isset($data['reference_payment_zelle']) ? $data['reference_payment_zelle'] : 'N/A',
                        'observations_payment'      => $data['observations_payment'] == null ? 'N/A' : $data['observations_payment'],
                        'bank_usd'                  => isset($data['bank_usd']) ? $data['bank_usd'] : 'N/A',
                        'bank_ves'                  => $data['bank_ves'],
                        'renewal_date'              => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'created_by'                => Auth::user()->name,
                        'type_roll'                 => $type_roll,
                    ]);
                }

                /** PAGO MULTIPLE */
                if ($data['payment_method'] == 'MULTIPLE') {

                    $record->paid_membership_corporates()->create([
                        'affiliation_corporate_id'            => $record->id,
                        'agent_id'                  => $record->agent_id,
                        'code_agency'               => $record->code_agency,
                        'plan_id'                   => $data['plan_id'],
                        'coverage_id'               => $data['coverage_id'],
                        'total_amount'              => $data['total_amount'],
                        'tasa_bcv'                  => $data['tasa_bcv'],
                        'pay_amount_usd'            => $data['pay_amount_usd'],
                        'pay_amount_ves'            => $data['pay_amount_ves'],
                        'document_usd'              => $data['document_usd'] == null ? 'N/A' : $data['document_usd'],
                        'document_ves'              => $data['document_ves'],
                        'payment_method'            => $data['payment_method'],
                        'payment_method_usd'        => $data['payment_method_usd'],
                        'payment_method_ves'        => $data['payment_method_ves'],
                        'payment_frequency'         => $data['payment_frequency'],
                        'payment_date'              => now()->format('d-m-Y'),
                        'prox_payment_date'         => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'reference_payment_zelle'   => $data['reference_payment_zelle'] == null ? 'N/A' : $data['reference_payment_zelle'],
                        'reference_payment_ves'     => $data['reference_payment_ves'],
                        'observations_payment'      => $data['observations_payment'] == null ? 'N/A' : $data['observations_payment'],
                        'bank_usd'                  => $data['bank_usd'] == null ? 'N/A' : $data['bank_usd'],
                        'bank_ves'                  => $data['bank_ves'] ?? 'N/A',
                        'renewal_date'              => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'created_by'                => Auth::user()->name,
                        'type_roll'                 => $type_roll,
                    ]);
                }
            }

            if ($data['payment_frequency'] == 'MENSUAL') {

                /** PAGO USD */
                if ($data['payment_method'] == 'EFECTIVO US$' || $data['payment_method'] == 'ZELLE') {

                    $record->paid_membership_corporates()->create([
                        'affiliation_corporate_id'            => $record->id,
                        'agent_id'                  => $record->agent_id,
                        'code_agency'               => $record->code_agency,
                        'plan_id'                   => $data['plan_id'],
                        'coverage_id'               => $data['coverage_id'],
                        'total_amount'              => $data['total_amount'],
                        'pay_amount_usd'            => $data['total_amount'],
                        'pay_amount_ves'            => isset($data['pay_amount_ves']) ? $data['pay_amount_ves'] : 0.00,
                        'document_usd'              => isset($data['document_usd']) ? $data['document_usd'] : 'N/A',
                        'document_ves'              => isset($data['document_ves']) ? $data['document_ves'] : 'N/A',
                        'payment_method'            => $data['payment_method'],
                        'reference_payment_zelle'   => isset($data['reference_payment_zelle']) ? $data['reference_payment_zelle'] : 'N/A',
                        'reference_payment_ves'     => isset($data['reference_payment_ves']) ? $data['reference_payment_ves'] : 'N/A',
                        'bank_usd'                  => isset($data['bank_usd']) ? $data['bank_usd'] : 'N/A',
                        'bank_ves'                  => isset($data['bank_ves']) ? $data['bank_ves'] : 'N/A',
                        'payment_frequency'         => $data['payment_frequency'],
                        'payment_date'              => now()->format('d-m-Y'),
                        'prox_payment_date'         => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'observations_payment'      => $data['observations_payment'] == null ? 'N/A' : $data['observations_payment'],
                        'renewal_date'              => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'created_by'                => Auth::user()->name,
                        'type_roll'                 => $type_roll,
                    ]);
                }

                /** PAGO BSD */
                if ($data['payment_method'] == 'PAGO MOVIL VES' || $data['payment_method'] == 'TRANSFERENCIA VES') {

                    $record->paid_membership_corporates()->create([
                        'affiliation_corporate_id'            => $record->id,
                        'agent_id'                  => $record->agent_id,
                        'code_agency'               => $record->code_agency,
                        'plan_id'                   => $data['plan_id'],
                        'coverage_id'               => $data['coverage_id'],
                        'total_amount'              => $data['total_amount'],
                        'tasa_bcv'                  => $data['tasa_bcv'],
                        'pay_amount_usd'            => isset($data['pay_amount_usd']) ? $data['pay_amount_usd'] : 0.00,
                        'pay_amount_ves'            => $data['pay_amount_ves'],
                        'document_ves'              => $data['document_ves'],
                        'document_usd'              => isset($data['document_usd']) ? $data['document_usd'] : 'N/A',
                        'payment_method'            => $data['payment_method'],
                        'payment_frequency'         => $data['payment_frequency'],
                        'payment_date'              => now()->format('d-m-Y'),
                        'prox_payment_date'         => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'reference_payment_ves'     => $data['reference_payment_ves'],
                        'reference_payment_zelle'   => isset($data['reference_payment_zelle']) ? $data['reference_payment_zelle'] : 'N/A',
                        'observations_payment'      => $data['observations_payment'] == null ? 'N/A' : $data['observations_payment'],
                        'bank_usd'                  => isset($data['bank_usd']) ? $data['bank_usd'] : 'N/A',
                        'bank_ves'                  => $data['bank_ves'],
                        'renewal_date'              => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'created_by'                => Auth::user()->name,
                        'type_roll'                 => $type_roll,
                    ]);
                }

                /** PAGO MULTIPLE */
                if ($data['payment_method'] == 'MULTIPLE') {

                    $record->paid_membership_corporates()->create([
                        'affiliation_corporate_id'            => $record->id,
                        'agent_id'                  => $record->agent_id,
                        'code_agency'               => $record->code_agency,
                        'plan_id'                   => $data['plan_id'],
                        'coverage_id'               => $data['coverage_id'],
                        'total_amount'              => $data['total_amount'],
                        'tasa_bcv'                  => $data['tasa_bcv'],
                        'pay_amount_usd'            => $data['pay_amount_usd'],
                        'pay_amount_ves'            => $data['pay_amount_ves'],
                        'document_usd'              => $data['document_usd'] == null ? 'N/A' : $data['document_usd'],
                        'document_ves'              => $data['document_ves'],
                        'payment_method'            => $data['payment_method'],
                        'payment_method_usd'        => $data['payment_method_usd'],
                        'payment_method_ves'        => $data['payment_method_ves'],
                        'payment_frequency'         => $data['payment_frequency'],
                        'payment_date'              => now()->format('d-m-Y'),
                        'prox_payment_date'         => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'reference_payment_zelle'   => $data['reference_payment_zelle'] == null ? 'N/A' : $data['reference_payment_zelle'],
                        'reference_payment_ves'     => $data['reference_payment_ves'],
                        'observations_payment'      => $data['observations_payment'] == null ? 'N/A' : $data['observations_payment'],
                        'bank_usd'                  => $data['bank_usd'] == null ? 'N/A' : $data['bank_usd'],
                        'bank_ves'                  => $data['bank_ves'] ?? 'N/A',
                        'renewal_date'              => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'created_by'                => Auth::user()->name,
                        'type_roll'                 => $type_roll,
                    ]);
                }
            }

            return true;

            //code...
        } catch (\Throwable $th) {
            Log::error($th->getMessage());
            Notification::make()
                ->title('EXCEPTION')
                ->body($th->getMessage())
                ->danger()
                ->send();
            //throw $th;
        }
    }
}