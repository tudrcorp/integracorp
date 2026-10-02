<?php

declare(strict_types=1);

namespace App\Support\Filament;

use App\Models\Agency;
use App\Models\Agent;
use App\Models\Permission;
use App\Models\User;
use Filament\Schemas\Components\Utilities\Get;

final class CommercialNetworkAccess
{
    /**
     * @var list<string>
     */
    public const AGENCY_TYPES = ['MASTER', 'GENERAL'];

    public static function isCommercialNetworkUser(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ((bool) $user->is_agent) {
            return true;
        }

        return (bool) $user->is_agency
            && in_array((string) $user->agency_type, self::AGENCY_TYPES, true);
    }

    public static function formUserIsCommercialNetwork(Get $get, ?User $record): bool
    {
        $isAgent = (bool) ($get('is_agent') ?? $record?->is_agent);
        $isAgency = (bool) ($get('is_agency') ?? $record?->is_agency);
        $agencyType = (string) ($get('agency_type') ?? $record?->agency_type ?? '');

        if ($isAgent) {
            return true;
        }

        if (! $isAgency) {
            return false;
        }

        return $agencyType === '' || in_array($agencyType, self::AGENCY_TYPES, true);
    }

    public static function canViewPatients(?User $user): bool
    {
        return self::hasAssignedSlug($user, CommercialNetworkPermissionRegistry::PATIENTS);
    }

    public static function canViewCases(?User $user): bool
    {
        return self::hasAssignedSlug($user, CommercialNetworkPermissionRegistry::CASES);
    }

    public static function canViewPatientsOrCases(?User $user): bool
    {
        return self::canViewPatients($user) || self::canViewCases($user);
    }

    public static function hasAssignedSlug(?User $user, string $slug): bool
    {
        if (! self::isCommercialNetworkUser($user)) {
            return false;
        }

        return self::userHasModuleSlug($user, CommercialNetworkPermissionRegistry::MODULE, $slug);
    }

    public static function userHasModuleSlug(User $user, string $module, string $slug): bool
    {
        $module = strtoupper($module);

        if ($user->relationLoaded('permissions')) {
            return $user->permissions->contains(
                fn (Permission $permission): bool => strtoupper((string) $permission->module) === $module
                    && $permission->slug === $slug
            );
        }

        return $user->permissions()
            ->where('permissions.module', $module)
            ->where('permissions.slug', $slug)
            ->exists();
    }

    /**
     * ID de agencia vinculada al usuario por {@see User::$code_agency}, o null si no existe fila.
     */
    public static function agencyIdForUser(?User $user): ?int
    {
        if ($user === null || ! filled($user->code_agency)) {
            return null;
        }

        $agencyId = Agency::query()
            ->where('code', $user->code_agency)
            ->value('id');

        return $agencyId === null ? null : (int) $agencyId;
    }

    /**
     * Tipo de gráfico del panel comercial (bar|line). Si falta agente/agencia, usa barras.
     */
    public static function chartTypeForUser(?User $user): string
    {
        $default = 'bar';

        if ($user === null) {
            return $default;
        }

        if ((bool) $user->is_agent && $user->agent_id !== null) {
            $type = Agent::query()->whereKey($user->agent_id)->value('type_chart');

            return filled($type) ? (string) $type : $default;
        }

        if ((bool) $user->is_agency && filled($user->code_agency)) {
            $type = Agency::query()->where('code', $user->code_agency)->value('type_chart');

            return filled($type) ? (string) $type : $default;
        }

        return $default;
    }

    /**
     * Menú superior vs lateral según conf_position_menu del agente o agencia del usuario.
     * Si falta el registro comercial, usa menú superior (true) para no bloquear el panel.
     */
    public static function prefersTopNavigation(?User $user): bool
    {
        if ($user === null) {
            return true;
        }

        if ((bool) $user->is_agent && $user->agent_id !== null) {
            $preference = Agent::query()->whereKey($user->agent_id)->value('conf_position_menu');

            return $preference === null ? true : (bool) $preference;
        }

        if ((bool) $user->is_agency && filled($user->code_agency)) {
            $preference = Agency::query()->where('code', $user->code_agency)->value('conf_position_menu');

            return $preference === null ? true : (bool) $preference;
        }

        return true;
    }
}
