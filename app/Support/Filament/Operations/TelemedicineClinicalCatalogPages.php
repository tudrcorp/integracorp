<?php

declare(strict_types=1);

namespace App\Support\Filament\Operations;

use App\Support\Filament\FilamentIosButton;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

final class TelemedicineClinicalCatalogPages
{
    public static function backAction(string $indexUrl): Action
    {
        return Action::make('regresar')
            ->label('Volver al listado')
            ->button()
            ->icon('heroicon-s-arrow-left')
            ->color('gray')
            ->extraAttributes([
                'class' => FilamentIosButton::extraClassForFilamentColor('gray'),
            ])
            ->url($indexUrl);
    }

    public static function enhanceCreateAction(Action $action, string $saveLabel): Action
    {
        return $action
            ->label(new HtmlString(Blade::render(<<<'BLADE'
                <span wire:loading.remove wire:target="create">{{ $saveLabel }}</span>
                <span wire:loading wire:target="create" class="flex items-center gap-2">
                    <span>Guardando…</span>
                </span>
            BLADE, ['saveLabel' => $saveLabel])))
            ->extraAttributes([
                'class' => FilamentIosButton::extraClassForFilamentColor('primary'),
            ]);
    }

    public static function enhanceSaveAction(Action $action): Action
    {
        return $action
            ->label(new HtmlString(Blade::render(<<<'BLADE'
                <span wire:loading.remove wire:target="save">Guardar cambios</span>
                <span wire:loading wire:target="save" class="flex items-center gap-2">
                    <span>Guardando…</span>
                </span>
            BLADE)))
            ->extraAttributes([
                'class' => FilamentIosButton::extraClassForFilamentColor('primary'),
            ]);
    }

    public static function enhanceCancelAction(Action $action): Action
    {
        return $action
            ->label('Cancelar')
            ->extraAttributes([
                'class' => FilamentIosButton::extraClassForFilamentColor('gray'),
            ]);
    }

    public static function createdNotification(string $entityLabel, mixed $name = null): Notification
    {
        $trimmed = trim((string) $name);

        return Notification::make()
            ->success()
            ->icon('heroicon-o-check-circle')
            ->title(ucfirst($entityLabel).' registrado')
            ->body($trimmed !== ''
                ? 'Se guardó '.$trimmed.'. Volvió al listado.'
                : 'El registro ya está en el listado.');
    }

    public static function savedNotification(string $entityLabel, mixed $name = null): Notification
    {
        $trimmed = trim((string) $name);

        return Notification::make()
            ->success()
            ->icon('heroicon-o-check-circle')
            ->title(ucfirst($entityLabel).' actualizado')
            ->body($trimmed !== ''
                ? 'Se guardaron los cambios de '.$trimmed.'. Volvió al listado.'
                : 'Los cambios ya están guardados. Volvió al listado.');
    }
}
