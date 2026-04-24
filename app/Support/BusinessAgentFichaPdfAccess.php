<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Agent;
use App\Support\AgentActivity\AgentActivityQuery;
use Illuminate\Support\Facades\Auth;

final class BusinessAgentFichaPdfAccess
{
    public static function userCanAccess(Agent $agent): bool
    {
        $user = Auth::user();
        if ($user === null) {
            return false;
        }

        $query = Agent::query()->whereKey($agent->getKey());

        if (! empty($user->is_accountManagers)) {
            $query->where('ownerAccountManagers', $user->id);
        }

        AgentActivityQuery::applyToAgentsQuery($query);

        return $query->exists();
    }
}
