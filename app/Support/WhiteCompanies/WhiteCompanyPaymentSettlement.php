<?php

declare(strict_types=1);

namespace App\Support\WhiteCompanies;

use App\Models\Commission;
use App\Models\PaidMembership;
use App\Models\Sale;
use Illuminate\Support\Facades\Auth;

final readonly class WhiteCompanyPaymentSettlement
{
    public function __construct(
        public float $annualSalePrice,
        public float $annualNeta,
        public string $paymentFrequency,
        public int $whiteCompanyId,
        public ?int $feeId = null,
    ) {}

    /**
     * Suma precio de venta y neta de cada persona (cobertura + rango) y congela el total anual.
     *
     * @param  list<array{sale_price: float|int|string, neta: float|int|string, fee_id?: int|null}>  $lines
     */
    public static function fromPersonLines(array $lines, string $paymentFrequency, int $whiteCompanyId): self
    {
        $salePrice = 0.0;
        $neta = 0.0;
        $feeIds = [];

        foreach ($lines as $line) {
            $salePrice += (float) $line['sale_price'];
            $neta += (float) $line['neta'];

            if (isset($line['fee_id']) && $line['fee_id'] !== null) {
                $feeIds[] = (int) $line['fee_id'];
            }
        }

        $uniqueFeeIds = array_values(array_unique($feeIds));

        return new self(
            annualSalePrice: round($salePrice, 2),
            annualNeta: round($neta, 2),
            paymentFrequency: $paymentFrequency,
            whiteCompanyId: $whiteCompanyId,
            feeId: count($uniqueFeeIds) === 1 ? $uniqueFeeIds[0] : null,
        );
    }

    public static function periodsForFrequency(string $paymentFrequency): int
    {
        return match (mb_strtoupper(trim($paymentFrequency))) {
            'ANUAL' => 1,
            'SEMESTRAL' => 2,
            'TRIMESTRAL' => 4,
            'MENSUAL' => 12,
            default => 1,
        };
    }

    public function periods(): int
    {
        return self::periodsForFrequency($this->paymentFrequency);
    }

    public function installmentNeta(): float
    {
        return round($this->annualNeta / $this->periods(), 2);
    }

    public function installmentMasterCommission(): float
    {
        return round($this->annualMargin() / $this->periods(), 2);
    }

    public function annualMargin(): float
    {
        return round($this->annualSalePrice - $this->annualNeta, 2);
    }

    public function storeCommission(Sale $sale, PaidMembership $membership): Commission
    {
        $commission = new Commission;
        $commission->code = $sale->invoice_number;
        $commission->sale_id = $sale->id;
        $commission->plan_id = $membership->plan_id;
        $commission->coverage_id = $membership->coverage_id;
        $commission->agent_id = $membership->agent_id;
        $commission->code_agency = $membership->code_agency;
        $commission->payment_frequency = $membership->payment_frequency;
        $commission->affiliate_full_name = $sale->affiliate_full_name;
        $commission->pay_amount_usd = $membership->pay_amount_usd;
        $commission->pay_amount_ves = $membership->pay_amount_ves;
        $commission->amount = $this->installmentNeta();
        $commission->commission_agent_usd = 0;
        $commission->commission_agent_ves = 0;
        $commission->porcent_agente = 0;
        $commission->porcent_sub_agente = 0;
        $commission->commission_sub_agent_usd = 0;
        $commission->commission_sub_agent_ves = 0;
        $commission->porcent_agency_general = 0;
        $commission->commission_agency_general_usd = 0;
        $commission->commission_agency_general_ves = 0;
        $commission->porcent_agency_master = 0;
        $commission->commission_agency_master_usd = $this->installmentMasterCommission();
        $commission->commission_agency_master_ves = 0;
        $commission->payment_method = $sale->payment_method;
        $commission->affiliation_code = $sale->affiliation_code;
        $commission->created_by = Auth::user()?->name;
        $commission->save();

        return $commission;
    }
}
