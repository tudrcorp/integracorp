<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\PortalHelpContacts\Pages;

use App\Filament\Operations\Resources\PortalHelpContacts\Actions\DeletePortalHelpContactAction;
use App\Filament\Operations\Resources\PortalHelpContacts\PortalHelpContactResource;
use App\Support\Filament\FilamentIosButton;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

class EditPortalHelpContact extends EditRecord
{
    protected static string $resource = PortalHelpContactResource::class;

    protected static ?string $title = 'Editar Contacto de Ayuda Portal';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('regresar')
                ->label('Regresar')
                ->button()
                ->icon('heroicon-s-arrow-left')
                ->color('warning')
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('warning'),
                ])
                ->url(PortalHelpContactResource::getUrl('index')),
            DeletePortalHelpContactAction::make(),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction(),
            $this->getCancelFormAction(),
        ];
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()
            ->label(new HtmlString(Blade::render(<<<'BLADE'
                <span wire:loading.remove wire:target="save">Guardar cambios</span>
                <span wire:loading wire:target="save" class="flex items-center space-x-2">
                    <span>Guardando...</span>
                </span>
            BLADE)))
            ->extraAttributes([
                'class' => 'min-w-28 justify-center bg-indigo-600 hover:bg-indigo-700 text-white',
            ]);
    }

    protected function getCancelFormAction(): Action
    {
        return parent::getCancelFormAction()
            ->extraAttributes([
                'class' => FilamentIosButton::extraClassForFilamentColor('gray'),
            ]);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['name'] = trim((string) ($data['name'] ?? ''));
        $data['phone'] = trim((string) ($data['phone'] ?? ''));
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['updated_by'] = (string) Auth::id();

        return $data;
    }
}
