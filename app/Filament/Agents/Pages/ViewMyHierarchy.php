<?php

declare(strict_types=1);

namespace App\Filament\Agents\Pages;

use App\Filament\Shared\CommercialStructure\CommercialHierarchyFlowchart;
use App\Models\Agent;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class ViewMyHierarchy extends Page
{
    protected static ?string $navigationLabel = 'Ver Jerarquía';

    protected static ?string $title = 'Mi jerarquía comercial';

    protected static ?string $slug = 'ver-jerarquia';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected string $view = 'filament.shared.pages.view-my-hierarchy';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        return filled(Auth::user()?->agent_id);
    }

    public function getHierarchyDiagram(): HtmlString
    {
        $agentId = (int) (Auth::user()?->agent_id ?? 0);
        $agent = $agentId > 0 ? Agent::query()->find($agentId) : null;

        if (! $agent instanceof Agent) {
            return new HtmlString(
                '<div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-950/40 dark:text-amber-100">'
                .'No se encontró el agente asociado a tu usuario.'
                .'</div>'
            );
        }

        return CommercialHierarchyFlowchart::renderForAgent($agent);
    }
}
