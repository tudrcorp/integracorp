<?php

declare(strict_types=1);

namespace App\Support\Companies;

use App\Http\Controllers\UtilsController;
use App\Models\Commission;
use App\Models\Company;
use App\Models\CompanyPaidMembership;
use App\Models\CompanyResponsible;
use App\Models\Sale;
use App\Support\SecurityAudit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class CompanyPaidMembershipApprovalService
{
    public const SALE_TYPE = 'NUEVOS NEGOCIOS';

    public const STATUS_PENDING = 'PENDIENTE';

    public const STATUS_APPROVED = 'APROBADO';

    public const STATUS_REJECTED = 'RECHAZADO';

    private const CASA_MATRIZ_CODE = 'TDG-100';

    /**
     * Aprueba un comprobante de nuevos negocios: crea Sale tipada, enlaza factura y comisión base.
     *
     * @return array{sale: Sale, commission: Commission|null}
     */
    public static function approve(CompanyPaidMembership $record): array
    {
        if ($record->status === self::STATUS_APPROVED) {
            throw new InvalidArgumentException('El comprobante ya fue aprobado.');
        }

        if ($record->status === self::STATUS_REJECTED) {
            throw new InvalidArgumentException('El comprobante está rechazado y no puede aprobarse.');
        }

        $record->loadMissing(['company.responsibles', 'company.associates']);

        $company = $record->company;

        if (! $company instanceof Company) {
            throw new RuntimeException('El comprobante no tiene empresa asociada.');
        }

        try {
            return DB::transaction(function () use ($record, $company): array {
                $locked = CompanyPaidMembership::query()
                    ->whereKey($record->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($locked->status === self::STATUS_APPROVED) {
                    throw new InvalidArgumentException('El comprobante ya fue aprobado.');
                }

                $sale = self::createSale($locked, $company);
                $commission = self::createCommissionPlaceholder($sale, $locked, $company);

                $locked->invoice_number = $sale->invoice_number;
                $locked->status = self::STATUS_APPROVED;
                $locked->aproved_by = Auth::user()?->name ?? 'system';
                $locked->save();

                SecurityAudit::log('AUDIT_COMPANY_PAYMENT_APPROVED', 'company-paid-membership.approve', [
                    'company_paid_membership_id' => $locked->id,
                    'company_id' => $company->id,
                    'sale_id' => $sale->id,
                    'invoice_number' => $sale->invoice_number,
                    'type' => self::SALE_TYPE,
                    'approved_by' => $locked->aproved_by,
                ]);

                return [
                    'sale' => $sale,
                    'commission' => $commission,
                ];
            });
        } catch (Throwable $throwable) {
            SecurityAudit::log('AUDIT_COMPANY_PAYMENT_APPROVE_FAILED', 'company-paid-membership.approve', [
                'company_paid_membership_id' => $record->id,
                'company_id' => $company->id,
                'error' => $throwable->getMessage(),
            ]);

            throw $throwable;
        }
    }

    public static function reject(CompanyPaidMembership $record, ?string $reason = null): CompanyPaidMembership
    {
        if ($record->status === self::STATUS_APPROVED) {
            throw new InvalidArgumentException('No se puede rechazar un comprobante ya aprobado.');
        }

        if ($record->status === self::STATUS_REJECTED) {
            throw new InvalidArgumentException('El comprobante ya fue rechazado.');
        }

        return DB::transaction(function () use ($record, $reason): CompanyPaidMembership {
            $locked = CompanyPaidMembership::query()
                ->whereKey($record->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === self::STATUS_APPROVED) {
                throw new InvalidArgumentException('No se puede rechazar un comprobante ya aprobado.');
            }

            $locked->status = self::STATUS_REJECTED;
            $locked->aproved_by = Auth::user()?->name ?? 'system';

            if (filled($reason)) {
                $existing = trim((string) ($locked->observations_payment ?? ''));
                $note = 'Rechazo: '.$reason;
                $locked->observations_payment = $existing === '' || $existing === 'N/A'
                    ? $note
                    : $existing.' | '.$note;
            }

            $locked->save();

            SecurityAudit::log('AUDIT_COMPANY_PAYMENT_REJECTED', 'company-paid-membership.reject', [
                'company_paid_membership_id' => $locked->id,
                'company_id' => $locked->company_id,
                'rejected_by' => $locked->aproved_by,
                'reason' => $reason,
            ]);

            return $locked;
        });
    }

    public static function resolveReferencePayment(CompanyPaidMembership $record): ?string
    {
        if ($record->payment_method === 'MULTIPLE') {
            return $record->reference_payment_ves.'-'.$record->reference_payment_usd;
        }

        if ($record->reference_payment_ves !== null && $record->reference_payment_ves !== 'N/A') {
            return $record->reference_payment_ves;
        }

        if ($record->reference_payment_usd !== null && $record->reference_payment_usd !== 'N/A') {
            return $record->reference_payment_usd;
        }

        return null;
    }

    public static function affiliationCodeFor(Company $company): string
    {
        return 'NN-'.$company->getKey();
    }

    private static function createSale(CompanyPaidMembership $record, Company $company): Sale
    {
        $responsible = $company->responsibles->first();
        $lastInvoiceNumber = Sale::query()->lockForUpdate()->latest('id')->value('invoice_number');
        $invoiceNumber = UtilsController::generateCorrelativeSale($lastInvoiceNumber ?? '');

        $sale = new Sale;
        $sale->date_activation = now()->format('d/m/Y');
        $sale->owner_code = self::CASA_MATRIZ_CODE;
        $sale->code_agency = self::CASA_MATRIZ_CODE;
        $sale->agent_id = null;
        $sale->plan_id = null;
        $sale->coverage_id = null;
        $sale->invoice_number = $invoiceNumber;
        $sale->affiliation_code = self::affiliationCodeFor($company);
        $sale->company_id = $company->getKey();
        $sale->affiliate_full_name = $company->name;
        $sale->affiliate_contact = $responsible instanceof CompanyResponsible
            ? $responsible->full_name
            : $company->name;
        $sale->affiliate_ci_rif = $company->rif
            ?? ($responsible instanceof CompanyResponsible ? $responsible->identity_card : null);
        $sale->affiliate_phone = $company->phone
            ?? ($responsible instanceof CompanyResponsible ? $responsible->phone : null);
        $sale->affiliate_email = $company->email
            ?? ($responsible instanceof CompanyResponsible ? $responsible->email : null);
        $sale->service = 'servicio';
        $sale->persons = (string) max(1, $company->associates->count());
        $sale->total_amount = $record->total_amount;
        $sale->type = self::SALE_TYPE;
        $sale->payment_method = $record->payment_method;
        $sale->payment_frequency = $record->payment_frequency ?: 'ANUAL';
        $sale->created_by = Auth::user()?->name ?? 'system';
        $sale->pay_amount_usd = $record->pay_amount_usd;
        $sale->pay_amount_ves = $record->pay_amount_ves;
        $sale->payment_method_usd = $record->payment_method_usd;
        $sale->payment_method_ves = $record->payment_method_ves;
        $sale->bank_usd = $record->bank_usd;
        $sale->bank_ves = $record->bank_ves;
        $sale->type_roll = $record->type_roll;
        $sale->payment_date = $record->payment_date;
        $sale->reference_payment = self::resolveReferencePayment($record);
        $sale->observations = 'Origen: Nuevos Negocios · company_id='.$company->getKey();
        $sale->status_payment_commission = 'POR PAGAR';
        $sale->is_payment_link = $record->payment_method === 'LINK DE PAGO';
        $sale->save();

        SecurityAudit::log('AUDIT_SALE_REGISTERED', 'company-paid-membership.approve', [
            'flow' => 'nuevos-negocios',
            'sale_id' => $sale->id,
            'invoice_number' => $sale->invoice_number,
            'company_id' => $company->id,
            'affiliation_code' => $sale->affiliation_code,
            'type' => self::SALE_TYPE,
            'total_amount' => $sale->total_amount,
        ]);

        return $sale;
    }

    /**
     * Comisión placeholder (montos 0) hasta que Company tenga estructura comercial (agente/agencia).
     * Mantiene el vínculo sale ↔ commission visible para el analista.
     */
    private static function createCommissionPlaceholder(
        Sale $sale,
        CompanyPaidMembership $record,
        Company $company,
    ): Commission {
        $commission = new Commission;
        $commission->code = $sale->invoice_number;
        $commission->sale_id = $sale->id;
        $commission->plan_id = null;
        $commission->coverage_id = null;
        $commission->agent_id = null;
        $commission->code_agency = self::CASA_MATRIZ_CODE;
        $commission->payment_frequency = $sale->payment_frequency ?: 'ANUAL';
        $commission->affiliate_full_name = $company->name;
        $commission->pay_amount_usd = $record->pay_amount_usd;
        $commission->pay_amount_ves = $record->pay_amount_ves;
        $commission->amount = $record->total_amount;
        $commission->veto = $sale->payment_frequency ?: 'ANUAL';
        $commission->commission_agent_usd = 0;
        $commission->commission_agent_ves = 0;
        $commission->commission_agency_master_usd = 0;
        $commission->commission_agency_master_ves = 0;
        $commission->commission_agency_general_usd = 0;
        $commission->commission_agency_general_ves = 0;
        $commission->porcent_agente = 0;
        $commission->porcent_agency_master = 0;
        $commission->porcent_agency_general = 0;
        $commission->payment_method = $sale->payment_method;
        $commission->affiliation_code = $sale->affiliation_code;
        $commission->created_by = Auth::user()?->name ?? 'system';
        $commission->save();

        return $commission;
    }
}
