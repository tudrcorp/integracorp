<?php

declare(strict_types=1);

use App\Filament\Business\Resources\Helpdesks\Widgets\HelpdeskStatusWeeklyChart;
use App\Filament\Business\Resources\Helpdesks\Widgets\StatsOverviewHelpdesk;
use App\Support\HelpdeskBusinessScrumRoles;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

/** Solo lee: la transacción revertida es la red por si algún día escribe. */
beforeEach(fn () => DB::beginTransaction());
afterEach(fn () => DB::rollBack());

it('busca a la Product Owner y a los desarrolladores una sola vez por petición', function (): void {
    DB::enableQueryLog();
    DB::flushQueryLog();

    $first = [HelpdeskBusinessScrumRoles::productOwnerId(), HelpdeskBusinessScrumRoles::developerIds()];

    for ($row = 0; $row < 20; $row++) {
        HelpdeskBusinessScrumRoles::productOwnerId();
        HelpdeskBusinessScrumRoles::developerIds();
        HelpdeskBusinessScrumRoles::developerOptions();
    }

    /** Una consulta por persona buscada (Product Owner + 2 desarrolladores), no una por fila. */
    expect(DB::getQueryLog())->toHaveCount(1 + count(HelpdeskBusinessScrumRoles::DEVELOPER_NAMES))
        ->and([HelpdeskBusinessScrumRoles::productOwnerId(), HelpdeskBusinessScrumRoles::developerIds()])->toBe($first);
});

it('la memoria se descarta entre jobs: un worker de larga vida vuelve a consultar', function (): void {
    HelpdeskBusinessScrumRoles::productOwnerId();

    /** Es lo que hace el worker de colas antes de cada job (y Laravel al cerrar cada petición). */
    app()->forgetScopedInstances();

    DB::enableQueryLog();
    DB::flushQueryLog();
    HelpdeskBusinessScrumRoles::productOwnerId();

    expect(DB::getQueryLog())->toHaveCount(1);
});

it('los widgets de Helpdesk se dibujan con la página y no sondean cada 5 segundos', function (): void {
    $lazy = new ReflectionProperty(StatsOverviewHelpdesk::class, 'isLazy');
    $statsPolling = (new ReflectionClass(StatsOverviewHelpdesk::class))->getProperty('pollingInterval');
    $chartPolling = (new ReflectionClass(HelpdeskStatusWeeklyChart::class))->getProperty('pollingInterval');

    expect($lazy->getValue())->toBeFalse()
        ->and((new ReflectionProperty(HelpdeskStatusWeeklyChart::class, 'isLazy'))->getValue())->toBeFalse()
        ->and($statsPolling->getDefaultValue())->toBe('60s')
        ->and($chartPolling->getDefaultValue())->toBeNull();

    /** Operaciones, Administración y Marketing heredan de los de Negocios y no lo pisan. */
    foreach (['Operations', 'Administration', 'Marketing'] as $panel) {
        foreach (['StatsOverviewHelpdesk', 'HelpdeskStatusWeeklyChart'] as $widget) {
            $class = "App\\Filament\\{$panel}\\Resources\\Helpdesks\\Widgets\\{$widget}";
            $source = file_get_contents((new ReflectionClass($class))->getFileName());

            expect(get_parent_class($class))->toBe("App\\Filament\\Business\\Resources\\Helpdesks\\Widgets\\{$widget}")
                ->and($source)->not->toContain('isLazy')
                ->and($source)->not->toContain('pollingInterval');
        }
    }
});
