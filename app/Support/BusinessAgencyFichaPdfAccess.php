<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Agency;
use Illuminate\Support\Facades\Auth;

final class BusinessAgencyFichaPdfAccess
{
    public static function userCanAccess(Agency $agency): bool
    {
        $user = Auth::user();
        if ($user === null) {
            return false;
        }

        $query = Agency::query()->whereKey($agency->getKey());

        if (! empty($user->is_accountManagers)) {
            $query->where('ownerAccountManagers', $user->id);
        }

        return $query->exists();
    }
}
