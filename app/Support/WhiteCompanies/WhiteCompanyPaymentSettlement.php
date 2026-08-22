<?php

declare(strict_types=1);

namespace App\Support\WhiteCompanies;

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

    public function installmentSalePrice(): float
    {
        return round($this->annualSalePrice / $this->periods(), 2);
    }

    public function installmentNeta(): float
    {
        return round($this->annualNeta / $this->periods(), 2);
    }

    public function installmentPartner(): float
    {
        return round(($this->annualSalePrice - $this->annualNeta) / $this->periods(), 2);
    }

    /**
     * @return array{sale_price: float, neta_tdg: float, neta_partner: float}
     */
    public function installmentReportAmounts(): array
    {
        return [
            'sale_price' => $this->installmentSalePrice(),
            'neta_tdg' => $this->installmentNeta(),
            'neta_partner' => $this->installmentPartner(),
        ];
    }

    public static function fromFrozenAffiliationRates(
        mixed $annualSalePrice,
        mixed $annualNeta,
        ?string $paymentFrequency,
        int $whiteCompanyId = 0,
    ): self {
        return new self(
            annualSalePrice: (float) $annualSalePrice,
            annualNeta: (float) $annualNeta,
            paymentFrequency: is_string($paymentFrequency) && trim($paymentFrequency) !== ''
                ? $paymentFrequency
                : 'ANUAL',
            whiteCompanyId: $whiteCompanyId,
        );
    }
}
