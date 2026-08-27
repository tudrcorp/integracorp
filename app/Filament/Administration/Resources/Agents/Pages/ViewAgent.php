<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\Agents\Pages;

use App\Filament\Administration\Resources\Agents\AgentResource;
use App\Filament\Shared\CommercialStructure\Actions\CommercialStructureIosActionsMenu;
use App\Filament\Shared\CommercialStructure\Actions\ResetCommercialStructureUserPasswordAction;
use App\Filament\Shared\CommercialStructure\Actions\UpdateCommercialStructureEmailAction;
use App\Models\Agent;
use App\Support\Filament\CommercialStructurePageHeader;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewAgent extends ViewRecord
{
    protected static string $resource = AgentResource::class;

    private const WARNING_BUTTON_CLASS = 'aviso-btn-ios-warning shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    public function getTitle(): string|Htmlable
    {
        /** @var Agent $agent */
        $agent = $this->getRecord();

        return CommercialStructurePageHeader::forAgent($agent);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Volver')
                ->icon('heroicon-o-arrow-left')
                ->color('warning')
                ->url(AgentResource::getUrl())
                ->extraAttributes([
                    'class' => self::WARNING_BUTTON_CLASS,
                ]),
            CommercialStructureIosActionsMenu::make([
                EditAction::make()
                    ->label('Editar')
                    ->icon('heroicon-o-pencil')
                    ->color('primary'),
                UpdateCommercialStructureEmailAction::make('agent', 'administration'),
                ResetCommercialStructureUserPasswordAction::make('agent', 'administration'),
            ]),
        ];
    }
}
