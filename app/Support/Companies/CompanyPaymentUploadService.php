<?php

declare(strict_types=1);

namespace App\Support\Companies;

use App\Models\Company;
use App\Support\SecurityAudit;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Throwable;

final class CompanyPaymentUploadService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function upload(Company $company, array $data, string $typeRoll): bool
    {
        try {
            $paymentFrequency = 'ANUAL';
            $paymentMethod = (string) ($data['payment_method'] ?? '');
            $attributes = self::sharedAttributes($company, $data, $typeRoll, $paymentFrequency);

            if (in_array($paymentMethod, ['EFECTIVO US$', 'ZELLE', 'TRANSFERENCIA US$', 'LINK DE PAGO'], true)) {
                $company->paidMemberships()->create(array_merge($attributes, [
                    'name_ti_usd' => array_key_exists('name_ti_usd', $data) ? $data['name_ti_usd'] : 'N/A',
                    'pay_amount_usd' => $data['total_amount'],
                    'pay_amount_ves' => $data['pay_amount_ves'] ?? 0.00,
                    'document_usd' => $data['document_usd'],
                    'document_ves' => $data['document_ves'] ?? 'N/A',
                    'payment_method_usd' => 'N/A',
                    'payment_method_ves' => 'N/A',
                    'reference_payment_usd' => $data['reference_payment_usd'] ?? 'N/A',
                    'reference_payment_ves' => $data['reference_payment_ves'] ?? 'N/A',
                    'bank_usd' => $data['bank_usd'] ?? 'N/A',
                    'bank_ves' => $data['bank_ves'] ?? 'N/A',
                ]));
            }

            if (in_array($paymentMethod, ['PAGO MOVIL VES', 'TRANSFERENCIA VES'], true)) {
                $company->paidMemberships()->create(array_merge($attributes, [
                    'tasa_bcv' => $data['tasa_bcv'],
                    'pay_amount_usd' => $data['pay_amount_usd'] ?? 0.00,
                    'pay_amount_ves' => $data['pay_amount_ves'],
                    'document_ves' => $data['document_ves'],
                    'document_usd' => $data['document_usd'] ?? 'N/A',
                    'payment_method_usd' => 'N/A',
                    'payment_method_ves' => 'N/A',
                    'reference_payment_ves' => $data['reference_payment_ves'],
                    'reference_payment_usd' => $data['reference_payment_usd'] ?? 'N/A',
                    'bank_usd' => $data['bank_usd'] ?? 'N/A',
                    'bank_ves' => $data['bank_ves'],
                ]));
            }

            if ($paymentMethod === 'MULTIPLE') {
                $company->paidMemberships()->create(array_merge($attributes, [
                    'name_ti_usd' => array_key_exists('name_ti_usd', $data) ? $data['name_ti_usd'] : 'N/A',
                    'tasa_bcv' => $data['tasa_bcv'],
                    'pay_amount_usd' => $data['pay_amount_usd'],
                    'pay_amount_ves' => $data['pay_amount_ves'],
                    'document_usd' => $data['document_usd'],
                    'document_ves' => $data['document_ves'],
                    'payment_method_usd' => $data['payment_method_usd'],
                    'payment_method_ves' => $data['payment_method_ves'],
                    'reference_payment_usd' => array_key_exists('reference_payment_usd', $data) ? $data['reference_payment_usd'] : 'N/A',
                    'reference_payment_ves' => $data['reference_payment_ves'],
                    'bank_usd' => $data['bank_usd'] ?? 'N/A',
                    'bank_ves' => $data['bank_ves'] ?? 'N/A',
                ]));
            }

            SecurityAudit::log('AUDIT_BUSINESS_COMPANY_PAYMENT_VOUCHER_UPLOADED', 'business.companies.upload-payment', [
                'company_id' => $company->getKey(),
                'company_name' => $company->name,
                'plan_generator_id' => $company->plan_generator_id,
                'payment_method' => $paymentMethod,
                'type_roll' => $typeRoll,
                'latest_company_paid_membership_id' => $company->paidMemberships()->latest('id')->value('id'),
            ]);

            return true;
        } catch (Throwable $throwable) {
            SecurityAudit::log('AUDIT_BUSINESS_COMPANY_PAYMENT_VOUCHER_UPLOAD_FAILED', 'business.companies.upload-payment', [
                'company_id' => $company->getKey(),
                'company_name' => $company->name ?? null,
                'payment_method' => $data['payment_method'] ?? null,
                'error' => $throwable->getMessage(),
            ]);

            throw $throwable;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function sharedAttributes(Company $company, array $data, string $typeRoll, string $paymentFrequency): array
    {
        $paymentDate = now()->format('d-m-Y');
        $nextPaymentDate = Carbon::createFromFormat('d-m-Y', $paymentDate)->addYear()->format('d-m-Y');

        return [
            'company_id' => $company->getKey(),
            'plan_generator_id' => $company->plan_generator_id,
            'total_amount' => $data['total_amount'],
            'payment_method' => $data['payment_method'],
            'payment_frequency' => $paymentFrequency,
            'payment_date' => $paymentDate,
            'prox_payment_date' => $nextPaymentDate,
            'renewal_date' => $nextPaymentDate,
            'observations_payment' => $data['observations_payment'] === null ? 'N/A' : $data['observations_payment'],
            'created_by' => Auth::user()?->name,
            'type_roll' => $typeRoll,
            'date_payment_voucher' => $data['date_payment_voucher'],
        ];
    }
}
