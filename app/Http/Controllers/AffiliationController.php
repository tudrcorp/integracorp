<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Affiliate;
use Filament\Actions\Action;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Models\DetailIndividualQuote;
use Filament\Notifications\Notification;
use App\Filament\Agents\Resources\AffiliationResource;

class AffiliationController extends Controller
{

    public static function uploadPayment($record, $data, $type_roll)
    {

        try {
            // dd($data, $record);
            // $validate = self::getValidation($record, $data);

            //1. Actualizamos la tabla de afiliaciones
            $record->update([
                'family_members'        => Affiliate::select('affiliation_id')->where('affiliation_id', $record->id)->count(),
            ]);

            if ($record['payment_frequency'] == 'ANUAL') {

                /** PAGO USD */
                if ($data['payment_method'] == 'EFECTIVO US$' || $data['payment_method'] == 'ZELLE' || $data['payment_method'] == 'TRANSFERENCIA US$') {

                    $record->paid_memberships()->create([
                        'affiliation_id'            => $record->id,
                        'agent_id'                  => $record->agent_id,
                        'code_agency'               => $record->code_agency,
                        'plan_id'                   => $record->plan_id,
                        'coverage_id'               => $record->coverage_id,
                        'name_ti_usd'               => array_key_exists('name_ti_usd', $data) ? $data['name_ti_usd'] : 'N/A',
                        'total_amount'              => $data['total_amount'],
                        'pay_amount_usd'            => $data['total_amount'],
                        'pay_amount_ves'            => isset($data['pay_amount_ves']) ? $data['pay_amount_ves'] : 0.00,
                        'document_usd'              => $data['document_usd'],
                        'document_ves'              => isset($data['document_ves']) ? $data['document_ves'] : 'N/A',
                        'payment_method'            => $data['payment_method'],
                        'payment_method_usd'        => 'N/A',
                        'payment_method_ves'        => 'N/A',
                        'reference_payment_usd'     => isset($data['reference_payment_usd']) ? $data['reference_payment_usd'] : 'N/A',
                        'reference_payment_ves'     => isset($data['reference_payment_ves']) ? $data['reference_payment_ves'] : 'N/A',
                        'bank_usd'                  => isset($data['bank_usd']) ? $data['bank_usd'] : 'N/A',
                        'bank_ves'                  => isset($data['bank_ves']) ? $data['bank_ves'] : 'N/A',
                        'payment_frequency'         => $record['payment_frequency'],
                        'payment_date'              => now()->format('d-m-Y'),
                        'prox_payment_date'         => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'observations_payment'      => $data['observations_payment'] == null ? 'N/A' : $data['observations_payment'],
                        'renewal_date'              => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'created_by'                => Auth::user()->name,
                        'type_roll'                 => $type_roll,
                        'date_payment_voucher'      => $data['date_payment_voucher']

                    ]);
                }

                /** PAGO BSD */
                if ($data['payment_method'] == 'PAGO MOVIL VES' || $data['payment_method'] == 'TRANSFERENCIA VES') {

                    $record->paid_memberships()->create([
                        'affiliation_id'            => $record->id,
                        'agent_id'                  => $record->agent_id,
                        'code_agency'               => $record->code_agency,
                        'plan_id'                   => $record->plan_id,
                        'coverage_id'               => $record->coverage_id,
                        'total_amount'              => $data['total_amount'],
                        'tasa_bcv'                  => $data['tasa_bcv'],
                        'pay_amount_usd'            => isset($data['pay_amount_usd']) ? $data['pay_amount_usd'] : 0.00,
                        'pay_amount_ves'            => $data['pay_amount_ves'],
                        'document_ves'              => $data['document_ves'],
                        'document_usd'              => isset($data['document_usd']) ? $data['document_usd'] : 'N/A',
                        'payment_method'            => $data['payment_method'],
                        'payment_method_usd'            => 'N/A',
                        'payment_method_ves'            => 'N/A',
                        'payment_frequency'         => $record['payment_frequency'],
                        'payment_date'              => now()->format('d-m-Y'),
                        'prox_payment_date'         => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'reference_payment_ves'     => $data['reference_payment_ves'],
                        'reference_payment_usd'   => isset($data['reference_payment_usd']) ? $data['reference_payment_usd'] : 'N/A',
                        'observations_payment'      => $data['observations_payment'] == null ? 'N/A' : $data['observations_payment'],
                        'bank_usd'                  => isset($data['bank_usd']) ? $data['bank_usd'] : 'N/A',
                        'bank_ves'                  => $data['bank_ves'],
                        'renewal_date'              => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'created_by'                => Auth::user()->name,
                        'type_roll'                 => $type_roll,
                        'date_payment_voucher'      => $data['date_payment_voucher']
                    ]);
                }

                /** PAGO MULTIPLE */
                if ($data['payment_method'] == 'MULTIPLE') {

                    $record->paid_memberships()->create([
                        'affiliation_id'            => $record->id,
                        'agent_id'                  => $record->agent_id,
                        'code_agency'               => $record->code_agency,
                        'plan_id'                   => $record->plan_id,
                        'coverage_id'               => $record->coverage_id,
                        'total_amount'              => $data['total_amount'],
                        'tasa_bcv'                  => $data['tasa_bcv'],
                        'pay_amount_usd'            => $data['pay_amount_usd'],
                        'pay_amount_ves'            => $data['pay_amount_ves'],
                        'document_usd'              => $data['document_usd'],
                        'document_ves'              => $data['document_ves'],
                        'payment_method'            => $data['payment_method'],
                        'payment_method_usd'        => $data['payment_method_usd'],
                        'payment_method_ves'        => $data['payment_method_ves'],
                        'payment_frequency'         => $record['payment_frequency'],
                        'payment_date'              => now()->format('d-m-Y'),
                        'prox_payment_date'         => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'reference_payment_usd'   => array_key_exists('reference_payment_usd', $data) ? $data['reference_payment_usd'] : 'N/A',
                        'reference_payment_ves'     => $data['reference_payment_ves'],
                        'observations_payment'      => $data['observations_payment'] == null ? 'N/A' : $data['observations_payment'],
                        'bank_usd'                  => $data['bank_usd'] == null ? 'N/A' : $data['bank_usd'],
                        'bank_ves'                  => $data['bank_ves'] ?? 'N/A',
                        'renewal_date'              => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'created_by'                => Auth::user()->name,
                        'type_roll'                 => $type_roll,
                        'name_ti_usd'               => array_key_exists('name_ti_usd', $data) ? $data['name_ti_usd'] : 'N/A',
                        'date_payment_voucher'      => $data['date_payment_voucher']
                    ]);
                }
            }

            if ($record['payment_frequency'] == 'TRIMESTRAL') {

                /** PAGO USD */
                if ($data['payment_method'] == 'EFECTIVO US$' || $data['payment_method'] == 'ZELLE' || $data['payment_method'] == 'TRANSFERENCIA US$') {

                    $record->paid_memberships()->create([
                        'affiliation_id'            => $record->id,
                        'agent_id'                  => $record->agent_id,
                        'code_agency'               => $record->code_agency,
                        'plan_id'                   => $record->plan_id,
                        'coverage_id'               => $record->coverage_id,
                        'total_amount'              => $data['total_amount'],
                        'pay_amount_usd'            => $data['total_amount'],
                        'pay_amount_ves'            => isset($data['pay_amount_ves']) ? $data['pay_amount_ves'] : 0.00,
                        'document_usd'              => $data['document_usd'],
                        'document_ves'              => isset($data['document_ves']) ? $data['document_ves'] : 'N/A',
                        'payment_method'            => $data['payment_method'],
                        'reference_payment_usd'   => isset($data['reference_payment_usd']) ? $data['reference_payment_usd'] : 'N/A',
                        'reference_payment_ves'     => isset($data['reference_payment_ves']) ? $data['reference_payment_ves'] : 'N/A',
                        'bank_usd'                  => isset($data['bank_usd']) ? $data['bank_usd'] : 'N/A',
                        'bank_ves'                  => isset($data['bank_ves']) ? $data['bank_ves'] : 'N/A',
                        'payment_frequency'         => $record['payment_frequency'],
                        'payment_date'              => now()->format('d-m-Y'),
                        'prox_payment_date'         => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'observations_payment'      => $data['observations_payment'] == null ? 'N/A' : $data['observations_payment'],
                        'renewal_date'              => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'created_by'                => Auth::user()->name,
                        'type_roll'                 => $type_roll,
                        'name_ti_usd'               => array_key_exists('name_ti_usd', $data) ? $data['name_ti_usd'] : 'N/A',
                        'date_payment_voucher'      => $data['date_payment_voucher']
                    ]);
                }

                /** PAGO BSD */
                if ($data['payment_method'] == 'PAGO MOVIL VES' || $data['payment_method'] == 'TRANSFERENCIA VES') {

                    $record->paid_memberships()->create([
                        'affiliation_id'            => $record->id,
                        'agent_id'                  => $record->agent_id,
                        'code_agency'               => $record->code_agency,
                        'plan_id'                   => $record->plan_id,
                        'coverage_id'               => $record->coverage_id,
                        'total_amount'              => $data['total_amount'],
                        'tasa_bcv'                  => $data['tasa_bcv'],
                        'pay_amount_usd'            => isset($data['pay_amount_usd']) ? $data['pay_amount_usd'] : 0.00,
                        'pay_amount_ves'            => $data['pay_amount_ves'],
                        'document_ves'              => $data['document_ves'],
                        'document_usd'              => isset($data['document_usd']) ? $data['document_usd'] : 'N/A',
                        'payment_method'            => $data['payment_method'],
                        'payment_frequency'         => $record['payment_frequency'],
                        'payment_date'              => now()->format('d-m-Y'),
                        'prox_payment_date'         => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'reference_payment_ves'     => $data['reference_payment_ves'],
                        'reference_payment_usd'   => isset($data['reference_payment_usd']) ? $data['reference_payment_usd'] : 'N/A',
                        'observations_payment'      => $data['observations_payment'] == null ? 'N/A' : $data['observations_payment'],
                        'bank_usd'                  => isset($data['bank_usd']) ? $data['bank_usd'] : 'N/A',
                        'bank_ves'                  => $data['bank_ves'],
                        'renewal_date'              => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'created_by'                => Auth::user()->name,
                        'type_roll'                 => $type_roll,
                        'date_payment_voucher'      => $data['date_payment_voucher']
                    ]);
                }

                /** PAGO MULTIPLE */
                if ($data['payment_method'] == 'MULTIPLE') {

                    $record->paid_memberships()->create([
                        'affiliation_id'            => $record->id,
                        'agent_id'                  => $record->agent_id,
                        'code_agency'               => $record->code_agency,
                        'plan_id'                   => $record->plan_id,
                        'coverage_id'               => $record->coverage_id,
                        'total_amount'              => $data['total_amount'],
                        'tasa_bcv'                  => $data['tasa_bcv'],
                        'pay_amount_usd'            => $data['pay_amount_usd'],
                        'pay_amount_ves'            => $data['pay_amount_ves'],
                        'document_usd'              => $data['document_usd'],
                        'document_ves'              => $data['document_ves'],
                        'payment_method'            => $data['payment_method'],
                        'payment_method_usd'        => $data['payment_method_usd'],
                        'payment_method_ves'        => $data['payment_method_ves'],
                        'payment_frequency'         => $record['payment_frequency'],
                        'payment_date'              => now()->format('d-m-Y'),
                        'prox_payment_date'         => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'reference_payment_usd'   => array_key_exists('reference_payment_usd', $data) ? $data['reference_payment_usd'] : 'N/A',
                        'reference_payment_ves'     => $data['reference_payment_ves'],
                        'observations_payment'      => $data['observations_payment'] == null ? 'N/A' : $data['observations_payment'],
                        'bank_usd'                  => $data['bank_usd'] == null ? 'N/A' : $data['bank_usd'],
                        'bank_ves'                  => $data['bank_ves'] ?? 'N/A',
                        'renewal_date'              => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'created_by'                => Auth::user()->name,
                        'type_roll'                 => $type_roll,
                        'name_ti_usd'               => array_key_exists('name_ti_usd', $data) ? $data['name_ti_usd'] : 'N/A',
                        'date_payment_voucher'      => $data['date_payment_voucher']
                    ]);
                }
            }

            if ($record['payment_frequency'] == 'SEMESTRAL') {

                /** PAGO USD */
                if ($data['payment_method'] == 'EFECTIVO US$' || $data['payment_method'] == 'ZELLE' || $data['payment_method'] == 'TRANSFERENCIA US$') {

                    $record->paid_memberships()->create([
                        'affiliation_id'            => $record->id,
                        'agent_id'                  => $record->agent_id,
                        'code_agency'               => $record->code_agency,
                        'plan_id'                   => $record->plan_id,
                        'coverage_id'               => $record->coverage_id,
                        'name_ti_usd'               => array_key_exists('name_ti_usd', $data) ? $data['name_ti_usd'] : 'N/A',
                        'total_amount'              => $data['total_amount'],
                        'pay_amount_usd'            => $data['total_amount'],
                        'pay_amount_ves'            => isset($data['pay_amount_ves']) ? $data['pay_amount_ves'] : 0.00,
                        'document_usd'              => $data['document_usd'],
                        'document_ves'              => isset($data['document_ves']) ? $data['document_ves'] : 'N/A',
                        'payment_method'            => $data['payment_method'],
                        'reference_payment_usd'   => isset($data['reference_payment_usd']) ? $data['reference_payment_usd'] : 'N/A',
                        'reference_payment_ves'     => isset($data['reference_payment_ves']) ? $data['reference_payment_ves'] : 'N/A',
                        'bank_usd'                  => isset($data['bank_usd']) ? $data['bank_usd'] : 'N/A',
                        'bank_ves'                  => isset($data['bank_ves']) ? $data['bank_ves'] : 'N/A',
                        'payment_frequency'         => $record['payment_frequency'],
                        'payment_date'              => now()->format('d-m-Y'),
                        'prox_payment_date'         => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'observations_payment'      => $data['observations_payment'] == null ? 'N/A' : $data['observations_payment'],
                        'renewal_date'              => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'created_by'                => Auth::user()->name,
                        'type_roll'                 => $type_roll,
                        'date_payment_voucher'      => $data['date_payment_voucher']
                    ]);
                }

                /** PAGO BSD */
                if ($data['payment_method'] == 'PAGO MOVIL VES' || $data['payment_method'] == 'TRANSFERENCIA VES') {

                    $record->paid_memberships()->create([
                        'affiliation_id'            => $record->id,
                        'agent_id'                  => $record->agent_id,
                        'code_agency'               => $record->code_agency,
                        'plan_id'                   => $record->plan_id,
                        'coverage_id'               => $record->coverage_id,
                        'name_ti_usd'               => array_key_exists('name_ti_usd', $data) ? $data['name_ti_usd'] : 'N/A',
                        'total_amount'              => $data['total_amount'],
                        'tasa_bcv'                  => $data['tasa_bcv'],
                        'pay_amount_usd'            => isset($data['pay_amount_usd']) ? $data['pay_amount_usd'] : 0.00,
                        'pay_amount_ves'            => $data['pay_amount_ves'],
                        'document_ves'              => $data['document_ves'],
                        'document_usd'              => isset($data['document_usd']) ? $data['document_usd'] : 'N/A',
                        'payment_method'            => $data['payment_method'],
                        'payment_frequency'         => $record['payment_frequency'],
                        'payment_date'              => now()->format('d-m-Y'),
                        'prox_payment_date'         => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'reference_payment_ves'     => $data['reference_payment_ves'],
                        'reference_payment_usd'   => isset($data['reference_payment_usd']) ? $data['reference_payment_usd'] : 'N/A',
                        'observations_payment'      => $data['observations_payment'] == null ? 'N/A' : $data['observations_payment'],
                        'bank_usd'                  => isset($data['bank_usd']) ? $data['bank_usd'] : 'N/A',
                        'bank_ves'                  => $data['bank_ves'],
                        'renewal_date'              => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'created_by'                => Auth::user()->name,
                        'type_roll'                 => $type_roll,
                        'date_payment_voucher'      => $data['date_payment_voucher']
                    ]);
                }

                /** PAGO MULTIPLE */
                if ($data['payment_method'] == 'MULTIPLE') {

                    $record->paid_memberships()->create([
                        'affiliation_id'            => $record->id,
                        'agent_id'                  => $record->agent_id,
                        'code_agency'               => $record->code_agency,
                        'plan_id'                   => $record->plan_id,
                        'coverage_id'               => $record->coverage_id,
                        'name_ti_usd'               => array_key_exists('name_ti_usd', $data) ? $data['name_ti_usd'] : 'N/A',
                        'total_amount'              => $data['total_amount'],
                        'tasa_bcv'                  => $data['tasa_bcv'],
                        'pay_amount_usd'            => $data['pay_amount_usd'],
                        'pay_amount_ves'            => $data['pay_amount_ves'],
                        'document_usd'              => $data['document_usd'],
                        'document_ves'              => $data['document_ves'],
                        'payment_method'            => $data['payment_method'],
                        'payment_method_usd'        => $data['payment_method_usd'],
                        'payment_method_ves'        => $data['payment_method_ves'],
                        'payment_frequency'         => $record['payment_frequency'],
                        'payment_date'              => now()->format('d-m-Y'),
                        'prox_payment_date'         => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'reference_payment_usd'   => array_key_exists('reference_payment_usd', $data) ? $data['reference_payment_usd'] : 'N/A',
                        'reference_payment_ves'     => $data['reference_payment_ves'],
                        'observations_payment'      => $data['observations_payment'] == null ? 'N/A' : $data['observations_payment'],
                        'bank_usd'                  => $data['bank_usd'] == null ? 'N/A' : $data['bank_usd'],
                        'bank_ves'                  => $data['bank_ves'] ?? 'N/A',
                        'renewal_date'              => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'created_by'                => Auth::user()->name,
                        'type_roll'                 => $type_roll,
                        'date_payment_voucher'      => $data['date_payment_voucher']
                    ]);
                }
            }

            if ($record['payment_frequency'] == 'MENSUAL') {

                /** PAGO USD */
                if ($data['payment_method'] == 'EFECTIVO US$' || $data['payment_method'] == 'ZELLE' || $data['payment_method'] == 'TRANSFERENCIA US$') {

                    $record->paid_memberships()->create([
                        'affiliation_id'            => $record->id,
                        'agent_id'                  => $record->agent_id,
                        'code_agency'               => $record->code_agency,
                        'plan_id'                   => $record->plan_id,
                        'coverage_id'               => $record->coverage_id,
                        'name_ti_usd'               => array_key_exists('name_ti_usd', $data) ? $data['name_ti_usd'] : 'N/A',
                        'total_amount'              => $data['total_amount'],
                        'pay_amount_usd'            => $data['total_amount'],
                        'pay_amount_ves'            => isset($data['pay_amount_ves']) ? $data['pay_amount_ves'] : 0.00,
                        'document_usd'              => isset($data['document_usd']) ? $data['document_usd'] : 'N/A',
                        'document_ves'              => isset($data['document_ves']) ? $data['document_ves'] : 'N/A',
                        'payment_method'            => $data['payment_method'],
                        'reference_payment_usd'   => isset($data['reference_payment_usd']) ? $data['reference_payment_usd'] : 'N/A',
                        'reference_payment_ves'     => isset($data['reference_payment_ves']) ? $data['reference_payment_ves'] : 'N/A',
                        'bank_usd'                  => isset($data['bank_usd']) ? $data['bank_usd'] : 'N/A',
                        'bank_ves'                  => isset($data['bank_ves']) ? $data['bank_ves'] : 'N/A',
                        'payment_frequency'         => $record['payment_frequency'],
                        'payment_date'              => now()->format('d-m-Y'),
                        'prox_payment_date'         => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'observations_payment'      => $data['observations_payment'] == null ? 'N/A' : $data['observations_payment'],
                        'renewal_date'              => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'created_by'                => Auth::user()->name,
                        'type_roll'                 => $type_roll,
                        'date_payment_voucher'      => $data['date_payment_voucher']
                    ]);
                }

                /** PAGO BSD */
                if ($data['payment_method'] == 'PAGO MOVIL VES' || $data['payment_method'] == 'TRANSFERENCIA VES') {

                    $record->paid_memberships()->create([
                        'affiliation_id'            => $record->id,
                        'agent_id'                  => $record->agent_id,
                        'code_agency'               => $record->code_agency,
                        'plan_id'                   => $record->plan_id,
                        'coverage_id'               => $record->coverage_id,
                        'name_ti_usd'               => array_key_exists('name_ti_usd', $data) ? $data['name_ti_usd'] : 'N/A',
                        'total_amount'              => $data['total_amount'],
                        'tasa_bcv'                  => $data['tasa_bcv'],
                        'pay_amount_usd'            => isset($data['pay_amount_usd']) ? $data['pay_amount_usd'] : 0.00,
                        'pay_amount_ves'            => $data['pay_amount_ves'],
                        'document_ves'              => $data['document_ves'],
                        'document_usd'              => isset($data['document_usd']) ? $data['document_usd'] : 'N/A',
                        'payment_method'            => $data['payment_method'],
                        'payment_frequency'         => $record['payment_frequency'],
                        'payment_date'              => now()->format('d-m-Y'),
                        'prox_payment_date'         => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'reference_payment_ves'     => $data['reference_payment_ves'],
                        'reference_payment_usd'   => isset($data['reference_payment_usd']) ? $data['reference_payment_usd'] : 'N/A',
                        'observations_payment'      => $data['observations_payment'] == null ? 'N/A' : $data['observations_payment'],
                        'bank_usd'                  => isset($data['bank_usd']) ? $data['bank_usd'] : 'N/A',
                        'bank_ves'                  => $data['bank_ves'],
                        'renewal_date'              => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'created_by'                => Auth::user()->name,
                        'type_roll'                 => $type_roll,
                        'date_payment_voucher'      => $data['date_payment_voucher']
                    ]);
                }

                /** PAGO MULTIPLE */
                if ($data['payment_method'] == 'MULTIPLE') {

                    $record->paid_memberships()->create([
                        'affiliation_id'            => $record->id,
                        'agent_id'                  => $record->agent_id,
                        'code_agency'               => $record->code_agency,
                        'plan_id'                   => $record->plan_id,
                        'coverage_id'               => $record->coverage_id,
                        'name_ti_usd'               => array_key_exists('name_ti_usd', $data) ? $data['name_ti_usd'] : 'N/A',
                        'total_amount'              => $data['total_amount'],
                        'tasa_bcv'                  => $data['tasa_bcv'],
                        'pay_amount_usd'            => $data['pay_amount_usd'],
                        'pay_amount_ves'            => $data['pay_amount_ves'],
                        'document_usd'              => $data['document_usd'] == null ? 'N/A' : $data['document_usd'],
                        'document_ves'              => $data['document_ves'],
                        'payment_method'            => $data['payment_method'],
                        'payment_method_usd'        => $data['payment_method_usd'],
                        'payment_method_ves'        => $data['payment_method_ves'],
                        'payment_frequency'         => $record['payment_frequency'],
                        'payment_date'              => now()->format('d-m-Y'),
                        'prox_payment_date'         => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'reference_payment_usd'   => array_key_exists('reference_payment_usd', $data) ? $data['reference_payment_usd'] : 'N/A',
                        'reference_payment_ves'     => $data['reference_payment_ves'],
                        'observations_payment'      => $data['observations_payment'] == null ? 'N/A' : $data['observations_payment'],
                        'bank_usd'                  => $data['bank_usd'] == null ? 'N/A' : $data['bank_usd'],
                        'bank_ves'                  => $data['bank_ves'] ?? 'N/A',
                        'renewal_date'              => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'created_by'                => Auth::user()->name,
                        'type_roll'                 => $type_roll,
                        'date_payment_voucher'      => $data['date_payment_voucher']
                    ]);
                }
            }

            return true;

            //code...
        } catch (\Throwable $th) {
            dd($th);
            Log::error($th->getMessage());
            Notification::make()
                ->title('EXCEPTION')
                ->body($th->getMessage())
                ->danger()
                ->send();
            //throw $th;
        }
    }

    public static function uploadPaymentMultipleAffiliations($records, $data, $type_roll)
    {

        try {

            foreach ($records as $record) {
                $record->update([
                    'family_members' => Affiliate::select('affiliation_id')->where('affiliation_id', $record->id)->count(),
                ]);

                if ($record['payment_frequency'] == 'ANUAL') {

                    /** PAGO USD */
                    if ($data['payment_method'] == 'EFECTIVO US$' || $data['payment_method'] == 'ZELLE' || $data['payment_method'] == 'TRANSFERENCIA US$') {

                        $record->paid_memberships()->create([
                            'affiliation_id'            => $record->id,
                            'agent_id'                  => $record->agent_id,
                            'code_agency'               => $record->code_agency,
                            'plan_id'                   => $record->plan_id,
                            'coverage_id'               => $record->coverage_id,
                            'name_ti_usd'               => array_key_exists('name_ti_usd', $data) ? $data['name_ti_usd'] : 'N/A',
                            'total_amount'              => $data['total_amount'],
                            'pay_amount_usd'            => $data['total_amount'],
                            'pay_amount_ves'            => isset($data['pay_amount_ves']) ? $data['pay_amount_ves'] : 0.00,
                            'document_usd'              => $data['document_usd'],
                            'document_ves'              => isset($data['document_ves']) ? $data['document_ves'] : 'N/A',
                            'payment_method'            => $data['payment_method'],
                            'payment_method_usd'        => 'N/A',
                            'payment_method_ves'        => 'N/A',
                            'reference_payment_usd'   => isset($data['reference_payment_usd']) ? $data['reference_payment_usd'] : 'N/A',
                            'reference_payment_ves'     => isset($data['reference_payment_ves']) ? $data['reference_payment_ves'] : 'N/A',
                            'bank_usd'                  => isset($data['bank_usd']) ? $data['bank_usd'] : 'N/A',
                            'bank_ves'                  => isset($data['bank_ves']) ? $data['bank_ves'] : 'N/A',
                            'payment_frequency'         => $record['payment_frequency'],
                            'payment_date'              => now()->format('d-m-Y'),
                            'prox_payment_date'         => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                            'observations_payment'      => $data['observations_payment'] == null ? 'N/A' : $data['observations_payment'],
                            'renewal_date'              => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                            'created_by'                => Auth::user()->name,
                            'type_roll'                 => $type_roll,
                            'date_payment_voucher'      => $data['date_payment_voucher']

                        ]);
                    }

                    /** PAGO BSD */
                    if ($data['payment_method'] == 'PAGO MOVIL VES' || $data['payment_method'] == 'TRANSFERENCIA VES') {

                        $record->paid_memberships()->create([
                            'affiliation_id'            => $record->id,
                            'agent_id'                  => $record->agent_id,
                            'code_agency'               => $record->code_agency,
                            'plan_id'                   => $record->plan_id,
                            'coverage_id'               => $record->coverage_id,
                            'total_amount'              => $data['total_amount'],
                            'tasa_bcv'                  => $data['tasa_bcv'],
                            'pay_amount_usd'            => isset($data['pay_amount_usd']) ? $data['pay_amount_usd'] : 0.00,
                            'pay_amount_ves'            => $data['pay_amount_ves'],
                            'document_ves'              => $data['document_ves'],
                            'document_usd'              => isset($data['document_usd']) ? $data['document_usd'] : 'N/A',
                            'payment_method'            => $data['payment_method'],
                            'payment_method_usd'            => 'N/A',
                            'payment_method_ves'            => 'N/A',
                            'payment_frequency'         => $record['payment_frequency'],
                            'payment_date'              => now()->format('d-m-Y'),
                            'prox_payment_date'         => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                            'reference_payment_ves'     => $data['reference_payment_ves'],
                            'reference_payment_usd'   => isset($data['reference_payment_usd']) ? $data['reference_payment_usd'] : 'N/A',
                            'observations_payment'      => $data['observations_payment'] == null ? 'N/A' : $data['observations_payment'],
                            'bank_usd'                  => isset($data['bank_usd']) ? $data['bank_usd'] : 'N/A',
                            'bank_ves'                  => $data['bank_ves'],
                            'renewal_date'              => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                            'created_by'                => Auth::user()->name,
                            'type_roll'                 => $type_roll,
                            'date_payment_voucher'      => $data['date_payment_voucher']
                        ]);
                    }

                    /** PAGO MULTIPLE */
                    if ($data['payment_method'] == 'MULTIPLE') {

                        $record->paid_memberships()->create([
                            'affiliation_id'            => $record->id,
                            'agent_id'                  => $record->agent_id,
                            'code_agency'               => $record->code_agency,
                            'plan_id'                   => $record->plan_id,
                            'coverage_id'               => $record->coverage_id,
                            'total_amount'              => $data['total_amount'],
                            'tasa_bcv'                  => $data['tasa_bcv'],
                            'pay_amount_usd'            => $data['pay_amount_usd'],
                            'pay_amount_ves'            => $data['pay_amount_ves'],
                            'document_usd'              => $data['document_usd'],
                            'document_ves'              => $data['document_ves'],
                            'payment_method'            => $data['payment_method'],
                            'payment_method_usd'        => $data['payment_method_usd'],
                            'payment_method_ves'        => $data['payment_method_ves'],
                            'payment_frequency'         => $record['payment_frequency'],
                            'payment_date'              => now()->format('d-m-Y'),
                            'prox_payment_date'         => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                            'reference_payment_usd'   => array_key_exists('reference_payment_usd', $data) ? $data['reference_payment_usd'] : 'N/A',
                            'reference_payment_ves'     => $data['reference_payment_ves'],
                            'observations_payment'      => $data['observations_payment'] == null ? 'N/A' : $data['observations_payment'],
                            'bank_usd'                  => $data['bank_usd'] == null ? 'N/A' : $data['bank_usd'],
                            'bank_ves'                  => $data['bank_ves'] ?? 'N/A',
                            'renewal_date'              => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                            'created_by'                => Auth::user()->name,
                            'type_roll'                 => $type_roll,
                            'name_ti_usd'               => array_key_exists('name_ti_usd', $data) ? $data['name_ti_usd'] : 'N/A',
                            'date_payment_voucher'      => $data['date_payment_voucher']
                        ]);
                    }
                }

                if ($record['payment_frequency'] == 'TRIMESTRAL') {

                    /** PAGO USD */
                    if ($data['payment_method'] == 'EFECTIVO US$' || $data['payment_method'] == 'ZELLE' || $data['payment_method'] == 'TRANSFERENCIA US$') {

                        $record->paid_memberships()->create([
                            'affiliation_id'            => $record->id,
                            'agent_id'                  => $record->agent_id,
                            'code_agency'               => $record->code_agency,
                            'plan_id'                   => $record->plan_id,
                            'coverage_id'               => $record->coverage_id,
                            'total_amount'              => $record->total_amount,
                            'pay_amount_usd'            => $data['total_amount'],
                            'pay_amount_ves'            => isset($data['pay_amount_ves']) ? $data['pay_amount_ves'] : 0.00,
                            'document_usd'              => $data['document_usd'],
                            'document_ves'              => isset($data['document_ves']) ? $data['document_ves'] : 'N/A',
                            'payment_method'            => $data['payment_method'],
                            'reference_payment_usd'   => isset($data['reference_payment_usd']) ? $data['reference_payment_usd'] : 'N/A',
                            'reference_payment_ves'     => isset($data['reference_payment_ves']) ? $data['reference_payment_ves'] : 'N/A',
                            'bank_usd'                  => isset($data['bank_usd']) ? $data['bank_usd'] : 'N/A',
                            'bank_ves'                  => isset($data['bank_ves']) ? $data['bank_ves'] : 'N/A',
                            'payment_frequency'         => $record['payment_frequency'],
                            'payment_date'              => now()->format('d-m-Y'),
                            'prox_payment_date'         => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                            'observations_payment'      => $data['observations_payment'] == null ? 'N/A' : $data['observations_payment'],
                            'renewal_date'              => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                            'created_by'                => Auth::user()->name,
                            'type_roll'                 => $type_roll,
                            'name_ti_usd'               => array_key_exists('name_ti_usd', $data) ? $data['name_ti_usd'] : 'N/A',
                            'date_payment_voucher'      => $data['date_payment_voucher']
                        ]);
                    }

                    /** PAGO BSD */
                    if ($data['payment_method'] == 'PAGO MOVIL VES' || $data['payment_method'] == 'TRANSFERENCIA VES') {

                        $record->paid_memberships()->create([
                            'affiliation_id'            => $record->id,
                            'agent_id'                  => $record->agent_id,
                            'code_agency'               => $record->code_agency,
                            'plan_id'                   => $record->plan_id,
                            'coverage_id'               => $record->coverage_id,
                            'total_amount'              => $record->total_amount,
                            'tasa_bcv'                  => $data['tasa_bcv'],
                            'pay_amount_usd'            => isset($data['pay_amount_usd']) ? $data['pay_amount_usd'] : 0.00,
                            'pay_amount_ves'            => $data['pay_amount_ves'],
                            'document_ves'              => $data['document_ves'],
                            'document_usd'              => isset($data['document_usd']) ? $data['document_usd'] : 'N/A',
                            'payment_method'            => $data['payment_method'],
                            'payment_frequency'         => $record['payment_frequency'],
                            'payment_date'              => now()->format('d-m-Y'),
                            'prox_payment_date'         => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                            'reference_payment_ves'     => $data['reference_payment_ves'],
                            'reference_payment_usd'   => isset($data['reference_payment_usd']) ? $data['reference_payment_usd'] : 'N/A',
                            'observations_payment'      => $data['observations_payment'] == null ? 'N/A' : $data['observations_payment'],
                            'bank_usd'                  => isset($data['bank_usd']) ? $data['bank_usd'] : 'N/A',
                            'bank_ves'                  => $data['bank_ves'],
                            'renewal_date'              => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                            'created_by'                => Auth::user()->name,
                            'type_roll'                 => $type_roll,
                            'date_payment_voucher'      => $data['date_payment_voucher']
                        ]);
                    }

                    /** PAGO MULTIPLE */
                    if ($data['payment_method'] == 'MULTIPLE') {

                        $record->paid_memberships()->create([
                            'affiliation_id'            => $record->id,
                            'agent_id'                  => $record->agent_id,
                            'code_agency'               => $record->code_agency,
                            'plan_id'                   => $record->plan_id,
                            'coverage_id'               => $record->coverage_id,
                            'total_amount'              => $record->total_amount,
                            'tasa_bcv'                  => $data['tasa_bcv'],
                            'pay_amount_usd'            => $data['pay_amount_usd'],
                            'pay_amount_ves'            => $data['pay_amount_ves'],
                            'document_usd'              => $data['document_usd'],
                            'document_ves'              => $data['document_ves'],
                            'payment_method'            => $data['payment_method'],
                            'payment_method_usd'        => $data['payment_method_usd'],
                            'payment_method_ves'        => $data['payment_method_ves'],
                            'payment_frequency'         => $record['payment_frequency'],
                            'payment_date'              => now()->format('d-m-Y'),
                            'prox_payment_date'         => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                            'reference_payment_usd'   => array_key_exists('reference_payment_usd', $data) ? $data['reference_payment_usd'] : 'N/A',
                            'reference_payment_ves'     => $data['reference_payment_ves'],
                            'observations_payment'      => $data['observations_payment'] == null ? 'N/A' : $data['observations_payment'],
                            'bank_usd'                  => $data['bank_usd'] == null ? 'N/A' : $data['bank_usd'],
                            'bank_ves'                  => $data['bank_ves'] ?? 'N/A',
                            'renewal_date'              => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                            'created_by'                => Auth::user()->name,
                            'type_roll'                 => $type_roll,
                            'name_ti_usd'               => array_key_exists('name_ti_usd', $data) ? $data['name_ti_usd'] : 'N/A',
                            'date_payment_voucher'      => $data['date_payment_voucher']
                        ]);
                    }
                }

                if ($record['payment_frequency'] == 'SEMESTRAL') {

                    /** PAGO USD */
                    if ($data['payment_method'] == 'EFECTIVO US$' || $data['payment_method'] == 'ZELLE' || $data['payment_method'] == 'TRANSFERENCIA US$') {

                        $record->paid_memberships()->create([
                            'affiliation_id'            => $record->id,
                            'agent_id'                  => $record->agent_id,
                            'code_agency'               => $record->code_agency,
                            'plan_id'                   => $record->plan_id,
                            'coverage_id'               => $record->coverage_id,
                            'name_ti_usd'               => array_key_exists('name_ti_usd', $data) ? $data['name_ti_usd'] : 'N/A',
                            'total_amount'              => $record->total_amount,
                            'pay_amount_usd'            => $data['total_amount'],
                            'pay_amount_ves'            => isset($data['pay_amount_ves']) ? $data['pay_amount_ves'] : 0.00,
                            'document_usd'              => $data['document_usd'],
                            'document_ves'              => isset($data['document_ves']) ? $data['document_ves'] : 'N/A',
                            'payment_method'            => $data['payment_method'],
                            'reference_payment_usd'   => isset($data['reference_payment_usd']) ? $data['reference_payment_usd'] : 'N/A',
                            'reference_payment_ves'     => isset($data['reference_payment_ves']) ? $data['reference_payment_ves'] : 'N/A',
                            'bank_usd'                  => isset($data['bank_usd']) ? $data['bank_usd'] : 'N/A',
                            'bank_ves'                  => isset($data['bank_ves']) ? $data['bank_ves'] : 'N/A',
                            'payment_frequency'         => $record['payment_frequency'],
                            'payment_date'              => now()->format('d-m-Y'),
                            'prox_payment_date'         => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                            'observations_payment'      => $data['observations_payment'] == null ? 'N/A' : $data['observations_payment'],
                            'renewal_date'              => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                            'created_by'                => Auth::user()->name,
                            'type_roll'                 => $type_roll,
                            'date_payment_voucher'      => $data['date_payment_voucher']
                        ]);
                    }

                    /** PAGO BSD */
                    if ($data['payment_method'] == 'PAGO MOVIL VES' || $data['payment_method'] == 'TRANSFERENCIA VES') {

                        $record->paid_memberships()->create([
                            'affiliation_id'            => $record->id,
                            'agent_id'                  => $record->agent_id,
                            'code_agency'               => $record->code_agency,
                            'plan_id'                   => $record->plan_id,
                            'coverage_id'               => $record->coverage_id,
                            'name_ti_usd'               => isset($data['name_ti_usd']) ? $data['name_ti_usd'] : 'N/A',
                            'total_amount'              => $record->total_amount,
                            'tasa_bcv'                  => $data['tasa_bcv'],
                            'pay_amount_usd'            => isset($data['pay_amount_usd']) ? $data['pay_amount_usd'] : 0.00,
                            'pay_amount_ves'            => $data['pay_amount_ves'],
                            'document_ves'              => $data['document_ves'],
                            'document_usd'              => isset($data['document_usd']) ? $data['document_usd'] : 'N/A',
                            'payment_method'            => $data['payment_method'],
                            'payment_frequency'         => $record['payment_frequency'],
                            'payment_date'              => now()->format('d-m-Y'),
                            'prox_payment_date'         => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                            'reference_payment_ves'     => $data['reference_payment_ves'],
                            'reference_payment_usd'   => isset($data['reference_payment_usd']) ? $data['reference_payment_usd'] : 'N/A',
                            'observations_payment'      => $data['observations_payment'] == null ? 'N/A' : $data['observations_payment'],
                            'bank_usd'                  => isset($data['bank_usd']) ? $data['bank_usd'] : 'N/A',
                            'bank_ves'                  => $data['bank_ves'],
                            'renewal_date'              => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                            'created_by'                => Auth::user()->name,
                            'type_roll'                 => $type_roll,
                            'date_payment_voucher'      => $data['date_payment_voucher']
                        ]);
                    }

                    /** PAGO MULTIPLE */
                    if ($data['payment_method'] == 'MULTIPLE') {

                        $record->paid_memberships()->create([
                            'affiliation_id'            => $record->id,
                            'agent_id'                  => $record->agent_id,
                            'code_agency'               => $record->code_agency,
                            'plan_id'                   => $record->plan_id,
                            'coverage_id'               => $record->coverage_id,
                            'name_ti_usd'               => array_key_exists('name_ti_usd', $data) ? $data['name_ti_usd'] : 'N/A',
                            'total_amount'              => $record->total_amount,
                            'tasa_bcv'                  => $data['tasa_bcv'],
                            'pay_amount_usd'            => $data['pay_amount_usd'],
                            'pay_amount_ves'            => $data['pay_amount_ves'],
                            'document_usd'              => $data['document_usd'],
                            'document_ves'              => $data['document_ves'],
                            'payment_method'            => $data['payment_method'],
                            'payment_method_usd'        => $data['payment_method_usd'],
                            'payment_method_ves'        => $data['payment_method_ves'],
                            'payment_frequency'         => $record['payment_frequency'],
                            'payment_date'              => now()->format('d-m-Y'),
                            'prox_payment_date'         => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                            'reference_payment_usd'   => $data['reference_payment_usd'] == null ? 'N/A' : $data['reference_payment_usd'],
                            'reference_payment_ves'     => $data['reference_payment_ves'],
                            'observations_payment'      => $data['observations_payment'] == null ? 'N/A' : $data['observations_payment'],
                            'bank_usd'                  => $data['bank_usd'] == null ? 'N/A' : $data['bank_usd'],
                            'bank_ves'                  => $data['bank_ves'] ?? 'N/A',
                            'renewal_date'              => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                            'created_by'                => Auth::user()->name,
                            'type_roll'                 => $type_roll,
                            'date_payment_voucher'      => $data['date_payment_voucher']
                        ]);
                    }
                }

                if ($record['payment_frequency'] == 'MENSUAL') {

                    /** PAGO USD */
                    if ($data['payment_method'] == 'EFECTIVO US$' || $data['payment_method'] == 'ZELLE' || $data['payment_method'] == 'TRANSFERENCIA US$') {

                        $record->paid_memberships()->create([
                            'affiliation_id'            => $record->id,
                            'agent_id'                  => $record->agent_id,
                            'code_agency'               => $record->code_agency,
                            'plan_id'                   => $record->plan_id,
                            'coverage_id'               => $record->coverage_id,
                            'name_ti_usd'               => array_key_exists('name_ti_usd', $data) ? $data['name_ti_usd'] : 'N/A',
                            'total_amount'              => $record->total_amount,
                            'pay_amount_usd'            => $data['total_amount'],
                            'pay_amount_ves'            => isset($data['pay_amount_ves']) ? $data['pay_amount_ves'] : 0.00,
                            'document_usd'              => isset($data['document_usd']) ? $data['document_usd'] : 'N/A',
                            'document_ves'              => isset($data['document_ves']) ? $data['document_ves'] : 'N/A',
                            'payment_method'            => $data['payment_method'],
                            'reference_payment_usd'   => isset($data['reference_payment_usd']) ? $data['reference_payment_usd'] : 'N/A',
                            'reference_payment_ves'     => isset($data['reference_payment_ves']) ? $data['reference_payment_ves'] : 'N/A',
                            'bank_usd'                  => isset($data['bank_usd']) ? $data['bank_usd'] : 'N/A',
                            'bank_ves'                  => isset($data['bank_ves']) ? $data['bank_ves'] : 'N/A',
                            'payment_frequency'         => $record['payment_frequency'],
                            'payment_date'              => now()->format('d-m-Y'),
                            'prox_payment_date'         => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                            'observations_payment'      => $data['observations_payment'] == null ? 'N/A' : $data['observations_payment'],
                            'renewal_date'              => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                            'created_by'                => Auth::user()->name,
                            'type_roll'                 => $type_roll,
                            'date_payment_voucher'      => $data['date_payment_voucher']
                        ]);
                    }

                    /** PAGO BSD */
                    if ($data['payment_method'] == 'PAGO MOVIL VES' || $data['payment_method'] == 'TRANSFERENCIA VES') {

                        $record->paid_memberships()->create([
                            'affiliation_id'            => $record->id,
                            'agent_id'                  => $record->agent_id,
                            'code_agency'               => $record->code_agency,
                            'plan_id'                   => $record->plan_id,
                            'coverage_id'               => $record->coverage_id,
                            'name_ti_usd'               => array_key_exists('name_ti_usd', $data) ? $data['name_ti_usd'] : 'N/A',
                            'total_amount'              => $record->total_amount,
                            'tasa_bcv'                  => $data['tasa_bcv'],
                            'pay_amount_usd'            => isset($data['pay_amount_usd']) ? $data['pay_amount_usd'] : 0.00,
                            'pay_amount_ves'            => $data['pay_amount_ves'],
                            'document_ves'              => $data['document_ves'],
                            'document_usd'              => isset($data['document_usd']) ? $data['document_usd'] : 'N/A',
                            'payment_method'            => $data['payment_method'],
                            'payment_frequency'         => $record['payment_frequency'],
                            'payment_date'              => now()->format('d-m-Y'),
                            'prox_payment_date'         => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                            'reference_payment_ves'     => $data['reference_payment_ves'],
                            'reference_payment_usd'   => isset($data['reference_payment_usd']) ? $data['reference_payment_usd'] : 'N/A',
                            'observations_payment'      => $data['observations_payment'] == null ? 'N/A' : $data['observations_payment'],
                            'bank_usd'                  => isset($data['bank_usd']) ? $data['bank_usd'] : 'N/A',
                            'bank_ves'                  => $data['bank_ves'],
                            'renewal_date'              => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                            'created_by'                => Auth::user()->name,
                            'type_roll'                 => $type_roll,
                            'date_payment_voucher'      => $data['date_payment_voucher']
                        ]);
                    }

                    /** PAGO MULTIPLE */
                    if ($data['payment_method'] == 'MULTIPLE') {

                        $record->paid_memberships()->create([
                            'affiliation_id'            => $record->id,
                            'agent_id'                  => $record->agent_id,
                            'code_agency'               => $record->code_agency,
                            'plan_id'                   => $record->plan_id,
                            'coverage_id'               => $record->coverage_id,
                            'name_ti_usd'               => array_key_exists('name_ti_usd', $data) ? $data['name_ti_usd'] : 'N/A',
                            'total_amount'              => $record->total_amount,
                            'tasa_bcv'                  => $data['tasa_bcv'],
                            'pay_amount_usd'            => $data['pay_amount_usd'],
                            'pay_amount_ves'            => $data['pay_amount_ves'],
                            'document_usd'              => $data['document_usd'] == null ? 'N/A' : $data['document_usd'],
                            'document_ves'              => $data['document_ves'],
                            'payment_method'            => $data['payment_method'],
                            'payment_method_usd'        => $data['payment_method_usd'],
                            'payment_method_ves'        => $data['payment_method_ves'],
                            'payment_frequency'         => $record['payment_frequency'],
                            'payment_date'              => now()->format('d-m-Y'),
                            'prox_payment_date'         => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                            'reference_payment_usd'   => array_key_exists('reference_payment_usd', $data) ? $data['reference_payment_usd'] : 'N/A',
                            'reference_payment_ves'     => $data['reference_payment_ves'],
                            'observations_payment'      => $data['observations_payment'] == null ? 'N/A' : $data['observations_payment'],
                            'bank_usd'                  => $data['bank_usd'] == null ? 'N/A' : $data['bank_usd'],
                            'bank_ves'                  => $data['bank_ves'] ?? 'N/A',
                            'renewal_date'              => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                            'created_by'                => Auth::user()->name,
                            'type_roll'                 => $type_roll,
                            'date_payment_voucher'      => $data['date_payment_voucher']
                        ]);
                    }
                }
            }


            return true;

            //code...
        } catch (\Throwable $th) {
            dd($th);
            Log::error($th->getMessage());
            Notification::make()
                ->title('EXCEPTION')
                ->body($th->getMessage())
                ->danger()
                ->send();
            //throw $th;
        }
    }

    public static function generateCertificateIndividual($record, $afiliates, $user)
    {
        try {

            // ✅ Reconstruye el usuario dentro del job
            $user = User::findOrFail($user);

            $pagador = [
                'name'                  => $record->full_name_payer,
                'code'                  => $record->code,
                'tarifa_anual'          => $record->fee_anual,
                'plan'                  => $record->plan->description,
                'plan_id'               => $record->plan_id,
                'frecuencia_pago'       => $record->payment_frequency,
                'cobertura'            => isset($record->coverage_id) ? $record->coverage->price : 0,
                'fecha_afiliacion'      => $record->created_at->format('d/m/Y'),
                'tarifa_periodo'        => $record->total_amount,
            ];

            //Validamos si la afiliacionn la realizo un agente o una agencia
            if (isset($record->agent)) {
                $pagador['agente_agencia'] = $record->agent->name;
            } else {
                $pagador['agente_agencia'] = isset($record->agency->name_corporative) ? $record->agency->name_corporative : 'TuDrEnCasa';
            }


            //Nombre del PDF
            $name_pdf = 'CER-' . $record->code . '.pdf';

            //Beneficios asociados al plan
            $beneficios = $record->plan->benefitPlans->toArray();
            $beneficios_table = [];
            for ($i = 0; $i < count($beneficios); $i++) {
                $beneficios_table[$i] = $beneficios[$i]['description'];
            }

            ini_set('memory_limit', '2048M');
            set_time_limit(120);

            $pdf = Pdf::loadView('documents.certificate', compact('pagador', 'beneficios_table', 'afiliates'));
            $pdf->save(public_path('storage/certificados-doc/' . $name_pdf));

            Notification::make()
                ->title('¡TAREA COMPLETADA!')
                ->body('📎 ' . $name_pdf . ' ya se encuentra disponible para su descarga.')
                ->success()
                ->actions([
                    Action::make('download')
                        ->label('Descargar archivo')
                        ->url('/storage/certificados-doc/' . $name_pdf)
                ])
                ->sendToDatabase($user);

        } catch (\Throwable $th) {
            dd($th);
            Notification::make()
                ->title('EXCEPTION')
                ->body($th->getMessage())
                ->danger()
                ->send();
        }
    }
}