<?php

declare(strict_types=1);

namespace App\Support\PaidMemberships;

use App\Exceptions\AgencyNotFoundForCommissionException;
use App\Models\Agency;

final class AgencyTypeForCommission
{
    /**
     * Resuelve la agencia para cálculo de comisiones o lanza un error accionable.
     */
    public static function resolveOrFail(
        string $codeAgency,
        ?string $affiliationCode,
        int|string|null $paidMembershipId,
    ): Agency {
        $agency = Agency::query()
            ->select(['code', 'agency_type_id'])
            ->where('code', $codeAgency)
            ->first();

        if ($agency === null) {
            throw AgencyNotFoundForCommissionException::make(
                $codeAgency,
                $affiliationCode,
                $paidMembershipId,
            );
        }

        return $agency;
    }
}
