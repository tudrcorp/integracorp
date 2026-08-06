<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Commission extends Model
{
    private const AGENCY_TYPE_MASTER = 1;

    private const AGENCY_TYPE_GENERAL = 3;

    private const CASA_MATRIZ_CODE = 'TDG-100';

    private const CASA_MATRIZ_DISPLAY_NAME = 'TUDRENCASA';

    protected $table = 'commissions';

    protected $fillable = [
        'code',
        'code_agency',
        'agent_id',
        'plan_id',
        'coverage_id',
        'sale_id',
        'affiliate_full_name',
        'amount',
        'veto',
        'payment_frequency',
        'created_by',
        'pay_amount_usd',
        'pay_amount_ves',
        'affiliation_code',
        'commission_agency_master_usd',
        'commission_agency_master_ves',
        'commission_agency_general_usd',
        'porcent_agency_general',
        'commission_agency_general_ves',
        'porcent_agente',
        'commission_agent_usd',
        'commission_agent_ves',
        'porcent_agency_master',
        'payment_method',

        // Comisiones del sub-agente
        'porcent_sub_agente',
        'commission_sub_agent_usd',
        'commission_sub_agent_ves',

    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function coverage(): BelongsTo
    {
        return $this->belongsTo(Coverage::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'code_agency', 'code');
    }

    public function ownerNameAgency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'owner_code', 'code');
    }

    public function generalNameAgency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'code_agency', 'code');
    }

    /**
     * Nombre de la agencia master en la jerarquía del pago, o "-" si no aplica.
     */
    public function masterAgencyDisplayName(): string
    {
        $agency = $this->agency;

        if (! $agency instanceof Agency) {
            $codeAgency = strtoupper(trim((string) ($this->code_agency ?? '')));

            return $codeAgency === self::CASA_MATRIZ_CODE
                ? self::CASA_MATRIZ_DISPLAY_NAME
                : '-';
        }

        $agencyTypeId = (int) ($agency->agency_type_id ?? 0);

        if ($agencyTypeId === self::AGENCY_TYPE_MASTER) {
            return $this->formatAgencyDisplayName($agency);
        }

        if ($agencyTypeId === self::AGENCY_TYPE_GENERAL) {
            $masterAgency = $agency->relationLoaded('masterAgency')
                ? $agency->getRelation('masterAgency')
                : $agency->masterAgency()->where('agency_type_id', self::AGENCY_TYPE_MASTER)->first();

            if ($masterAgency instanceof Agency && (int) ($masterAgency->agency_type_id ?? 0) === self::AGENCY_TYPE_MASTER) {
                return $this->formatAgencyDisplayName($masterAgency);
            }

            return '-';
        }

        return '-';
    }

    /**
     * Nombre de la agencia general en la jerarquía del pago, o "-" si no aplica.
     */
    public function generalAgencyDisplayName(): string
    {
        $agency = $this->agency;

        if (! $agency instanceof Agency) {
            return '-';
        }

        if ((int) ($agency->agency_type_id ?? 0) !== self::AGENCY_TYPE_GENERAL) {
            return '-';
        }

        $name = trim((string) ($agency->name_corporative ?? ''));

        return $name !== '' ? $name : '-';
    }

    private function formatAgencyDisplayName(Agency $agency): string
    {
        $agencyCode = strtoupper(trim((string) ($agency->code ?? '')));

        if ($agencyCode === self::CASA_MATRIZ_CODE) {
            return self::CASA_MATRIZ_DISPLAY_NAME;
        }

        $name = trim((string) ($agency->name_corporative ?? ''));

        return $name !== '' ? $name : '-';
    }
}
