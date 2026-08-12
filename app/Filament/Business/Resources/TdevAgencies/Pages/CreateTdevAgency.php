<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\TdevAgencies\Pages;

use App\Filament\Business\Resources\TdevAgencies\TdevAgencyResource;
use App\Models\TdevAgency;
use App\Support\Filament\FilamentIosButton;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class CreateTdevAgency extends CreateRecord
{
    protected static string $resource = TdevAgencyResource::class;

    public function getTitle(): string|Htmlable
    {
        return new HtmlString(
            <<<'HTML'
            <div class="flex flex-col items-start gap-3 py-1">
                <img src="/image/logo-tdev.png" alt="TDEV" class="h-16 w-auto max-w-[12rem] object-contain drop-shadow-md sm:h-20 sm:max-w-[14rem]">
                <div class="min-w-0">
                    <p class="text-[11px] font-bold uppercase tracking-[0.28em] text-cyan-700 dark:text-cyan-300">
                        Nueva agencia TDEV
                    </p>
                    <h1 class="text-2xl font-bold tracking-tight text-gray-950 dark:text-white sm:text-3xl">
                        Crear Agencia TDEV
                    </h1>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <span style="background:#2299A4;box-shadow:0 8px 18px rgba(34,153,164,.28);color:#fff;padding:5px 14px;border-radius:999px;font-size:.75rem;font-weight:700;">
                            Nivel 2 · principal
                        </span>
                    </div>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        Completa identidad, contacto y ubicación en las pestañas del formulario.
                    </p>
                </div>
            </div>
            HTML
        );
    }

    protected function getCreateFormAction(): \Filament\Actions\Action
    {
        return parent::getCreateFormAction()
            ->label('Crear agencia')
            ->extraAttributes([
                'class' => FilamentIosButton::extraClassForFilamentColor('primary'),
            ]);
    }

    protected function getCreateAnotherFormAction(): \Filament\Actions\Action
    {
        return parent::getCreateAnotherFormAction()
            ->label('Crear y añadir otra')
            ->extraAttributes([
                'class' => FilamentIosButton::extraClassForFilamentColor('gray'),
            ]);
    }

    protected function getCancelFormAction(): \Filament\Actions\Action
    {
        return parent::getCancelFormAction()
            ->extraAttributes([
                'class' => FilamentIosButton::extraClassForFilamentColor('gray'),
            ]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['level'] = TdevAgency::LEVEL_TWO;
        $data['parent_id'] = null;
        $data['created_by'] = Auth::user()?->name;

        return $data;
    }
}
