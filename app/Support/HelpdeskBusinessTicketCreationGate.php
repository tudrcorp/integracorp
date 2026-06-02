<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * @deprecated Use HelpdeskTicketCreationGate directly. Mantenido por compatibilidad en Business (cuota de grupo).
 */
final class HelpdeskBusinessTicketCreationGate
{
    public const DEFAULT_GROUP_QUOTA = HelpdeskTicketCreationGate::DEFAULT_GROUP_QUOTA;

    public static function allowsCreation(?Authenticatable $user = null): HelpdeskBusinessTicketCreationVerdict
    {
        return HelpdeskTicketCreationGate::allowsCreation($user, enforceGroupQuota: true);
    }

    public static function resolveColaboradorForUser(\App\Models\User $user): ?\App\Models\RrhhColaborador
    {
        return HelpdeskTicketCreationGate::resolveColaboradorForUser($user);
    }

    public static function findActiveGroupForColaborador(int $colaboradorId): ?\App\Models\HelpdeskGroup
    {
        return HelpdeskTicketCreationGate::findActiveGroupForColaborador($colaboradorId);
    }

    /**
     * @return list<string>
     */
    public static function creatorNamesForGroup(\App\Models\HelpdeskGroup $group): array
    {
        return HelpdeskTicketCreationGate::creatorNamesForGroup($group);
    }
}
