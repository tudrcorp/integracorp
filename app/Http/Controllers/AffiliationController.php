<?php

namespace App\Http\Controllers;

use App\Mail\SendMailKitBienvenida;
use App\Models\Affiliate;
use App\Models\Affiliation;
use App\Models\Plan;
use App\Models\User;
use App\Support\AffiliationCorporates\CorporateCertificateBenefitSections;
use App\Support\Affiliations\WelcomeKitAttachments;
use App\Support\DomPdfBatchRenderOptions;
use App\Support\SecurityAudit;
use App\Support\WhiteCompanies\WhiteCompanyDocumentBrand;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;
use ZipArchive;

class AffiliationController extends Controller
{
    public static function uploadPayment($record, $data, $type_roll)
    {

        try {
            // dd($data, $record);
            // $validate = self::getValidation($record, $data);

            // 1. Actualizamos la tabla de afiliaciones
            $record->update([
                'family_members' => Affiliate::select('affiliation_id')->where('affiliation_id', $record->id)->count(),
            ]);

            if ($record['payment_frequency'] == 'ANUAL') {

                /** PAGO USD */
                if ($data['payment_method'] == 'EFECTIVO US$' || $data['payment_method'] == 'ZELLE' || $data['payment_method'] == 'TRANSFERENCIA US$' || $data['payment_method'] == 'LINK DE PAGO') {

                    $record->paid_memberships()->create([
                        'affiliation_id' => $record->id,
                        'agent_id' => $record->agent_id,
                        'code_agency' => $record->code_agency,
                        'plan_id' => $record->plan_id,
                        'coverage_id' => $record->coverage_id,
                        'name_ti_usd' => array_key_exists('name_ti_usd', $data) ? $data['name_ti_usd'] : 'N/A',
                        'total_amount' => $data['total_amount'],
                        'pay_amount_usd' => $data['total_amount'],
                        'pay_amount_ves' => isset($data['pay_amount_ves']) ? $data['pay_amount_ves'] : 0.00,
                        'document_usd' => $data['document_usd'],
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
                        'date_payment_voucher' => $data['date_payment_voucher'],

                    ]);
                }

                /** PAGO BSD */
                if ($data['payment_method'] == 'PAGO MOVIL VES' || $data['payment_method'] == 'TRANSFERENCIA VES') {

                    $record->paid_memberships()->create([
                        'affiliation_id' => $record->id,
                        'agent_id' => $record->agent_id,
                        'code_agency' => $record->code_agency,
                        'plan_id' => $record->plan_id,
                        'coverage_id' => $record->coverage_id,
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
                        'date_payment_voucher' => $data['date_payment_voucher'],
                    ]);
                }

                /** PAGO MULTIPLE */
                if ($data['payment_method'] == 'MULTIPLE') {

                    $record->paid_memberships()->create([
                        'affiliation_id' => $record->id,
                        'agent_id' => $record->agent_id,
                        'code_agency' => $record->code_agency,
                        'plan_id' => $record->plan_id,
                        'coverage_id' => $record->coverage_id,
                        'total_amount' => $data['total_amount'],
                        'tasa_bcv' => $data['tasa_bcv'],
                        'pay_amount_usd' => $data['pay_amount_usd'],
                        'pay_amount_ves' => $data['pay_amount_ves'],
                        'document_usd' => $data['document_usd'],
                        'document_ves' => $data['document_ves'],
                        'payment_method' => $data['payment_method'],
                        'payment_method_usd' => $data['payment_method_usd'],
                        'payment_method_ves' => $data['payment_method_ves'],
                        'payment_frequency' => $record['payment_frequency'],
                        'payment_date' => now()->format('d-m-Y'),
                        'prox_payment_date' => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'reference_payment_usd' => array_key_exists('reference_payment_usd', $data) ? $data['reference_payment_usd'] : 'N/A',
                        'reference_payment_ves' => $data['reference_payment_ves'],
                        'observations_payment' => $data['observations_payment'] == null ? 'N/A' : $data['observations_payment'],
                        'bank_usd' => $data['bank_usd'] == null ? 'N/A' : $data['bank_usd'],
                        'bank_ves' => $data['bank_ves'] ?? 'N/A',
                        'renewal_date' => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'created_by' => Auth::user()->name,
                        'type_roll' => $type_roll,
                        'name_ti_usd' => array_key_exists('name_ti_usd', $data) ? $data['name_ti_usd'] : 'N/A',
                        'date_payment_voucher' => $data['date_payment_voucher'],
                    ]);
                }
            }

            if ($record['payment_frequency'] == 'TRIMESTRAL') {

                /** PAGO USD */
                if ($data['payment_method'] == 'EFECTIVO US$' || $data['payment_method'] == 'ZELLE' || $data['payment_method'] == 'TRANSFERENCIA US$' || $data['payment_method'] == 'LINK DE PAGO') {
                    $record->paid_memberships()->create([
                        'affiliation_id' => $record->id,
                        'agent_id' => $record->agent_id,
                        'code_agency' => $record->code_agency,
                        'plan_id' => $record->plan_id,
                        'coverage_id' => $record->coverage_id,
                        'total_amount' => $data['total_amount'],
                        'pay_amount_usd' => $data['total_amount'],
                        'pay_amount_ves' => isset($data['pay_amount_ves']) ? $data['pay_amount_ves'] : 0.00,
                        'document_usd' => $data['document_usd'],
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
                        'name_ti_usd' => array_key_exists('name_ti_usd', $data) ? $data['name_ti_usd'] : 'N/A',
                        'date_payment_voucher' => $data['date_payment_voucher'],
                    ]);
                }

                /** PAGO BSD */
                if ($data['payment_method'] == 'PAGO MOVIL VES' || $data['payment_method'] == 'TRANSFERENCIA VES') {

                    $record->paid_memberships()->create([
                        'affiliation_id' => $record->id,
                        'agent_id' => $record->agent_id,
                        'code_agency' => $record->code_agency,
                        'plan_id' => $record->plan_id,
                        'coverage_id' => $record->coverage_id,
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
                        'date_payment_voucher' => $data['date_payment_voucher'],
                    ]);
                }

                /** PAGO MULTIPLE */
                if ($data['payment_method'] == 'MULTIPLE') {

                    $record->paid_memberships()->create([
                        'affiliation_id' => $record->id,
                        'agent_id' => $record->agent_id,
                        'code_agency' => $record->code_agency,
                        'plan_id' => $record->plan_id,
                        'coverage_id' => $record->coverage_id,
                        'total_amount' => $data['total_amount'],
                        'tasa_bcv' => $data['tasa_bcv'],
                        'pay_amount_usd' => $data['pay_amount_usd'],
                        'pay_amount_ves' => $data['pay_amount_ves'],
                        'document_usd' => $data['document_usd'],
                        'document_ves' => $data['document_ves'],
                        'payment_method' => $data['payment_method'],
                        'payment_method_usd' => $data['payment_method_usd'],
                        'payment_method_ves' => $data['payment_method_ves'],
                        'payment_frequency' => $record['payment_frequency'],
                        'payment_date' => now()->format('d-m-Y'),
                        'prox_payment_date' => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'reference_payment_usd' => array_key_exists('reference_payment_usd', $data) ? $data['reference_payment_usd'] : 'N/A',
                        'reference_payment_ves' => $data['reference_payment_ves'],
                        'observations_payment' => $data['observations_payment'] == null ? 'N/A' : $data['observations_payment'],
                        'bank_usd' => $data['bank_usd'] == null ? 'N/A' : $data['bank_usd'],
                        'bank_ves' => $data['bank_ves'] ?? 'N/A',
                        'renewal_date' => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'created_by' => Auth::user()->name,
                        'type_roll' => $type_roll,
                        'name_ti_usd' => array_key_exists('name_ti_usd', $data) ? $data['name_ti_usd'] : 'N/A',
                        'date_payment_voucher' => $data['date_payment_voucher'],
                    ]);
                }
            }

            if ($record['payment_frequency'] == 'SEMESTRAL') {

                /** PAGO USD */
                if ($data['payment_method'] == 'EFECTIVO US$' || $data['payment_method'] == 'ZELLE' || $data['payment_method'] == 'TRANSFERENCIA US$' || $data['payment_method'] == 'LINK DE PAGO') {

                    $record->paid_memberships()->create([
                        'affiliation_id' => $record->id,
                        'agent_id' => $record->agent_id,
                        'code_agency' => $record->code_agency,
                        'plan_id' => $record->plan_id,
                        'coverage_id' => $record->coverage_id,
                        'name_ti_usd' => array_key_exists('name_ti_usd', $data) ? $data['name_ti_usd'] : 'N/A',
                        'total_amount' => $data['total_amount'],
                        'pay_amount_usd' => $data['total_amount'],
                        'pay_amount_ves' => isset($data['pay_amount_ves']) ? $data['pay_amount_ves'] : 0.00,
                        'document_usd' => $data['document_usd'],
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
                        'date_payment_voucher' => $data['date_payment_voucher'],
                    ]);
                }

                /** PAGO BSD */
                if ($data['payment_method'] == 'PAGO MOVIL VES' || $data['payment_method'] == 'TRANSFERENCIA VES') {

                    $record->paid_memberships()->create([
                        'affiliation_id' => $record->id,
                        'agent_id' => $record->agent_id,
                        'code_agency' => $record->code_agency,
                        'plan_id' => $record->plan_id,
                        'coverage_id' => $record->coverage_id,
                        'name_ti_usd' => array_key_exists('name_ti_usd', $data) ? $data['name_ti_usd'] : 'N/A',
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
                        'date_payment_voucher' => $data['date_payment_voucher'],
                    ]);
                }

                /** PAGO MULTIPLE */
                if ($data['payment_method'] == 'MULTIPLE') {

                    $record->paid_memberships()->create([
                        'affiliation_id' => $record->id,
                        'agent_id' => $record->agent_id,
                        'code_agency' => $record->code_agency,
                        'plan_id' => $record->plan_id,
                        'coverage_id' => $record->coverage_id,
                        'name_ti_usd' => array_key_exists('name_ti_usd', $data) ? $data['name_ti_usd'] : 'N/A',
                        'total_amount' => $data['total_amount'],
                        'tasa_bcv' => $data['tasa_bcv'],
                        'pay_amount_usd' => $data['pay_amount_usd'],
                        'pay_amount_ves' => $data['pay_amount_ves'],
                        'document_usd' => $data['document_usd'],
                        'document_ves' => $data['document_ves'],
                        'payment_method' => $data['payment_method'],
                        'payment_method_usd' => $data['payment_method_usd'],
                        'payment_method_ves' => $data['payment_method_ves'],
                        'payment_frequency' => $record['payment_frequency'],
                        'payment_date' => now()->format('d-m-Y'),
                        'prox_payment_date' => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'reference_payment_usd' => array_key_exists('reference_payment_usd', $data) ? $data['reference_payment_usd'] : 'N/A',
                        'reference_payment_ves' => $data['reference_payment_ves'],
                        'observations_payment' => $data['observations_payment'] == null ? 'N/A' : $data['observations_payment'],
                        'bank_usd' => $data['bank_usd'] == null ? 'N/A' : $data['bank_usd'],
                        'bank_ves' => $data['bank_ves'] ?? 'N/A',
                        'renewal_date' => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'created_by' => Auth::user()->name,
                        'type_roll' => $type_roll,
                        'date_payment_voucher' => $data['date_payment_voucher'],
                    ]);
                }
            }

            if ($record['payment_frequency'] == 'MENSUAL') {

                /** PAGO USD */
                if ($data['payment_method'] == 'EFECTIVO US$' || $data['payment_method'] == 'ZELLE' || $data['payment_method'] == 'TRANSFERENCIA US$' || $data['payment_method'] == 'LINK DE PAGO') {

                    $record->paid_memberships()->create([
                        'affiliation_id' => $record->id,
                        'agent_id' => $record->agent_id,
                        'code_agency' => $record->code_agency,
                        'plan_id' => $record->plan_id,
                        'coverage_id' => $record->coverage_id,
                        'name_ti_usd' => array_key_exists('name_ti_usd', $data) ? $data['name_ti_usd'] : 'N/A',
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
                        'date_payment_voucher' => $data['date_payment_voucher'],
                    ]);
                }

                /** PAGO BSD */
                if ($data['payment_method'] == 'PAGO MOVIL VES' || $data['payment_method'] == 'TRANSFERENCIA VES') {

                    $record->paid_memberships()->create([
                        'affiliation_id' => $record->id,
                        'agent_id' => $record->agent_id,
                        'code_agency' => $record->code_agency,
                        'plan_id' => $record->plan_id,
                        'coverage_id' => $record->coverage_id,
                        'name_ti_usd' => array_key_exists('name_ti_usd', $data) ? $data['name_ti_usd'] : 'N/A',
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
                        'date_payment_voucher' => $data['date_payment_voucher'],
                    ]);
                }

                /** PAGO MULTIPLE */
                if ($data['payment_method'] == 'MULTIPLE') {

                    $record->paid_memberships()->create([
                        'affiliation_id' => $record->id,
                        'agent_id' => $record->agent_id,
                        'code_agency' => $record->code_agency,
                        'plan_id' => $record->plan_id,
                        'coverage_id' => $record->coverage_id,
                        'name_ti_usd' => array_key_exists('name_ti_usd', $data) ? $data['name_ti_usd'] : 'N/A',
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
                        'reference_payment_usd' => array_key_exists('reference_payment_usd', $data) ? $data['reference_payment_usd'] : 'N/A',
                        'reference_payment_ves' => $data['reference_payment_ves'],
                        'observations_payment' => $data['observations_payment'] == null ? 'N/A' : $data['observations_payment'],
                        'bank_usd' => $data['bank_usd'] == null ? 'N/A' : $data['bank_usd'],
                        'bank_ves' => $data['bank_ves'] ?? 'N/A',
                        'renewal_date' => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                        'created_by' => Auth::user()->name,
                        'type_roll' => $type_roll,
                        'date_payment_voucher' => $data['date_payment_voucher'],
                    ]);
                }
            }

            SecurityAudit::log('AUDIT_AFFILIATION_PAYMENT_VOUCHER_UPLOADED', 'affiliations.upload-payment', [
                'affiliation_id' => $record->id,
                'affiliation_code' => $record->code,
                'payment_frequency' => $record['payment_frequency'] ?? null,
                'payment_method' => $data['payment_method'] ?? null,
                'type_roll' => $type_roll,
                'latest_paid_membership_id' => $record->paid_memberships()->latest('id')->value('id'),
            ]);

            return true;

            // code...
        } catch (\Throwable $th) {
            SecurityAudit::log('AUDIT_AFFILIATION_PAYMENT_VOUCHER_UPLOAD_FAILED', 'affiliations.upload-payment', [
                'affiliation_id' => $record->id ?? null,
                'affiliation_code' => $record->code ?? null,
                'error' => $th->getMessage(),
            ]);

            dd($th);
            Log::error($th->getMessage());
            Notification::make()
                ->title('EXCEPTION')
                ->body($th->getMessage().' Linea: '.$th->getLine().' Archivo: '.$th->getFile())
                ->danger()
                ->send();
            // throw $th;
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
                    if ($data['payment_method'] == 'EFECTIVO US$' || $data['payment_method'] == 'ZELLE' || $data['payment_method'] == 'TRANSFERENCIA US$' || $data['payment_method'] == 'LINK DE PAGO') {

                        $record->paid_memberships()->create([
                            'affiliation_id' => $record->id,
                            'agent_id' => $record->agent_id,
                            'code_agency' => $record->code_agency,
                            'plan_id' => $record->plan_id,
                            'coverage_id' => $record->coverage_id,
                            'name_ti_usd' => array_key_exists('name_ti_usd', $data) ? $data['name_ti_usd'] : 'N/A',
                            'total_amount' => $data['total_amount'],
                            'pay_amount_usd' => $record['total_amount'],
                            'pay_amount_ves' => isset($data['pay_amount_ves']) ? $data['pay_amount_ves'] : 0.00,
                            'document_usd' => $data['document_usd'],
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
                            'date_payment_voucher' => $data['date_payment_voucher'],

                        ]);
                    }

                    /** PAGO BSD */
                    if ($data['payment_method'] == 'PAGO MOVIL VES' || $data['payment_method'] == 'TRANSFERENCIA VES') {

                        $record->paid_memberships()->create([
                            'affiliation_id' => $record->id,
                            'agent_id' => $record->agent_id,
                            'code_agency' => $record->code_agency,
                            'plan_id' => $record->plan_id,
                            'coverage_id' => $record->coverage_id,
                            'total_amount' => $data['total_amount'],
                            'tasa_bcv' => $data['tasa_bcv'],
                            'pay_amount_usd' => isset($data['pay_amount_usd']) ? $data['pay_amount_usd'] : 0.00,
                            'pay_amount_ves' => $record['total_amount'] * $data['tasa_bcv'],
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
                            'date_payment_voucher' => $data['date_payment_voucher'],
                        ]);
                    }

                    /** PAGO MULTIPLE */
                    if ($data['payment_method'] == 'MULTIPLE') {

                        $record->paid_memberships()->create([
                            'affiliation_id' => $record->id,
                            'agent_id' => $record->agent_id,
                            'code_agency' => $record->code_agency,
                            'plan_id' => $record->plan_id,
                            'coverage_id' => $record->coverage_id,
                            'total_amount' => $record['total_amount'],
                            'tasa_bcv' => $data['tasa_bcv'],
                            'pay_amount_usd' => $data['pay_amount_usd'],
                            'pay_amount_ves' => $data['pay_amount_ves'],
                            'document_usd' => $data['document_usd'],
                            'document_ves' => $data['document_ves'],
                            'payment_method' => $data['payment_method'],
                            'payment_method_usd' => $data['payment_method_usd'],
                            'payment_method_ves' => $data['payment_method_ves'],
                            'payment_frequency' => $record['payment_frequency'],
                            'payment_date' => now()->format('d-m-Y'),
                            'prox_payment_date' => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                            'reference_payment_usd' => array_key_exists('reference_payment_usd', $data) ? $data['reference_payment_usd'] : 'N/A',
                            'reference_payment_ves' => $data['reference_payment_ves'],
                            'observations_payment' => $data['observations_payment'] == null ? 'N/A' : $data['observations_payment'],
                            'bank_usd' => $data['bank_usd'] == null ? 'N/A' : $data['bank_usd'],
                            'bank_ves' => $data['bank_ves'] ?? 'N/A',
                            'renewal_date' => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                            'created_by' => Auth::user()->name,
                            'type_roll' => $type_roll,
                            'name_ti_usd' => array_key_exists('name_ti_usd', $data) ? $data['name_ti_usd'] : 'N/A',
                            'date_payment_voucher' => $data['date_payment_voucher'],
                        ]);
                    }
                }

                if ($record['payment_frequency'] == 'TRIMESTRAL') {

                    /** PAGO USD */
                    if ($data['payment_method'] == 'EFECTIVO US$' || $data['payment_method'] == 'ZELLE' || $data['payment_method'] == 'TRANSFERENCIA US$' || $data['payment_method'] == 'LINK DE PAGO') {

                        $record->paid_memberships()->create([
                            'affiliation_id' => $record->id,
                            'agent_id' => $record->agent_id,
                            'code_agency' => $record->code_agency,
                            'plan_id' => $record->plan_id,
                            'coverage_id' => $record->coverage_id,
                            'total_amount' => $record->total_amount,
                            'pay_amount_usd' => $record->total_amount,
                            'pay_amount_ves' => isset($data['pay_amount_ves']) ? $data['pay_amount_ves'] : 0.00,
                            'document_usd' => $data['document_usd'],
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
                            'name_ti_usd' => array_key_exists('name_ti_usd', $data) ? $data['name_ti_usd'] : 'N/A',
                            'date_payment_voucher' => $data['date_payment_voucher'],
                        ]);
                    }

                    /** PAGO BSD */
                    if ($data['payment_method'] == 'PAGO MOVIL VES' || $data['payment_method'] == 'TRANSFERENCIA VES') {

                        $record->paid_memberships()->create([
                            'affiliation_id' => $record->id,
                            'agent_id' => $record->agent_id,
                            'code_agency' => $record->code_agency,
                            'plan_id' => $record->plan_id,
                            'coverage_id' => $record->coverage_id,
                            'total_amount' => $record->total_amount,
                            'tasa_bcv' => $data['tasa_bcv'],
                            'pay_amount_usd' => isset($data['pay_amount_usd']) ? $data['pay_amount_usd'] : 0.00,
                            'pay_amount_ves' => $record->total_amount * $data['tasa_bcv'],
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
                            'date_payment_voucher' => $data['date_payment_voucher'],
                        ]);
                    }

                    /** PAGO MULTIPLE */
                    if ($data['payment_method'] == 'MULTIPLE') {

                        $record->paid_memberships()->create([
                            'affiliation_id' => $record->id,
                            'agent_id' => $record->agent_id,
                            'code_agency' => $record->code_agency,
                            'plan_id' => $record->plan_id,
                            'coverage_id' => $record->coverage_id,
                            'total_amount' => $record->total_amount,
                            'tasa_bcv' => $data['tasa_bcv'],
                            'pay_amount_usd' => $data['pay_amount_usd'],
                            'pay_amount_ves' => $data['pay_amount_ves'],
                            'document_usd' => $data['document_usd'],
                            'document_ves' => $data['document_ves'],
                            'payment_method' => $data['payment_method'],
                            'payment_method_usd' => $data['payment_method_usd'],
                            'payment_method_ves' => $data['payment_method_ves'],
                            'payment_frequency' => $record['payment_frequency'],
                            'payment_date' => now()->format('d-m-Y'),
                            'prox_payment_date' => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                            'reference_payment_usd' => array_key_exists('reference_payment_usd', $data) ? $data['reference_payment_usd'] : 'N/A',
                            'reference_payment_ves' => $data['reference_payment_ves'],
                            'observations_payment' => $data['observations_payment'] == null ? 'N/A' : $data['observations_payment'],
                            'bank_usd' => $data['bank_usd'] == null ? 'N/A' : $data['bank_usd'],
                            'bank_ves' => $data['bank_ves'] ?? 'N/A',
                            'renewal_date' => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                            'created_by' => Auth::user()->name,
                            'type_roll' => $type_roll,
                            'name_ti_usd' => array_key_exists('name_ti_usd', $data) ? $data['name_ti_usd'] : 'N/A',
                            'date_payment_voucher' => $data['date_payment_voucher'],
                        ]);
                    }
                }

                if ($record['payment_frequency'] == 'SEMESTRAL') {

                    /** PAGO USD */
                    if ($data['payment_method'] == 'EFECTIVO US$' || $data['payment_method'] == 'ZELLE' || $data['payment_method'] == 'TRANSFERENCIA US$' || $data['payment_method'] == 'LINK DE PAGO') {

                        $record->paid_memberships()->create([
                            'affiliation_id' => $record->id,
                            'agent_id' => $record->agent_id,
                            'code_agency' => $record->code_agency,
                            'plan_id' => $record->plan_id,
                            'coverage_id' => $record->coverage_id,
                            'name_ti_usd' => array_key_exists('name_ti_usd', $data) ? $data['name_ti_usd'] : 'N/A',
                            'total_amount' => $record->total_amount,
                            'pay_amount_usd' => $record->total_amount,
                            'pay_amount_ves' => isset($data['pay_amount_ves']) ? $data['pay_amount_ves'] : 0.00,
                            'document_usd' => $data['document_usd'],
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
                            'date_payment_voucher' => $data['date_payment_voucher'],
                        ]);
                    }

                    /** PAGO BSD */
                    if ($data['payment_method'] == 'PAGO MOVIL VES' || $data['payment_method'] == 'TRANSFERENCIA VES') {

                        $record->paid_memberships()->create([
                            'affiliation_id' => $record->id,
                            'agent_id' => $record->agent_id,
                            'code_agency' => $record->code_agency,
                            'plan_id' => $record->plan_id,
                            'coverage_id' => $record->coverage_id,
                            'name_ti_usd' => isset($data['name_ti_usd']) ? $data['name_ti_usd'] : 'N/A',
                            'total_amount' => $record->total_amount,
                            'tasa_bcv' => $data['tasa_bcv'],
                            'pay_amount_usd' => isset($data['pay_amount_usd']) ? $data['pay_amount_usd'] : 0.00,
                            'pay_amount_ves' => $record->total_amount * $data['tasa_bcv'],
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
                            'date_payment_voucher' => $data['date_payment_voucher'],
                        ]);
                    }

                    /** PAGO MULTIPLE */
                    if ($data['payment_method'] == 'MULTIPLE') {

                        $record->paid_memberships()->create([
                            'affiliation_id' => $record->id,
                            'agent_id' => $record->agent_id,
                            'code_agency' => $record->code_agency,
                            'plan_id' => $record->plan_id,
                            'coverage_id' => $record->coverage_id,
                            'name_ti_usd' => array_key_exists('name_ti_usd', $data) ? $data['name_ti_usd'] : 'N/A',
                            'total_amount' => $record->total_amount,
                            'tasa_bcv' => $data['tasa_bcv'],
                            'pay_amount_usd' => $data['pay_amount_usd'],
                            'pay_amount_ves' => $data['pay_amount_ves'],
                            'document_usd' => $data['document_usd'],
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
                            'date_payment_voucher' => $data['date_payment_voucher'],
                        ]);
                    }
                }

                if ($record['payment_frequency'] == 'MENSUAL') {

                    /** PAGO USD */
                    if ($data['payment_method'] == 'EFECTIVO US$' || $data['payment_method'] == 'ZELLE' || $data['payment_method'] == 'TRANSFERENCIA US$' || $data['payment_method'] == 'LINK DE PAGO') {

                        $record->paid_memberships()->create([
                            'affiliation_id' => $record->id,
                            'agent_id' => $record->agent_id,
                            'code_agency' => $record->code_agency,
                            'plan_id' => $record->plan_id,
                            'coverage_id' => $record->coverage_id,
                            'name_ti_usd' => array_key_exists('name_ti_usd', $data) ? $data['name_ti_usd'] : 'N/A',
                            'total_amount' => $record->total_amount,
                            'pay_amount_usd' => $record->total_amount,
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
                            'date_payment_voucher' => $data['date_payment_voucher'],
                        ]);
                    }

                    /** PAGO BSD */
                    if ($data['payment_method'] == 'PAGO MOVIL VES' || $data['payment_method'] == 'TRANSFERENCIA VES') {

                        $record->paid_memberships()->create([
                            'affiliation_id' => $record->id,
                            'agent_id' => $record->agent_id,
                            'code_agency' => $record->code_agency,
                            'plan_id' => $record->plan_id,
                            'coverage_id' => $record->coverage_id,
                            'name_ti_usd' => array_key_exists('name_ti_usd', $data) ? $data['name_ti_usd'] : 'N/A',
                            'total_amount' => $record->total_amount,
                            'tasa_bcv' => $data['tasa_bcv'],
                            'pay_amount_usd' => isset($data['pay_amount_usd']) ? $data['pay_amount_usd'] : 0.00,
                            'pay_amount_ves' => $record->total_amount * $data['tasa_bcv'],
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
                            'date_payment_voucher' => $data['date_payment_voucher'],
                        ]);
                    }

                    /** PAGO MULTIPLE */
                    if ($data['payment_method'] == 'MULTIPLE') {

                        $record->paid_memberships()->create([
                            'affiliation_id' => $record->id,
                            'agent_id' => $record->agent_id,
                            'code_agency' => $record->code_agency,
                            'plan_id' => $record->plan_id,
                            'coverage_id' => $record->coverage_id,
                            'name_ti_usd' => array_key_exists('name_ti_usd', $data) ? $data['name_ti_usd'] : 'N/A',
                            'total_amount' => $record->total_amount,
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
                            'reference_payment_usd' => array_key_exists('reference_payment_usd', $data) ? $data['reference_payment_usd'] : 'N/A',
                            'reference_payment_ves' => $data['reference_payment_ves'],
                            'observations_payment' => $data['observations_payment'] == null ? 'N/A' : $data['observations_payment'],
                            'bank_usd' => $data['bank_usd'] == null ? 'N/A' : $data['bank_usd'],
                            'bank_ves' => $data['bank_ves'] ?? 'N/A',
                            'renewal_date' => Carbon::createFromFormat('d-m-Y', now()->format('d-m-Y'))->addYear()->format('d-m-Y'),
                            'created_by' => Auth::user()->name,
                            'type_roll' => $type_roll,
                            'date_payment_voucher' => $data['date_payment_voucher'],
                        ]);
                    }
                }
            }

            SecurityAudit::log('AUDIT_AFFILIATION_PAYMENT_VOUCHER_UPLOADED_BULK', 'affiliations.upload-payment-multiple', [
                'records_count' => count($records),
                'payment_method' => $data['payment_method'] ?? null,
                'type_roll' => $type_roll,
            ]);

            return true;

            // code...
        } catch (\Throwable $th) {
            SecurityAudit::log('AUDIT_AFFILIATION_PAYMENT_VOUCHER_UPLOAD_BULK_FAILED', 'affiliations.upload-payment-multiple', [
                'records_count' => count($records ?? []),
                'error' => $th->getMessage(),
            ]);

            dd($th);
            Log::error($th->getMessage());
            Notification::make()
                ->title('EXCEPTION')
                ->body($th->getMessage().' Linea: '.$th->getLine().' Archivo: '.$th->getFile())
                ->danger()
                ->send();
            // throw $th;
        }
    }

    public static function generateCertificateIndividual($record, $afiliates, $user, bool $notifyUser = true, bool $rethrowOnFailure = false)
    {
        /**
         * Genera el certificado PDF para una afiliación individual.
         *
         * @param  $record  mixed - Registro de la afiliación
         * @param  $afiliates  mixed - Afiliados de la afiliación
         * @param  $user  mixed - Usuario que generó el certificado
         * @return bool - true si se generó el certificado, false si hubo un error
         *
         * @version 2.0
         */
        try {

            if (! $record || ! $record->plan) {
                throw new \Exception('La afiliación no tiene un plan asociado o el registro es inválido.');
            }

            $userForNotification = null;
            if ($notifyUser) {
                $userForNotification = $user instanceof User ? $user : User::find($user);
                if (! $userForNotification) {
                    Log::error('Certificado Error: No se pudo encontrar el usuario para notificar.', ['user_id' => $user]);

                    return;
                }
            }

            // 3. Preparación de datos del pagador con valores de respaldo (Fallback)
            $effectiveDate = $record->effective_date;
            $finalVigencia = '';

            if (! empty($effectiveDate)) {
                try {
                    $finalVigencia = Carbon::createFromFormat('d/m/Y', $effectiveDate)->addYear()->format('d/m/Y');
                } catch (Throwable $e) {
                    Log::warning("Fecha de vigencia con formato inválido para registro ID: {$record->id}");
                }
            }

            $pagador = [
                'name' => $record->full_name_payer ?? 'S/N',
                'code' => $record->code ?? 'TEMP',
                'tarifa_anual' => (float) ($record->fee_anual ?? 0),
                'plan' => $record->plan->description ?? 'Plan Estándar',
                'plan_id' => $record->plan_id,
                'frecuencia_pago' => $record->payment_frequency ?? 'N/A',
                'cobertura' => (float) ($record->coverage->price ?? 0),
                'fecha_afiliacion' => $record->activated_at ?? '',
                'tarifa_periodo' => (float) ($record->total_amount ?? 0),
                'fecha_vigencia' => $effectiveDate ?? '',
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

            // Validamos si la afiliacionn la realizo un agente o una agencia
            if (isset($record->agent)) {
                $pagador['agente_agencia'] = $record->agent->name;
            } else {
                $pagador['agente_agencia'] = isset($record->agency->name_corporative) ? $record->agency->name_corporative : 'TuDrEnCasa';
            }

            // Nombre del PDF
            $name_pdf = 'CER-'.$record->code.'.pdf';

            // Beneficios asociados al plan
            $beneficios = $record->plan->benefitPlans->toArray();
            $beneficios_table = [];
            for ($i = 0; $i < count($beneficios); $i++) {
                $beneficios_table[$i] = $beneficios[$i]['description'];
            }

            // ini_set('memory_limit', '2048M');
            // set_time_limit(120);

            // En regeneración por lote el servicio ya fija límites; no reducir memory aquí.
            if (! $rethrowOnFailure) {
                ini_set('memory_limit', '512M'); // Suficiente para la mayoría de PDFs, evita saturar el server
                set_time_limit(180);
            }

            $pdf = Pdf::loadView(
                'documents.certificate',
                self::dataForCertificatePdfView(
                    $pagador,
                    $beneficios_table,
                    $afiliates,
                    $record instanceof Affiliation
                        ? WhiteCompanyDocumentBrand::forAffiliation($record)
                        : WhiteCompanyDocumentBrand::tdec(),
                ),
            );
            DomPdfBatchRenderOptions::apply($pdf);
            $pdf->save(public_path('storage/certificados-doc/'.$name_pdf));

            if ($notifyUser && $userForNotification) {
                Notification::make()
                    ->title('¡TAREA COMPLETADA!')
                    ->body('📎 '.$name_pdf.' ya se encuentra disponible para su descarga.')
                    ->success()
                    ->actions([
                        Action::make('download')
                            ->label('Descargar archivo')
                            ->url('/storage/certificados-doc/'.$name_pdf),
                    ])
                    ->sendToDatabase($userForNotification);
            }
        } catch (\Throwable $th) {
            // Log profesional de errores
            Log::error('Fallo crítico en generación de certificado', [
                'error' => $th->getMessage(),
                'line' => $th->getLine(),
                'file' => $th->getFile(),
                'record' => $record->id ?? 'N/A',
            ]);

            if ($rethrowOnFailure) {
                throw $th;
            }

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
    /**
     * Descarga (ZIP) o reenvía (correo) el kit de bienvenida de una afiliación individual.
     *
     * Los tres documentos se resuelven y verifican antes de actuar: la tarjeta ya no siempre
     * se llama `TAR-{code}.pdf` y el condicionado depende de la marca (TDG o empresa aliada).
     *
     * @param  array<string, mixed>  $data
     * @return string|bool Ruta del ZIP en DESCARGAR (null si falta algo); true/false si se envió en REENVIAR.
     */
    public static function downloadResendKit($record, $data)
    {
        $option = $data['option'] ?? null;
        $kit = WelcomeKitAttachments::forAffiliation($record);

        if (! $kit->isComplete()) {
            Notification::make()
                ->title('El kit está incompleto')
                ->body($kit->missingSummary().' Genera los documentos pendientes de la afiliación '
                    .($record->code ?: '').' y vuelve a intentarlo.')
                ->icon('heroicon-s-x-circle')
                ->iconColor('danger')
                ->danger()
                ->persistent()
                ->send();

            Log::warning('KIT INCOMPLETO: no se descargó ni se envió el kit de bienvenida.', [
                'code' => $record->code,
                'option' => $option,
                'missing' => $kit->missingLabels(),
            ]);

            return $option === 'DESCARGAR' ? null : false;
        }

        try {

            /**
             * DESCARGAR KIT BIENVENIDA
             *
             * @version 3.0
             */
            if ($option === 'DESCARGAR') {

                $zipFileName = 'Kit_Bienvenida_'.time().'.zip';
                $directory = storage_path('app/public/kit-temp/');

                if (! is_dir($directory)) {
                    mkdir($directory, 0755, true);
                }

                $tempZipPath = $directory.$zipFileName;

                $zip = new ZipArchive;

                if ($zip->open($tempZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                    throw new \Exception('No se pudo crear el archivo ZIP temporal del kit.');
                }

                foreach ($kit->paths() as $file) {
                    $zip->addFile($file, basename($file));
                }

                $zip->close();

                Log::info('DESCARGA COMPLETADA: Kit generado correctamente.', [
                    'code' => $record->code,
                    'user' => $record->full_name_payer,
                    'files' => $kit->filenames(),
                ]);

                return $tempZipPath;
            }

            /**
             * REENVIAR KIT BIENVENIDA
             *
             * Se envía en el acto (no en cola) para que el analista sepa de verdad si salió:
             * encolado, un adjunto ausente moría en el worker y la UI ya había dicho «enviado».
             *
             * @version 3.0
             */
            if ($option === 'REENVIAR') {

                $code = [
                    'code' => $record->code,
                ];

                Mail::to($data['email'])
                    ->cc('afiliaciones@tudrencasa.com')
                    ->sendNow(new SendMailKitBienvenida(
                        $code,
                        basename((string) $kit->condicionadoPath),
                        $kit->paths(),
                    ));

                Log::info('ENVIO COMPLETADO: Kit enviado correctamente.', [
                    'code' => $record->code,
                    'to' => $data['email'],
                    'user' => $record->full_name_payer,
                    'files' => $kit->filenames(),
                ]);

                Notification::make()
                    ->title('¡TAREA COMPLETADA!')
                    ->body('✅ Kit enviado a '.$data['email'].'.')
                    ->success()
                    ->send();

                return true;
            }

            return false;
        } catch (\Throwable $th) {

            Log::error('FALLA DE ENVIO: No se pudo enviar el kit.', [
                'code' => $record->code,
                'to' => $data['email'] ?? null,
                'user' => $record->full_name_payer,
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);

            Notification::make()
                ->title($option === 'DESCARGAR' ? 'ERROR EN LA DESCARGA DEL KIT' : 'ERROR EN EL ENVIO DEL KIT')
                ->body($th->getMessage())
                ->icon('heroicon-s-x-circle')
                ->iconColor('danger')
                ->danger()
                ->persistent()
                ->send();

            return $option === 'DESCARGAR' ? null : false;
        }
    }

    /**
     * Variables listas para la vista `documents.certificate` (sin lógica pesada en Blade).
     *
     * @param  array<string, mixed>  $pagador
     * @param  list<string>  $beneficios_table
     * @param  iterable<mixed>  $afiliates
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, mixed>  $pagador
     * @param  list<string>  $beneficios_table
     * @param  iterable<mixed>  $afiliates
     * @param  list<array{plan_id: int|null, plan_label: string, rows: list<array{text: string, show_cobertura: bool}>, note: string|null}>|null  $benefitSections
     *                                                                                                                                                              Secciones de beneficios ya resueltas (corporativas con varios planes). Si es null se
     *                                                                                                                                                              arma una sola sección con `$beneficios_table`, que es el caso del certificado individual.
     * @return array<string, mixed>
     */
    public static function dataForCertificatePdfView(
        array $pagador,
        array $beneficios_table,
        iterable $afiliates,
        ?WhiteCompanyDocumentBrand $brand = null,
        ?array $benefitSections = null,
        bool $showPlanColumn = false,
    ): array {
        $pagador['periodo_facturado_hasta'] = self::certificatePeriodoFacturadoHasta($pagador);
        $brand ??= WhiteCompanyDocumentBrand::tdec();
        $planId = isset($pagador['plan_id']) ? (int) $pagador['plan_id'] : null;
        $pagador['plan'] = $brand->planDisplayName($planId, (string) ($pagador['plan'] ?? ''));

        $cobertura = (float) ($pagador['cobertura'] ?? 0);
        $hasCoverageAmount = $cobertura > 0;

        $benefitSections ??= [[
            'plan_id' => $planId,
            'plan_label' => '',
            'rows' => self::certificateBeneficiosRows($beneficios_table, $hasCoverageAmount),
            'note' => self::planRequiresPreexistenceNote($planId)
                ? CorporateCertificateBenefitSections::PREEXISTENCE_NOTE
                : null,
        ]];

        return [
            'pagador' => $pagador,
            'affiliateTableRows' => self::certificateAffiliateTableRows($afiliates),
            'coberturaFormatted' => number_format($cobertura, 2, ',', '.'),
            'beneficiosRows' => $benefitSections[0]['rows'] ?? [],
            'benefitSections' => array_values($benefitSections),
            'showPlanColumn' => $showPlanColumn,
            'brandColor' => $brand->primaryColor,
            'logoDataUri' => $brand->logoDataUri(),
            'signatureDataUri' => $brand->signatureDataUri(),
            'isAlliedCertificate' => $brand->isAllied(),
            'companyName' => $brand->companyName(),
        ];
    }

    /**
     * La nota de preexistencias la declara el plan (`plans.requires_preexistence_note`).
     * Antes se decidía con el número mágico `plan_id == 3`, que dejaba fuera a los planes
     * equivalentes del catálogo nuevo; ese id queda solo como respaldo.
     */
    private static function planRequiresPreexistenceNote(?int $planId): bool
    {
        if ($planId === null) {
            return false;
        }

        $flag = Plan::query()->whereKey($planId)->value('requires_preexistence_note');

        if ($flag === null) {
            return $planId === 3;
        }

        return (bool) $flag;
    }

    /**
     * @param  array<string, mixed>  $pagador
     */
    private static function certificatePeriodoFacturadoHasta(array $pagador): string
    {
        $desde = $pagador['fecha_vigencia'] ?? '';
        if ($desde === '') {
            return '';
        }

        try {
            $fecha = Carbon::createFromFormat('d/m/Y', $desde);
        } catch (Throwable) {
            return '';
        }

        return match ($pagador['frecuencia_pago'] ?? '') {
            'MENSUAL' => $fecha->copy()->addMonths(1)->format('d/m/Y'),
            'TRIMESTRAL' => $fecha->copy()->addMonths(3)->format('d/m/Y'),
            'SEMESTRAL' => $fecha->copy()->addMonths(6)->format('d/m/Y'),
            'ANUAL' => $fecha->copy()->addYear()->format('d/m/Y'),
            default => '',
        };
    }

    /**
     * @param  iterable<mixed>  $afiliates
     * @return list<array{full_name: string, nro_identificacion: string, birth_date: mixed, relationship: string}>
     */
    private static function certificateAffiliateTableRows(iterable $afiliates): array
    {
        if ($afiliates instanceof \Illuminate\Support\Collection) {
            $collection = $afiliates;
        } elseif (is_array($afiliates)) {
            $collection = collect($afiliates);
        } else {
            $collection = collect([$afiliates]);
        }

        return $collection->map(function ($a): array {
            if (is_array($a)) {
                return [
                    'full_name' => (string) ($a['full_name'] ?? ''),
                    'nro_identificacion' => (string) ($a['nro_identificacion'] ?? ''),
                    'birth_date' => $a['birth_date'] ?? '',
                    'relationship' => (string) ($a['relationship'] ?? ''),
                    'plan_label' => (string) ($a['plan_label'] ?? ''),
                ];
            }

            return [
                'full_name' => (string) $a->full_name,
                'nro_identificacion' => (string) $a->nro_identificacion,
                'birth_date' => $a->birth_date,
                'relationship' => (string) $a->relationship,
                'plan_label' => '',
            ];
        })->all();
    }

    /**
     * @param  list<string>  $beneficios_table
     * @return list<array{text: string, show_cobertura: bool}>
     */
    private static function certificateBeneficiosRows(array $beneficios_table, bool $hasCoverageAmount = true): array
    {
        $conCobertura = [
            'EMERGENCIAS MÉDICAS POR PATOLOGIAS LISTADAS',
            'ASISTENCIA MÉDICA POR ACCIDENTES',
        ];

        $out = [];
        foreach ($beneficios_table as $fila) {
            $text = (string) $fila;
            $out[] = [
                'text' => $text,
                'show_cobertura' => $hasCoverageAmount && in_array(trim($text), $conCobertura, true),
            ];
        }

        return $out;
    }
}
