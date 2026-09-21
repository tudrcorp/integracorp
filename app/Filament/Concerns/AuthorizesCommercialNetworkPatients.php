<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Filament\Shared\CommercialTelemedicine\CommercialTelemedicinePanel;
use App\Support\Filament\CommercialNetworkAccess;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait AuthorizesCommercialNetworkPatients
{
    public static function canAccess(): bool
    {
        return CommercialNetworkAccess::canViewPatients(Auth::user());
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canViewAny(): bool
    {
        return static::canAccess();
    }

    public static function canView(Model $record): bool
    {
        return CommercialTelemedicinePanel::recordIsAccessiblePatient($record);
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
