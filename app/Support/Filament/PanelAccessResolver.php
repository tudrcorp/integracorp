<?php

declare(strict_types=1);

namespace App\Support\Filament;

use App\Models\User;
use App\Support\LivePresence\ActivityContext;
use Filament\Facades\Filament;
use Throwable;

/**
 * A qué paneles puede entrar un usuario y por qué dirección.
 *
 * Se usa para orientar a quien escribe bien su clave en el panel equivocado:
 * Filament responde igual que con una clave incorrecta y el usuario no tiene
 * forma de saber que el problema es la dirección.
 */
final class PanelAccessResolver
{
    /**
     * @return list<array{id: string, label: string, url: string}>
     */
    public static function accessiblePanels(User $user, ?string $exceptPanelId = null): array
    {
        $panels = [];

        foreach (Filament::getPanels() as $panel) {
            $id = $panel->getId();

            if ($id === $exceptPanelId || ! $panel->hasLogin()) {
                continue;
            }

            try {
                /** Sobre una copia: varias ramas de canAccessPanel asignan `status` en vez de compararlo. */
                if (! (clone $user)->canAccessPanel($panel)) {
                    continue;
                }

                $panels[] = [
                    'id' => $id,
                    'label' => self::label($id),
                    'url' => (string) $panel->getLoginUrl(),
                ];
            } catch (Throwable) {
                continue;
            }
        }

        return $panels;
    }

    public static function label(string $panelId): string
    {
        return ActivityContext::PANELS[$panelId] ?? ucfirst($panelId);
    }
}
