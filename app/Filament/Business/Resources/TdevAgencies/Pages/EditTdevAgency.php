<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\TdevAgencies\Pages;

use App\Filament\Business\Resources\TdevAgencies\TdevAgencyResource;
use App\Models\TdevAgency;
use App\Support\Filament\FilamentIosButton;
use App\Support\Tdev\TdevAgencyRegistrar;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class EditTdevAgency extends EditRecord
{
    protected static string $resource = TdevAgencyResource::class;

    public function getTitle(): string|Htmlable
    {
        /** @var TdevAgency $record */
        $record = $this->getRecord();
        $name = e((string) $record->name);
        $level = $record->isLevelThree() ? 'Nivel 3 · asociada' : 'Nivel 2 · principal';
        $levelClass = $record->isLevelThree()
            ? 'background:#f59e0b;box-shadow:0 8px 18px rgba(245,158,11,.28);'
            : 'background:#2299A4;box-shadow:0 8px 18px rgba(34,153,164,.28);';
        $parent = $record->isLevelThree() && $record->parentAgency
            ? e((string) $record->parentAgency->name)
            : null;

        $parentHtml = $parent
            ? '<p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Asociada a <span class="font-semibold text-gray-700 dark:text-gray-200">'.$parent.'</span></p>'
            : '';

        return new HtmlString(
            <<<HTML
            <div class="flex flex-col items-start gap-3 py-1">
                <img src="/image/logo-tdev.png" alt="TDEV" class="h-16 w-auto max-w-[12rem] object-contain drop-shadow-md sm:h-20 sm:max-w-[14rem]">
                <div class="min-w-0">
                    <p class="text-[11px] font-bold uppercase tracking-[0.28em] text-cyan-700 dark:text-cyan-300">
                        Editar agencia TDEV
                    </p>
                    <h1 class="text-2xl font-bold tracking-tight text-gray-950 dark:text-white sm:text-3xl">
                        {$name}
                    </h1>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <span style="{$levelClass}color:#fff;padding:5px 14px;border-radius:999px;font-size:.75rem;font-weight:700;">
                            {$level}
                        </span>
                    </div>
                    {$parentHtml}
                </div>
            </div>
            HTML
        );
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = Auth::user()?->name;

        /** @var TdevAgency $record */
        $record = $this->getRecord();

        if ($record->isLevelThree()) {
            $data['level'] = TdevAgency::LEVEL_THREE;
            $data['parent_id'] = $record->parent_id;
        } else {
            $data['level'] = TdevAgency::LEVEL_TWO;
            $data['parent_id'] = null;
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('openLandingLink')
                ->label('Abrir página web')
                ->icon(Heroicon::OutlinedGlobeAlt)
                ->color('info')
                ->visible(fn (TdevAgency $record): bool => $record->isLevelTwo())
                ->url(fn (TdevAgency $record): string => TdevAgencyRegistrar::publicLandingUrl($record))
                ->openUrlInNewTab()
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('info'),
                ]),
            Action::make('openRegistrationLink')
                ->label('Abrir URL de agentes')
                ->icon(Heroicon::OutlinedLink)
                ->color('success')
                ->url(fn (TdevAgency $record): string => TdevAgencyRegistrar::publicAgentRegistrationUrl($record))
                ->openUrlInNewTab()
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('success'),
                ]),
            Action::make('openAgencyRegistrationLink')
                ->label('Abrir URL agencias N3')
                ->icon(Heroicon::OutlinedBuildingStorefront)
                ->color('warning')
                ->visible(fn (TdevAgency $record): bool => $record->isLevelTwo())
                ->url(fn (TdevAgency $record): string => TdevAgencyRegistrar::publicLevelThreeAgencyRegistrationUrl($record))
                ->openUrlInNewTab()
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('warning'),
                ]),
            ViewAction::make()
                ->label('Ver')
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('gray'),
                ]),
            DeleteAction::make()
                ->label('Borrar')
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('danger'),
                ]),
        ];
    }
}
