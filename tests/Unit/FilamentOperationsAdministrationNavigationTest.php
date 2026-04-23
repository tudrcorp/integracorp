<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;

uses(Tests\TestCase::class);

/**
 * @return list<NavigationItem|NavigationGroup>
 */
function filamentNavigationRoots(): array
{
    return collect(Filament::getNavigation())->values()->all();
}

function panelNavigationContainsExactLabel(string $panelId, string $label): bool
{
    Filament::setCurrentPanel($panelId);
    $stack = filamentNavigationRoots();

    while ($stack !== []) {
        $item = array_pop($stack);
        if ($item instanceof NavigationItem) {
            if ($item->getLabel() === $label) {
                return true;
            }
        } elseif ($item instanceof NavigationGroup) {
            foreach (collect($item->getItems())->values()->all() as $child) {
                $stack[] = $child;
            }
        }
    }

    return false;
}

it('no muestra Gestión de Carpetas en la navegación del panel operations', function (): void {
    expect(panelNavigationContainsExactLabel('operations', 'Gestión de Carpetas'))->toBeFalse();
});

it('no muestra Gestión de Carpetas en la navegación del panel administration', function (): void {
    expect(panelNavigationContainsExactLabel('administration', 'Gestión de Carpetas'))->toBeFalse();
});
