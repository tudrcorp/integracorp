<?php

declare(strict_types=1);

namespace App\Filament\Shared\CommercialStructure;

use App\Support\CommercialStructure\ReferidorAccess;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Set;

final class ReferidorToggle
{
    public static function make(?string $allowedHelperText = null, bool $forceReadOnly = false): Toggle
    {
        $allowedHelperText ??= 'Marca si opera como referidor.';

        return Toggle::make('is_referidor')
            ->label('Es Referidor')
            ->default(false)
            ->live()
            ->afterStateUpdated(function (Set $set, mixed $state): void {
                if (! $state) {
                    $set('referidor_percentage', null);
                }
            })
            ->disabled(fn (): bool => $forceReadOnly || ! ReferidorAccess::userCanManage())
            ->dehydrated(fn (): bool => ! $forceReadOnly && ReferidorAccess::userCanManage())
            ->helperText(function () use ($allowedHelperText, $forceReadOnly): string {
                if ($forceReadOnly) {
                    return 'Solo el administrador del sistema puede marcar este campo.';
                }

                if (ReferidorAccess::userCanManage()) {
                    return $allowedHelperText;
                }

                return 'No tiene permiso para marcar referidor. Solicítelo al administrador del sistema.';
            });
    }
}
