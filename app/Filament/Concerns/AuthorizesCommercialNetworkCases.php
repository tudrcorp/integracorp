<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Filament\Shared\CommercialTelemedicine\CommercialTelemedicinePanel;
use App\Support\Filament\CommercialNetworkAccess;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait AuthorizesCommercialNetworkCases
{
    public static function canAccess(): bool
    {
        return CommercialNetworkAccess::canViewPatientsOrCases(Auth::user());
    }

    public static function shouldRegisterNavigation(): bool
    {
        return CommercialNetworkAccess::canViewCases(Auth::user());
    }

    public static function canViewAny(): bool
    {
        return CommercialNetworkAccess::canViewCases(Auth::user());
    }

    public static function canView(Model $record): bool
    {
        return CommercialTelemedicinePanel::recordIsAccessibleCase($record);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}
