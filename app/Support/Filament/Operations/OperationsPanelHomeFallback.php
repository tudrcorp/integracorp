<?php

declare(strict_types=1);

namespace App\Support\Filament\Operations;

use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;

/**
 * Primer destino disponible del panel de Operaciones para un usuario que no
 * tiene asignado el Escritorio.
 */
final class OperationsPanelHomeFallback
{
    public static function url(): ?string
    {
        $panel = Filament::getCurrentOrDefaultPanel();

        if ($panel === null) {
            return null;
        }

        $homeUrl = url($panel->getPath());

        foreach ($panel->getNavigation() as $group) {
            foreach (collect($group->getItems()) as $item) {
                $url = self::resolveItemUrl($item, $homeUrl);

                if ($url !== null) {
                    return $url;
                }
            }
        }

        return null;
    }

    private static function resolveItemUrl(NavigationItem $item, string $homeUrl): ?string
    {
        $url = $item->getUrl();

        if (filled($url) && rtrim($url, '/') !== rtrim($homeUrl, '/')) {
            return $url;
        }

        foreach (collect($item->getChildItems()) as $childItem) {
            $childUrl = self::resolveItemUrl($childItem, $homeUrl);

            if ($childUrl !== null) {
                return $childUrl;
            }
        }

        return null;
    }
}
