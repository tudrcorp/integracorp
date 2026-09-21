<?php

declare(strict_types=1);

namespace App\Support\Filament;

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
}
