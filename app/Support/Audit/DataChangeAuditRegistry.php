<?php

declare(strict_types=1);

namespace App\Support\Audit;

use App\Models\Affiliate;
use App\Models\AffiliateCorporate;
use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\Collection;
use App\Models\Commission;
use App\Models\Company;
use App\Models\CompanyAssociate;
use App\Models\CompanyPaidMembership;
use App\Models\CorporateQuote;
use App\Models\Fee;
use App\Models\IndividualQuote;
use App\Models\OperationAccountsPayable;
use App\Models\OperationCashPayment;
use App\Models\Plan;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Qué se audita y cómo se lee: cada modelo vigilado, su nombre para el analista, el campo
 * que identifica al registro (código), su registro padre y los campos que afectan los
 * cálculos de Administración (ventas, cobranza, comisiones, capitado, renovaciones).
 */
final class DataChangeAuditRegistry
{
    /** Campos que nunca se auditan: marcas de tiempo y ruido. */
    public const IGNORED_FIELDS = ['updated_at', 'created_at', 'remember_token'];

    /** Campos cuyo valor nunca se guarda (se guarda «••••» en su lugar). */
    public const MASKED_FIELDS = ['password', 'registration_token', 'token', 'api_token', 'secret'];

    /** Largo máximo de un valor guardado: los textos largos se recortan con su huella. */
    public const MAX_VALUE_LENGTH = 1500;

    /**
     * Campos que afectan cálculos, comunes a casi todas las entidades.
     *
     * @var list<string>
     */
    private const COMMON_CRITICAL = [
        'owner_code', 'code_agency', 'agent_id', 'owner_agent', 'agency_id', 'plan_id', 'coverage_id',
        'fee', 'fee_anual', 'total_amount', 'amount', 'price', 'payment_frequency', 'status',
        'effective_date', 'activated_at', 'service_providers', 'white_company_id', 'deleted_at',
        'pay_amount_usd', 'pay_amount_ves', 'payment_method', 'payment_date', 'filter_next_payment_date',
        'next_payment_date', 'affiliation_code', 'type', 'business_unit_id', 'business_line_id',
    ];

    /**
     * @return array<class-string<Model>, array{key: string, label: string, code: list<string>, parent: ?array{relation: string, code: string}, critical: list<string>}>
     */
    public static function entities(): array
    {
        return [
            Affiliation::class => ['key' => 'affiliation', 'label' => 'Afiliación individual', 'code' => ['code'], 'parent' => null, 'critical' => [
                'subtotal_anual', 'white_company_sale_price', 'white_company_neta', 'white_company_fee_id', 'upgrade_billing_frequency', 'family_members', 'specific_business_unit',
            ]],
            Affiliate::class => ['key' => 'affiliate', 'label' => 'Afiliado individual', 'code' => ['full_name'], 'parent' => ['relation' => 'affiliation', 'code' => 'code'], 'critical' => [
                'age', 'age_range_id', 'birth_date', 'specific_business_unit',
            ]],
            AffiliationCorporate::class => ['key' => 'affiliation_corporate', 'label' => 'Afiliación corporativa', 'code' => ['code', 'name_corporate'], 'parent' => null, 'critical' => [
                'poblation', 'upgrade_billing_frequency', 'specific_business_unit',
            ]],
            AffiliateCorporate::class => ['key' => 'affiliate_corporate', 'label' => 'Afiliado corporativo', 'code' => ['first_name', 'last_name'], 'parent' => ['relation' => 'affiliationCorporate', 'code' => 'code'], 'critical' => [
                'subtotal_anual', 'subtotal_payment_frequency', 'subtotal_daily', 'age', 'birth_date', 'specific_business_unit',
            ]],
            IndividualQuote::class => ['key' => 'individual_quote', 'label' => 'Cotización individual', 'code' => ['code'], 'parent' => null, 'critical' => ['plan', 'assignment_status']],
            CorporateQuote::class => ['key' => 'corporate_quote', 'label' => 'Cotización corporativa', 'code' => ['code'], 'parent' => null, 'critical' => ['plan']],
            Agency::class => ['key' => 'agency', 'label' => 'Agencia', 'code' => ['code', 'name_corporative'], 'parent' => null, 'critical' => [
                'agency_type_id', 'commission_tdec', 'commission_tdec_renewal', 'commission_tdev', 'commission_tdev_renewal', 'tdec', 'tdev', 'referidor_id', 'referidor_agent_id',
            ]],
            Agent::class => ['key' => 'agent', 'label' => 'Agente', 'code' => ['code_agent', 'name'], 'parent' => null, 'critical' => [
                'agent_type_id', 'commission_tdec', 'commission_tdec_renewal', 'commission_tdev', 'commission_tdev_renewal', 'tdec', 'tdev', 'referidor_id', 'referidor_agent_id',
            ]],
            Sale::class => ['key' => 'sale', 'label' => 'Venta (pago registrado)', 'code' => ['invoice_number'], 'parent' => null, 'critical' => [
                'date_activation', 'status_payment_commission', 'persons', 'bank_usd', 'bank_ves', 'company_id', 'white_company_neta',
            ]],
            Collection::class => ['key' => 'collection', 'label' => 'Cuota de cobranza', 'code' => ['collection_invoice_number'], 'parent' => null, 'critical' => [
                'sale_id', 'include_date', 'expiration_date', 'days', 'billing_adjustment_id', 'follow_up_status',
            ]],
            Commission::class => ['key' => 'commission', 'label' => 'Comisión', 'code' => ['code'], 'parent' => null, 'critical' => [
                'sale_id', 'veto', 'commission_agency_master_usd', 'commission_agency_master_ves', 'commission_agency_general_usd', 'commission_agency_general_ves',
                'commission_agent_usd', 'commission_agent_ves', 'commission_sub_agent_usd', 'commission_sub_agent_ves', 'commission_referidor_usd', 'commission_referidor_ves',
                'porcent_agency_master', 'porcent_agency_general', 'porcent_agente', 'porcent_sub_agente', 'porcent_referidor',
            ]],
            Plan::class => ['key' => 'plan', 'label' => 'Plan', 'code' => ['code', 'description'], 'parent' => null, 'critical' => [
                'pricing_mode', 'is_quotable', 'quotable_in', 'structure_version', 'description',
            ]],
            Fee::class => ['key' => 'fee', 'label' => 'Tarifa', 'code' => ['code'], 'parent' => null, 'critical' => ['age_range_id', 'range', 'coverage']],
            Company::class => ['key' => 'company', 'label' => 'Empresa (Nuevos Negocios)', 'code' => ['name'], 'parent' => null, 'critical' => [
                'plan_generator_id', 'plan_generator_column_key',
            ]],
            CompanyAssociate::class => ['key' => 'company_associate', 'label' => 'Asociado (Nuevos Negocios)', 'code' => ['full_name'], 'parent' => ['relation' => 'company', 'code' => 'name'], 'critical' => [
                'company_id', 'registration_start_date', 'registration_end_date', 'annulled_at',
            ]],
            OperationAccountsPayable::class => ['key' => 'operation_accounts_payable', 'label' => 'Cuenta por pagar (proveedor)', 'code' => ['invoice_number', 'invoice_receipt_number', 'supplier_name'], 'parent' => null, 'critical' => [
                'invoice_amount', 'invoice_currency', 'invoice_date', 'payment_status', 'payment_reference', 'national_bank', 'international_bank', 'payment_receipt_path', 'supplier_id', 'invoice_receipt_number', 'invoice_number',
            ]],
            OperationCashPayment::class => ['key' => 'operation_cash_payment', 'label' => 'Pago de contado (proveedor)', 'code' => ['payment_reference', 'supplier_name'], 'parent' => null, 'critical' => [
                'payment_currency', 'payment_amount', 'bcv_rate', 'amount_in_invoice_currency', 'bank', 'receipt_path', 'payment_reference', 'reversed_at',
            ]],
            CompanyPaidMembership::class => ['key' => 'company_paid_membership', 'label' => 'Pago de Nuevos Negocios', 'code' => ['invoice_number'], 'parent' => ['relation' => 'company', 'code' => 'name'], 'critical' => [
                'company_id', 'plan_generator_id', 'tasa_bcv', 'prox_payment_date', 'renewal_date', 'aproved_by',
            ]],
        ];
    }

    /**
     * @return array{key: string, label: string, code: list<string>, parent: ?array{relation: string, code: string}, critical: list<string>}|null
     */
    public static function for(Model $model): ?array
    {
        return self::entities()[$model::class] ?? null;
    }

    /**
     * @return class-string<Model>|null
     */
    public static function classFor(string $key): ?string
    {
        foreach (self::entities() as $class => $definition) {
            if ($definition['key'] === $key) {
                return $class;
            }
        }

        return null;
    }

    /**
     * @return array<string, string> clave => nombre legible
     */
    public static function entityOptions(): array
    {
        $options = [];

        foreach (self::entities() as $definition) {
            $options[$definition['key']] = $definition['label'];
        }

        return $options;
    }

    public static function entityLabel(string $key): string
    {
        return self::entityOptions()[$key] ?? Str::headline($key);
    }

    public static function isCritical(string $entityKey, string $field): bool
    {
        $class = self::classFor($entityKey);

        return in_array($field, self::COMMON_CRITICAL, true)
            || ($class !== null && in_array($field, self::entities()[$class]['critical'], true));
    }

    /**
     * Nombre legible de un campo para el analista.
     */
    public static function fieldLabel(string $field): string
    {
        return self::FIELD_LABELS[$field] ?? Str::ucfirst(str_replace('_', ' ', $field));
    }

    private const FIELD_LABELS = [
        'invoice_receipt_number' => 'Número de recibo',
        'payment_status' => 'Estatus del pago',
        'payment_reference' => 'Referencia del pago',
        'national_bank' => 'Banco nacional',
        'international_bank' => 'Banco internacional',
        'payment_receipt_path' => 'Comprobante del pago',
        'payment_currency' => 'Moneda del pago',
        'payment_amount' => 'Monto del pago',
        'bcv_rate' => 'Tasa BCV',
        'amount_in_invoice_currency' => 'Monto en la moneda de la factura',
        'bank' => 'Banco',
        'receipt_path' => 'Comprobante',
        'reversed_at' => 'Reversado el',
        'reversal_reason' => 'Razón de la reversión',
        'invoice_amount' => 'Monto de la factura',
        'invoice_currency' => 'Moneda de la factura',
        'owner_code' => 'Agencia dueña (master)',
        'code_agency' => 'Agencia',
        'agent_id' => 'Agente',
        'owner_agent' => 'Agente responsable',
        'agency_id' => 'Agencia del agente',
        'plan_id' => 'Plan',
        'coverage_id' => 'Cobertura',
        'fee' => 'Tarifa',
        'fee_anual' => 'Tarifa anual',
        'total_amount' => 'Monto total',
        'amount' => 'Monto',
        'price' => 'Precio',
        'payment_frequency' => 'Frecuencia de pago',
        'status' => 'Estatus',
        'effective_date' => 'Vigencia',
        'activated_at' => 'Fecha de activación',
        'service_providers' => 'Proveedores de servicio',
        'white_company_id' => 'Empresa aliada',
        'deleted_at' => 'Eliminado',
        'pay_amount_usd' => 'Pagado en USD',
        'pay_amount_ves' => 'Pagado en Bs.',
        'payment_method' => 'Método de pago',
        'payment_date' => 'Fecha de pago',
        'filter_next_payment_date' => 'Vencimiento',
        'next_payment_date' => 'Próximo pago',
        'affiliation_code' => 'Código de afiliación',
        'type' => 'Tipo',
        'code' => 'Código',
        'full_name' => 'Nombre completo',
        'first_name' => 'Nombres',
        'last_name' => 'Apellidos',
        'nro_identificacion' => 'Cédula',
        'birth_date' => 'Fecha de nacimiento',
        'name_corporate' => 'Razón social',
        'name_corporative' => 'Razón social',
        'name' => 'Nombre',
        'rif' => 'RIF',
        'agency_type_id' => 'Tipo de agencia',
        'agent_type_id' => 'Tipo de agente',
        'commission_tdec' => 'Comisión TDEC',
        'commission_tdec_renewal' => 'Comisión TDEC renovación',
        'commission_tdev' => 'Comisión TDEV',
        'commission_tdev_renewal' => 'Comisión TDEV renovación',
        'invoice_number' => 'Número de factura',
        'collection_invoice_number' => 'Aviso de cobro',
        'sale_id' => 'Venta',
        'poblation' => 'Población',
        'description' => 'Descripción',
        'business_unit_id' => 'Unidad de negocio',
        'business_line_id' => 'Línea de negocio',
        'specific_business_unit' => 'Unidad de negocio específica',
        'company_id' => 'Empresa',
        'relationship' => 'Parentesco',
        'veto' => 'Veto',
    ];
}
