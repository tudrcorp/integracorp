<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\TdevAgencies\Pages;

use App\Filament\Business\Resources\TdevAgencies\TdevAgencyResource;
use App\Filament\Business\Resources\TdevAgencies\Widgets\TdevAgencyStatsOverview;
use App\Support\Filament\FilamentIosButton;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class ListTdevAgencies extends ListRecords
{
    protected static string $resource = TdevAgencyResource::class;

    public function getTitle(): string|Htmlable
    {
        return new HtmlString(
            <<<'HTML'
            <div class="flex flex-col items-start gap-3 py-1">
                <img src="/image/logo-tdev.png" alt="TDEV" class="h-20 w-auto max-w-[14rem] object-contain drop-shadow-md sm:h-24 sm:max-w-[16rem]">
                <div class="min-w-0">
                    <p class="text-[11px] font-bold uppercase tracking-[0.28em] text-cyan-700 dark:text-cyan-300">
                        Tu Doctor En Viajes
                    </p>
                    <h1 class="text-2xl font-bold tracking-tight text-gray-950 dark:text-white sm:text-3xl">
                        Agencias TDEV
                    </h1>
                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                        Estructura comercial · nivel 2, asociadas y agentes
                    </p>
                </div>
            </div>
            HTML
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nueva agencia TDEV')
                ->icon(Heroicon::OutlinedPlusCircle)
                ->color('primary')
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('primary'),
                ]),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            TdevAgencyStatsOverview::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }
}
