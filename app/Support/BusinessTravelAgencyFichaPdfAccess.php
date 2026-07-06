<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\TravelAgency;
use Illuminate\Support\Facades\Auth;

final class BusinessTravelAgencyFichaPdfAccess
{
    public static function userCanAccess(TravelAgency $travelAgency): bool
    {
        $user = Auth::user();
        if ($user === null) {
            return false;
        }

        $query = TravelAgency::query()->whereKey($travelAgency->getKey());

        if (! empty($user->is_accountManagers)) {
            $query->where('ownerAccountManagers', $user->id);
        }

        return $query->exists();
    }
}
