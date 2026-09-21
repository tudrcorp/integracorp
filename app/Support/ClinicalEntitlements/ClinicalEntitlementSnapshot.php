<?php

declare(strict_types=1);

namespace App\Support\ClinicalEntitlements;

use App\Enums\ClinicalServiceChannel;

final class ClinicalEntitlementSnapshot
{
    /**
     * @param  list<string>  $missingBenefitLabels
     * @param  list<ClinicalEntitlement>  $entitlements
     */
    public function __construct(
        public bool $hasPlan,
        public bool $isComplete,
        public array $missingBenefitLabels,
        public array $entitlements,
        public string $blockingMessage,
    ) {}

    /**
     * @return array<int, string>
     */
    public function type1Options(): array
    {
        $options = [];

        foreach ($this->entitlements as $entitlement) {
            if ($entitlement->channel !== ClinicalServiceChannel::Type1) {
                continue;
            }

            if ($entitlement->telemedicineServiceListId === null) {
                continue;
            }

            $options[$entitlement->telemedicineServiceListId] = $entitlement->telemedicineServiceListName
                ?? ('Servicio #'.$entitlement->telemedicineServiceListId);
        }

        return $options;
    }

    /**
     * @return array<int, string>
     */
    public function type1HelperById(): array
    {
        $helpers = [];

        foreach ($this->entitlements as $entitlement) {
            if ($entitlement->channel !== ClinicalServiceChannel::Type1 || $entitlement->telemedicineServiceListId === null) {
                continue;
            }

            $helpers[$entitlement->telemedicineServiceListId] = $entitlement->helperText();
        }

        return $helpers;
    }

    public function forType1(?int $serviceListId): ?ClinicalEntitlement
    {
        if ($serviceListId === null) {
            return null;
        }

        foreach ($this->entitlements as $entitlement) {
            if ($entitlement->channel === ClinicalServiceChannel::Type1
                && $entitlement->telemedicineServiceListId === $serviceListId) {
                return $entitlement;
            }
        }

        return null;
    }

    public function forChannel(ClinicalServiceChannel $channel): ?ClinicalEntitlement
    {
        foreach ($this->entitlements as $entitlement) {
            if ($entitlement->channel === $channel) {
                return $entitlement;
            }
        }

        return null;
    }

    public function forBenefit(int $benefitId): ?ClinicalEntitlement
    {
        foreach ($this->entitlements as $entitlement) {
            if ($entitlement->benefitId === $benefitId) {
                return $entitlement;
            }
        }

        return null;
    }

    public function channelIsAvailable(ClinicalServiceChannel $channel): bool
    {
        return $this->forChannel($channel) !== null;
    }
}
