<?php

namespace App\Filament\Operations\Resources\Helpdesks\Widgets;

use App\Filament\Business\Resources\Helpdesks\Widgets\HelpdeskStatusWeeklyChart as BaseHelpdeskStatusWeeklyChart;
use App\Filament\Operations\Resources\Helpdesks\Pages\ListHelpdesks;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class HelpdeskStatusWeeklyChart extends BaseHelpdeskStatusWeeklyChart
{
    /**
     * El analista de un proveedor no ve la distribución anual de tickets:
     * resume la operación completa de TDG, no la suya.
     */
    public static function canView(): bool
    {
        $user = Auth::user();

        if ($user instanceof User && $user->isSupplierOperationsAnalyst()) {
            return false;
        }

        return parent::canView();
    }

    protected function getTablePage(): string
    {
        return ListHelpdesks::class;
    }
}
