<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\Agencies\Pages;

use App\Filament\Administration\Resources\Agencies\AgencyResource;
use App\Filament\Shared\CommercialStructure\Actions\CommercialStructureIosActionsMenu;
use App\Filament\Shared\CommercialStructure\Actions\ResetCommercialStructureUserPasswordAction;
use App\Filament\Shared\CommercialStructure\Actions\UpdateCommercialStructureEmailAction;
use App\Models\Agency;
use App\Support\Filament\CommercialStructurePageHeader;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewAgency extends ViewRecord
{
    protected static string $resource = AgencyResource::class;

    /**
     * Idéntico a Crear Ticket / Crear Nuevo Paciente: estilos iOS en theme.css.
     */
    private const WARNING_BUTTON_CLASS = 'aviso-btn-ios-warning shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    public function getTitle(): string|Htmlable
    {
        /** @var Agency $agency */
        $agency = $this->getRecord();

        return CommercialStructurePageHeader::forAgency($agency);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Volver')
                ->icon('heroicon-o-arrow-left')
                ->color('warning')
                ->url(AgencyResource::getUrl())
                ->extraAttributes([
                    'class' => self::WARNING_BUTTON_CLASS,
                ]),
            CommercialStructureIosActionsMenu::make([
                EditAction::make()
                    ->label('Editar')
                    ->icon('heroicon-o-pencil')
                    ->color('primary'),
                UpdateCommercialStructureEmailAction::make('agency', 'administration'),
                ResetCommercialStructureUserPasswordAction::make('agency', 'administration'),
            ]),
        ];
    }
}
