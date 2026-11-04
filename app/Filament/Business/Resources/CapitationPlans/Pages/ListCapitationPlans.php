<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\CapitationPlans\Pages;

use App\Filament\Business\Resources\CapitationPlans\CapitationPlanResource;
use App\Models\CapitationPlan;
use App\Support\Filament\FilamentIosButton;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class ListCapitationPlans extends ListRecords
{
    protected static string $resource = CapitationPlanResource::class;

    protected static ?string $title = 'Planes de Capitado';

    /**
     * Resumen en una sola consulta agregada.
     */
    public function getSubheading(): string|Htmlable|null
    {
        $row = CapitationPlan::query()
            ->where('status', CapitationPlan::STATUS_ACTIVE)
            ->toBase()
            ->selectRaw('COUNT(*) as plans, COUNT(DISTINCT CONCAT(provider_type, ":", provider_id)) as providers')
            ->first();

        $plans = (int) ($row->plans ?? 0);
        $providers = (int) ($row->providers ?? 0);

        if ($plans === 0) {
            return 'Planes negociados con los proveedores de la red Outsourcing.';
        }

        return ($plans === 1 ? '1 plan activo' : $plans.' planes activos').' en '.($providers === 1 ? '1 proveedor' : $providers.' proveedores').' de la red Outsourcing.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nuevo plan')
                ->icon(Heroicon::Plus)
                ->color('primary')
                ->extraAttributes(['class' => FilamentIosButton::extraClassForFilamentColor('primary')]),
        ];
    }
}
