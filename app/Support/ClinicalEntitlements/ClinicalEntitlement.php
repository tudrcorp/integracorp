<?php

declare(strict_types=1);

namespace App\Support\ClinicalEntitlements;

use App\Enums\ClinicalQuotaScope;
use App\Enums\ClinicalServiceChannel;

final class ClinicalEntitlement
{
    public function __construct(
        public int $benefitId,
        public string $benefitLabel,
        public ClinicalServiceChannel $channel,
        public ?int $telemedicineServiceListId,
        public ?string $telemedicineServiceListName,
        public ClinicalQuotaScope $quotaScope,
        public ?int $quota,
        public int $used,
        public ?int $remaining,
        public bool $exhausted,
    ) {}

    public function helperText(): string
    {
        if ($this->quotaScope === ClinicalQuotaScope::Unlimited) {
            return 'Sin tope de usos en este plan.';
        }

        $unit = $this->quotaScope === ClinicalQuotaScope::DistinctCases ? 'casos' : 'usos';
        $restante = $this->remaining ?? 0;

        if ($this->exhausted) {
            return sprintf(
                'Límite cubierto: %d de %d %s (%s). Para asignar uno extra se requiere autorización OTP.',
                $this->used,
                (int) $this->quota,
                $unit,
                $this->quotaScope->label(),
            );
        }

        return sprintf(
            'Usados %d de %d %s (%s). Restan %d.',
            $this->used,
            (int) $this->quota,
            $unit,
            $this->quotaScope->label(),
            $restante,
        );
    }

    public function displayName(): string
    {
        $service = trim((string) ($this->telemedicineServiceListName ?? ''));
        if ($service !== '') {
            return $service;
        }

        return $this->benefitLabel;
    }

    public function operationsBalanceLabel(): string
    {
        if ($this->quotaScope === ClinicalQuotaScope::Unlimited) {
            return 'Ilimitado';
        }

        if ($this->exhausted) {
            return 'Agotado';
        }

        return 'Restan '.(int) ($this->remaining ?? 0);
    }

    public function operationsCountLabel(): string
    {
        if ($this->quotaScope === ClinicalQuotaScope::Unlimited) {
            return 'Sin tope';
        }

        $unit = $this->quotaScope === ClinicalQuotaScope::DistinctCases ? 'casos' : 'usos';

        return $this->used.' / '.(int) $this->quota.' '.$unit;
    }

    public function operationsTone(): string
    {
        if ($this->quotaScope === ClinicalQuotaScope::Unlimited) {
            return 'muted';
        }

        if ($this->exhausted) {
            return 'danger';
        }

        if (($this->remaining ?? 0) <= 1) {
            return 'warning';
        }

        return 'ok';
    }
}
