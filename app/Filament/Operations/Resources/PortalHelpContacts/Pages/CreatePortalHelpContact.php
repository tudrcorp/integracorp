<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\PortalHelpContacts\Pages;

use App\Filament\Operations\Resources\PortalHelpContacts\PortalHelpContactResource;
use App\Support\Filament\FilamentIosButton;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

class CreatePortalHelpContact extends CreateRecord
{
    protected static string $resource = PortalHelpContactResource::class;

    protected static ?string $title = 'Crear Contacto de Ayuda Portal';

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction(),
            $this->getCancelFormAction(),
        ];
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()
            ->label(new HtmlString(Blade::render(<<<'BLADE'
                <span wire:loading.remove wire:target="create">Guardar contacto</span>
                <span wire:loading wire:target="create" class="flex items-center space-x-2">
                    <span>Guardando...</span>
                </span>
            BLADE)))
            ->extraAttributes([
                'class' => 'min-w-28 justify-center bg-indigo-600 hover:bg-indigo-700 text-white',
            ])
            ->submit('create');
    }

    protected function getCancelFormAction(): Action
    {
        return parent::getCancelFormAction()
            ->extraAttributes([
                'class' => FilamentIosButton::extraClassForFilamentColor('gray'),
            ]);
    }

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
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['name'] = trim((string) ($data['name'] ?? ''));
        $data['phone'] = trim((string) ($data['phone'] ?? ''));
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['created_by'] = (string) Auth::id();
        $data['updated_by'] = (string) Auth::id();

        return $data;
    }
}
