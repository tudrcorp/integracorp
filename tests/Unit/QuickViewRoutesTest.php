<?php

declare(strict_types=1);

use App\Support\Filament\QuickViewRoutes;
use Filament\Facades\Filament;

uses(Tests\TestCase::class);

function quickViewMatches(string $path): bool
{
    foreach (QuickViewRoutes::patterns('administration') as $pattern) {
        if (preg_match('#'.str_replace('#', '\#', $pattern).'#', $path) === 1) {
            return true;
        }
    }

    return false;
}

it('abre en vista rápida cualquier pantalla de Filament del panel: menú, listados, registros, crear y editar', function (string $path): void {
    expect(quickViewMatches($path))->toBeTrue();
})->with([
    'escritorio' => '/administration',
    'ítem del menú' => '/administration/collections',
    'ver registro' => '/administration/affiliations/123',
    'subpágina de registro' => '/administration/affiliations/123/pagos',
    'crear' => '/administration/affiliations/create',
    'editar (acción que navega)' => '/administration/affiliations/123/edit',
    'perfil (menú de usuario)' => '/administration/profile',
]);

it('nunca abre descargas, PDF, exportaciones, login ni otros paneles', function (string $path): void {
    expect(quickViewMatches($path))->toBeFalse();
})->with([
    'descarga de ficha' => '/administration/affiliations/123/ficha/download',
    'vista previa PDF' => '/administration/affiliations/123/ficha/preview',
    'exportación CSV' => '/administration/export-affiliations-csv',
    'regenerar aviso' => '/administration/aviso-cobro/regenerate/5',
    'login' => '/administration/login',
    'restablecer contraseña' => '/administration/password-reset/request',
    'otro panel' => '/business/affiliations/123',
]);

it('entrega la lista blanca a la página y marca el modo incrustado antes de pintar', function (): void {
    Filament::setCurrentPanel('administration');

    $host = view('filament.hooks.quick-view')->render();
    $head = view('filament.hooks.quick-view-head')->render();

    preg_match('/id="ic-quick-view-config">(.*?)<\/script>/s', $host, $match);

    expect(json_decode($match[1] ?? '{}', true))->toBe(['patterns' => QuickViewRoutes::patterns('administration')])
        ->and($head)->toContain("frame.hasAttribute('data-ic-quick-view')")
        ->and($host)->toContain("setAttribute('data-ic-quick-view', '1')");
});

it('solo se abre con Alt / ⌥ Option + clic, sin botón flotante, consejo ni preferencia', function (): void {
    $script = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/hooks/quick-view.blade.php');

    expect($script)
        ->toContain('event.button === 0 && event.altKey && !event.ctrlKey && !event.metaKey && !event.shiftKey')
        ->toContain('event.stopImmediatePropagation();')
        ->toContain("document.querySelector('.fi-modal.fi-modal-open')")
        ->toContain('component.$wire.$refresh();')
        ->not->toContain('ic-qv-pill')
        ->not->toContain('ic-qv-hint')
        ->not->toContain("'mode'")
        ->not->toContain('history.pushState');
});

it('está activa solo en el panel de Administración y sin interruptor en el menú de usuario', function (): void {
    $root = dirname(__DIR__, 2).'/app/Providers/Filament/';

    expect((string) file_get_contents($root.'AdministrationPanelProvider.php'))
        ->toContain("view('filament.hooks.quick-view-head')")
        ->toContain("view('filament.hooks.quick-view')")
        ->not->toContain('quickViewPreference');

    foreach (glob($root.'*PanelProvider.php') as $provider) {
        if (! str_ends_with($provider, 'AdministrationPanelProvider.php')) {
            expect((string) file_get_contents($provider))->not->toContain('filament.hooks.quick-view');
        }
    }
});
