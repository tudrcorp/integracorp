<?php

declare(strict_types=1);

namespace App\Filament\Shared\Auth;

use App\Models\User;
use App\Support\Filament\PanelAccessResolver;
use App\Support\LivePresence\SecurityMonitor;
use App\Support\LivePresence\UserBlockList;
use Filament\Actions\Action;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Login de todos los paneles.
 *
 * Filament responde «credenciales incorrectas» cuando la clave es correcta pero el
 * usuario no tiene acceso al panel, y además lo cuenta como login fallido (dos
 * veces: el guard y la página disparan `Failed`). Un usuario que se equivoca de
 * dirección termina con la cuenta bloqueada sin saber por qué.
 *
 * Aquí, antes del flujo normal, se detecta ese caso y se le indica el panel correcto,
 * sin contarlo como fallo. Todo lo demás (límite de intentos, bloqueo de cuenta,
 * lista negra de usuarios, clave incorrecta) sigue exactamente igual.
 */
class PanelAwareLogin extends Login
{
    /** Verificaciones de clave extra por minuto e IP que puede costar este desvío. */
    public const WRONG_PANEL_CHECKS_PER_MINUTE = 5;

    public function authenticate(): ?LoginResponse
    {
        $this->redirectIfWrongPanel();

        return parent::authenticate();
    }

    /**
     * Solo actúa si la clave es correcta y el panel no corresponde. En cualquier duda
     * devuelve el control al flujo normal, que decide y cuenta como siempre.
     *
     * @throws ValidationException
     */
    protected function redirectIfWrongPanel(): void
    {
        if (filled($this->userUndertakingMultiFactorAuthentication)) {
            return;
        }

        /** Con el login frenado, el flujo normal muestra la espera: aquí no se revela nada. */
        if (RateLimiter::tooManyAttempts($this->getRateLimitKey('authenticate'), 5)) {
            return;
        }

        try {
            $credentials = $this->getCredentialsFromFormData($this->form->getState());
        } catch (Throwable) {
            return;
        }

        $email = trim((string) ($credentials['email'] ?? ''));

        if ($email === '' || SecurityMonitor::accountLock($email) !== null) {
            return;
        }

        $panel = Filament::getCurrentOrDefaultPanel();
        $provider = Filament::auth()->getProvider(); /** @phpstan-ignore-line */
        $user = $provider->retrieveByCredentials($credentials);

        if (! $user instanceof User) {
            return;
        }

        try {
            if ((clone $user)->canAccessPanel($panel)) {
                return;
            }
        } catch (Throwable) {
            return;
        }

        if (UserBlockList::isBlocked((int) $user->getKey())) {
            return;
        }

        $checkKey = $this->getRateLimitKey('wrongPanelCheck');

        if (RateLimiter::tooManyAttempts($checkKey, self::WRONG_PANEL_CHECKS_PER_MINUTE)) {
            return;
        }

        RateLimiter::hit($checkKey, 60);

        if (! $provider->validateCredentials($user, $credentials)) {
            return;
        }

        $panels = PanelAccessResolver::accessiblePanels($user, $panel->getId());
        $labels = array_column($panels, 'label');

        SecurityMonitor::recordWrongPanelLogin(request(), $email, PanelAccessResolver::label($panel->getId()), $labels);

        $this->notifyCorrectPanels($panels);

        throw ValidationException::withMessages([
            'data.email' => $panels === []
                ? 'Su usuario no tiene acceso a ningún panel. Contacte a soporte.'
                : 'Su usuario no ingresa por este panel. Entre por: '.implode(', ', $labels).'.',
        ]);
    }

    /**
     * @param  list<array{id: string, label: string, url: string}>  $panels
     */
    protected function notifyCorrectPanels(array $panels): void
    {
        $notification = Notification::make()
            ->warning()
            ->persistent();

        if ($panels === []) {
            $notification
                ->title('Su usuario no tiene un panel asignado')
                ->body('La clave es correcta, pero su usuario no tiene acceso a ningún panel. Contacte a soporte.')
                ->send();

            return;
        }

        $notification
            ->title('Está entrando por el panel equivocado')
            ->body('Su clave es correcta. Su acceso es por '.implode(', ', array_column($panels, 'label')).'.')
            ->actions(array_map(
                static fn (array $panel): Action => Action::make('go-'.$panel['id'])
                    ->label('Ir a '.$panel['label'])
                    ->url($panel['url'])
                    ->button(),
                $panels,
            ))
            ->send();
    }
}
