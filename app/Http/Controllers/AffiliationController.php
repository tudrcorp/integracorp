<?php

namespace App\Http\Controllers;

use App\Filament\Agents\Resources\AffiliationResource;
use App\Mail\SendMailKitBienvenida;
use App\Models\Affiliate;
use App\Models\DetailIndividualQuote;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;
use ZipArchive;

class AffiliationController extends Controller
{

    public static function uploadPayment($record, $data, $type_roll)
    {

        try {
            // dd($data, $record);
            // $validate = self::getValidation($record, $data);

            //1. Actualizamos la tabla de afiliaciones
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
            // dd($data);
            return true;

            //code...
        } catch (\Throwable $th) {
            dd($th);
            Log::error($th->getMessage());
            Notification::make()
                ->title('EXCEPTION')
                ->body($th->getMessage() . ' Linea: ' . $th->getLine() . ' Archivo: ' . $th->getFile())
                ->danger()
                ->send();
            //throw $th;
        }
    }

    public static function uploadPaymentMultipleAffiliations($records, $data, $type_roll)
    {

        try {

            foreach ($records as $record) {
                // dd($record);
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
                            'pay_amount_usd'            => $record['total_amount'],
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
                            'pay_amount_ves'            => $record['total_amount'] * $data['tasa_bcv'],
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
                            'total_amount'              => $record['total_amount'],
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
                            'reference_payment_usd'     => array_key_exists('reference_payment_usd', $data) ? $data['reference_payment_usd'] : 'N/A',
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
                            'pay_amount_usd'            => $record->total_amount,
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
                            'pay_amount_ves'            => $record->total_amount * $data['tasa_bcv'],
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
                            'pay_amount_usd'            => $record->total_amount,
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
                            'pay_amount_ves'            => $record->total_amount * $data['tasa_bcv'],
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
                            'pay_amount_usd'            => $record->total_amount,
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
                            'pay_amount_ves'            => $record->total_amount * $data['tasa_bcv'],
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
                ->body($th->getMessage() . ' Linea: ' . $th->getLine() . ' Archivo: ' . $th->getFile())
                ->danger()
                ->send();
            //throw $th;
        }
    }

    public static function generateCertificateIndividual($record, $afiliates, $user)
    {
        /**
         * Genera el certificado PDF para una afiliación individual.
         * @param $record mixed - Registro de la afiliación
         * @param $afiliates mixed - Afiliados de la afiliación
         * @param $user mixed - Usuario que generó el certificado
         * @return bool - true si se generó el certificado, false si hubo un error
         * @version 2.0
         */
        try {

            if (!$record || !$record->plan) {
                throw new \Exception("La afiliación no tiene un plan asociado o el registro es inválido.");
            }

            // ✅ Reconstruye el usuario dentro del job
            $user = $user instanceof User ? $user : User::find($user);

            if (!$user) {
                Log::error("Certificado Error: No se pudo encontrar el usuario para notificar.", ['user_id' => $user]);
                return;
            }

            // 3. Preparación de datos del pagador con valores de respaldo (Fallback)
            $effectiveDate = $record->effective_date;
            $finalVigencia = '';

            if (!empty($effectiveDate)) {
                try {
                    $finalVigencia = Carbon::createFromFormat('d/m/Y', $effectiveDate)->addYear()->format('d/m/Y');
                } catch (Throwable $e) {
                    Log::warning("Fecha de vigencia con formato inválido para registro ID: {$record->id}");
                }
            }

            $pagador = [
                'name'                 => $record->full_name_payer ?? 'S/N',
                'code'                 => $record->code ?? 'TEMP',
                'tarifa_anual'         => (float) ($record->fee_anual ?? 0),
                'plan'                 => $record->plan->description ?? 'Plan Estándar',
                'plan_id'              => $record->plan_id,
                'frecuencia_pago'      => $record->payment_frequency ?? 'N/A',
                'cobertura'            => (float) ($record->coverage->price ?? 0),
                'fecha_afiliacion'     => $record->activated_at ?? '',
                'tarifa_periodo'       => (float) ($record->total_amount ?? 0),
                'fecha_vigencia'       => $effectiveDate ?? '',
                'fecha_vigencia_final' => $finalVigencia,
            ];

            // $pagador = [
            //     'name'                  => $record->full_name_payer,
            //     'code'                  => $record->code,
            //     'tarifa_anual'          => $record->fee_anual,
            //     'plan'                  => $record->plan->description,
            //     'plan_id'               => $record->plan_id,
            //     'frecuencia_pago'       => $record->payment_frequency,
            //     'cobertura'             => isset($record->coverage_id) ? $record->coverage->price : 0,
            //     'fecha_afiliacion'      => $record->activated_at == null ? '' : $record->activated_at,
            //     'tarifa_periodo'        => $record->total_amount,
            //     'fecha_vigencia'        => $record->effective_date == null ? '' : $record->effective_date,
            //     'fecha_vigencia_final'  => $record->effective_date == null ? '' : Carbon::createFromFormat('d/m/Y', $record->effective_date)->addYear()->format('d/m/Y')
                
            // ];

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

            // ini_set('memory_limit', '2048M');
            // set_time_limit(120);

            // 5. Configuración de recursos
            ini_set('memory_limit', '512M'); // Suficiente para la mayoría de PDFs, evita saturar el server
            set_time_limit(180);

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
            // Log profesional de errores
            Log::error("Fallo crítico en generación de certificado", [
                'error'    => $th->getMessage(),
                'line'     => $th->getLine(),
                'file'     => $th->getFile(),
                'record'   => $record->id ?? 'N/A'
            ]);

            // Notificación de error amigable al usuario (no técnica)
            Notification::make()
                ->title('Error al generar certificado')
                ->body('Ocurrió un problema técnico al generar el PDF. Por favor, intente nuevamente o contacte a soporte.')
                ->danger()
                ->send();
        }
    }

    /**
     * Comprime y descarga múltiples archivos en un único archivo ZIP.
     * * NOTA: Requiere que la extensión 'zip' de PHP esté habilitada.
     */
    public static function downloadResendKit($record, $data)
    {

        try {
            
            /**
             * DESCARGAR KIT BIENVENIDA
             * @version 2.0
             */
            if ($data['option'] == 'DESCARGAR') {

                $certificado = storage_path('app/public/certificados-doc/CER-' . $record->code . '.pdf');
                $tarjeta     = storage_path('app/public/tarjeta-afiliacion/TAR-' . $record->code . '.pdf');

                if ($record->plan_id == 1) {
                    $condicionado = storage_path('app/public/condicionados/CondicionesINICIAL.pdf');
                } elseif ($record->plan_id == 2) {
                    $condicionado = storage_path('app/public/condicionados/CondicionesIDEAL.pdf');
                } elseif ($record->plan_id == 3) {
                    $condicionado = storage_path('app/public/condicionados/CondicionesESPECIAL.pdf');
                } else {
                    throw new \Exception("Plan no soportado: {$record->plan_id}");
                }

                if (!file_exists($certificado) || !file_exists($tarjeta) || !file_exists($condicionado)) {
                    Notification::make()
                        ->title('Error')
                        ->body('Uno o más archivos del kit no existen.')
                        ->danger()
                        ->send();

                    return null;
                }
    
                $files = [
                    $certificado,
                    $tarjeta,
                    $condicionado
                ];

                // dd($files);

                // 2. Configurar el archivo ZIP temporal de salida
                $zipFileName = 'Kit_Bienvenida_' . time() . '.zip';
                // Usamos el directorio temporal del sistema operativo
                $tempZipPath = storage_path('app/public/kit-temp/') . $zipFileName;

                // AffiliationController::downloadMultipleFilesAsZip($files);
                $zip = new ZipArchive;

                // Abrir/Crear el archivo ZIP
                if ($zip->open($tempZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
                    // Error en la creación del archivo ZIP
                    return response('Error: No se pudo crear el archivo ZIP temporal.', 500);
                }

                for ($i = 0; $i < count($files); $i++) {

                    $zip->addFile($files[$i], basename($files[$i]));
                }

                $zip->close();
                Log::info('ZIP path', ['path' => $tempZipPath, 'exists' => file_exists($tempZipPath)]);
                Log::info("DESCARGA COMPLETADA: Kit enviado correctamente.", [
                    // 'to' => $data['email'],
                    'user' => $record->full_name_payer,
                ]);
                return $tempZipPath;
                //return response()->download($tempZipPath, $zipFileName)->deleteFileAfterSend(true);

            }

            /**
             * REENVIAR KIT BIENVENIDA
             * @version 2.0
             */
            if ($data['option'] == 'REENVIAR') {

                $code = [
                    'code' => $record->code
                ];

                if ($record->plan_id == 1) {
                    $condicionado = 'CondicionesINICIAL.pdf';
                }
                if ($record->plan_id == 2) {
                    $condicionado = 'CondicionesIDEAL.pdf';
                }
                if ($record->plan_id == 3) {
                    $condicionado = 'CondicionesESPECIAL.pdf';
                }

                Mail::to($data['email'])->send(new SendMailKitBienvenida($code, $condicionado));

                Log::info("ENVIO COMPLETADO: Kit enviado correctamente.", [
                    // 'to' => $data['email'],
                    'user' => $record->full_name_payer,
                ]);

                Notification::make()
                ->title('¡TAREA COMPLETADA!')
                ->body('✅ Kit reenviado correctamente.')
                ->success()
                ->send();
    
            }
            
        } catch (\Throwable $th) {

            Log::error("FALLA DE ENVIO: No se pudo enviar el kit.", [
                // 'to' => $data['email'],
                'user' => $record->full_name_payer,
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString()
            ]);

            Notification::make()
                ->title('EXCEPTION')
                ->body($th->getMessage() . ' Linea: ' . $th->getLine() . ' Archivo: ' . $th->getFile())
                ->danger()
                ->send();
            
        }
    }
}