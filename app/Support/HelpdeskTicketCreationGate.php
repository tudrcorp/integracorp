<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\HelpdeskGroup;
use App\Models\RrhhColaborador;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

final class HelpdeskTicketCreationGate
{
    public const DEFAULT_GROUP_QUOTA = 5;

    public static function allowsCreation(
        ?Authenticatable $user = null,
        bool $enforceGroupQuota = false,
    ): HelpdeskBusinessTicketCreationVerdict {
        $user ??= auth()->user();

        if (! $user instanceof User) {
            return HelpdeskBusinessTicketCreationVerdict::denied(
                'Debe iniciar sesión para crear un ticket.',
                denialReason: HelpdeskBusinessTicketCreationDenialReason::UNAUTHENTICATED,
            );
        }

        if (HelpdeskUserAccess::hasSuperAdminDepartment($user)) {
            return HelpdeskBusinessTicketCreationVerdict::allowed(
                bypassReason: 'Departamento SUPERADMIN (sin restricción de grupo).',
            );
        }

        $colaborador = self::resolveColaboradorForUser($user);

        if ($colaborador === null) {
            return HelpdeskBusinessTicketCreationVerdict::denied(
                'Su usuario no está vinculado al directorio RRHH. Comuníquese con el Departamento de Tecnología.',
                denialReason: HelpdeskBusinessTicketCreationDenialReason::MISSING_RRHH,
            );
        }

        $group = self::findActiveGroupForColaborador((int) $colaborador->id);

        if ($group === null) {
            return HelpdeskBusinessTicketCreationVerdict::denied(
                'Comuníquese con el Departamento de Tecnología para ser incluido en un grupo de trabajo.',
                denialReason: HelpdeskBusinessTicketCreationDenialReason::MISSING_GROUP,
            );
        }

        if (! $enforceGroupQuota) {
            return HelpdeskBusinessTicketCreationVerdict::allowed(
                group: $group,
            );
        }

        if (HelpdeskUserAccess::hasSystemsDepartment($user)) {
            return HelpdeskBusinessTicketCreationVerdict::allowed(
                group: $group,
                bypassReason: 'Departamento de tecnología (cuota no aplica).',
            );
        }

        $quota = max(0, (int) $group->total_tickets_assigned);
        $used = $group->ticketsCreatedCount();

        if ($quota < 1) {
            return HelpdeskBusinessTicketCreationVerdict::denied(
                'Su grupo «'.$group->name.'» no tiene cuota de tickets asignada. Comuníquese con el Departamento de Tecnología.',
                $group,
                $used,
                $quota,
                HelpdeskBusinessTicketCreationDenialReason::QUOTA_NOT_SET,
            );
        }

        if ($used >= $quota) {
            return HelpdeskBusinessTicketCreationVerdict::denied(
                'El grupo «'.$group->name.'» alcanzó su cuota de '.$quota.' ticket(s) ('.$used.' registrados). Solo Tecnología puede ampliar la cuota en Grupos de trabajo.',
                $group,
                $used,
                $quota,
                HelpdeskBusinessTicketCreationDenialReason::QUOTA_EXHAUSTED,
            );
        }

        return HelpdeskBusinessTicketCreationVerdict::allowed(
            group: $group,
            used: $used,
            quota: $quota,
        );
    }

    public static function resolveColaboradorForUser(User $user): ?RrhhColaborador
    {
        return RrhhColaborador::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->first();
    }

    public static function findActiveGroupForColaborador(int $colaboradorId): ?HelpdeskGroup
    {
        return HelpdeskGroup::query()
            ->where('status', 'ACTIVO')
            ->latest()
            ->get()
            ->first(static function (HelpdeskGroup $group) use ($colaboradorId): bool {
                return in_array($colaboradorId, $group->memberColaboradorIds(), true);
            });
    }

    /**
     * @return list<string>
     */
    public static function creatorNamesForGroup(HelpdeskGroup $group): array
    {
        $colaboradorIds = $group->memberColaboradorIds();

        if ($colaboradorIds === []) {
            return [];
        }

        $userIds = RrhhColaborador::query()
            ->whereIn('id', $colaboradorIds)
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($userIds === []) {
            return [];
        }

        return User::query()
            ->whereIn('id', $userIds)
            ->pluck('name')
            ->map(static fn (mixed $name): string => trim((string) $name))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
