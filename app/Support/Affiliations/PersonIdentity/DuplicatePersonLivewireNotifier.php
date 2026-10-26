<?php

declare(strict_types=1);

namespace App\Support\Affiliations\PersonIdentity;

use Filament\Notifications\Notification;
use Livewire\Component;
use Livewire\EventBus;
use Throwable;
use WeakMap;

use function Livewire\on;

/**
 * Cuando el candado de personas duplicadas corta un guardado dentro de un panel (formulario,
 * acción o relation manager), el analista ve el motivo completo en una notificación en lugar de
 * una pantalla de error. El formulario o la modal quedan abiertos con lo que escribió, y la
 * transacción del panel ya deshizo todo.
 *
 * Solo actúa en componentes de los paneles internos (`App\Filament\…`): los formularios públicos
 * capturan la excepción por su cuenta y muestran {@see DuplicatePersonIdentityException::publicMessage()},
 * que no revela datos de otra afiliación.
 */
final class DuplicatePersonLivewireNotifier
{
    private static ?WeakMap $registeredOnBus = null;

    public static function register(): void
    {
        $bus = app(EventBus::class);
        self::$registeredOnBus ??= new WeakMap;

        if (isset(self::$registeredOnBus[$bus])) {
            return;
        }

        self::$registeredOnBus[$bus] = true;

        on('exception', static function (mixed $target, Throwable $exception, callable $stopPropagation): void {
            if (! self::handles($target, $exception)) {
                return;
            }

            /** @var DuplicatePersonIdentityException $exception */
            Notification::make()
                ->title($exception->title())
                ->body($exception->getMessage())
                ->danger()
                ->persistent()
                ->send();

            $stopPropagation();
        });
    }

    public static function handles(mixed $target, Throwable $exception): bool
    {
        return $exception instanceof DuplicatePersonIdentityException
            && $target instanceof Component
            && str_starts_with($target::class, 'App\\Filament\\');
    }
}
