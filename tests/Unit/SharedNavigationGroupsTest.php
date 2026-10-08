<?php

declare(strict_types=1);

use App\Filament\Administration\Pages\AgendaCorporativa as AdministrationAgendaCorporativa;
use App\Filament\Administration\Pages\CalendariosTdg as AdministrationCalendariosTdg;
use App\Filament\Administration\Resources\Helpdesks\HelpdeskResource as AdministrationHelpdeskResource;
use App\Filament\Business\Pages\AgendaCorporativa;
use App\Filament\Business\Pages\CalendariosTdg;
use App\Filament\Business\Pages\LiveActivityMonitor;
use App\Filament\Business\Pages\LiveQueueCenter;
use App\Filament\Business\Pages\UserActivityMonitor;
use App\Filament\Business\Resources\Helpdesks\HelpdeskResource;
use App\Filament\Marketing\Pages\AgendaCorporativa as MarketingAgendaCorporativa;
use App\Filament\Marketing\Pages\CalendariosTdg as MarketingCalendariosTdg;
use App\Filament\Marketing\Resources\Helpdesks\HelpdeskResource as MarketingHelpdeskResource;
use App\Filament\Operations\Pages\AgendaCorporativa as OperationsAgendaCorporativa;
use App\Filament\Operations\Pages\CalendariosTdg as OperationsCalendariosTdg;
use App\Filament\Operations\Resources\Helpdesks\HelpdeskResource as OperationsHelpdeskResource;
use App\Support\Filament\BusinessPanelNavigationGroups;
use App\Support\Filament\SharedNavigationGroups;

uses(Tests\TestCase::class);

it('Helpdesk, Agenda Corporativa y Calendarios TDG van en DEL USUARIO en los cuatro paneles', function (string $class): void {
    expect($class::getNavigationGroup())->toBe(SharedNavigationGroups::USER);
})->with([
    'negocios helpdesk' => [HelpdeskResource::class],
    'negocios agenda' => [AgendaCorporativa::class],
    'negocios calendarios' => [CalendariosTdg::class],
    'administración helpdesk' => [AdministrationHelpdeskResource::class],
    'administración agenda' => [AdministrationAgendaCorporativa::class],
    'administración calendarios' => [AdministrationCalendariosTdg::class],
    'marketing helpdesk' => [MarketingHelpdeskResource::class],
    'marketing agenda' => [MarketingAgendaCorporativa::class],
    'marketing calendarios' => [MarketingCalendariosTdg::class],
    'operaciones helpdesk' => [OperationsHelpdeskResource::class],
    'operaciones agenda' => [OperationsAgendaCorporativa::class],
    'operaciones calendarios' => [OperationsCalendariosTdg::class],
]);

it('dentro de DEL USUARIO el orden es Helpdesk, Agenda Corporativa y Calendarios TDG', function (): void {
    expect([HelpdeskResource::getNavigationSort(), AgendaCorporativa::getNavigationSort(), CalendariosTdg::getNavigationSort()])
        ->toBe([1, 2, 3]);
});

it('Actividad de usuarios, Monitor en vivo y Colas y errores van en MONITOREO Y SEGURIDAD, en ese orden', function (): void {
    $classes = [UserActivityMonitor::class, LiveActivityMonitor::class, LiveQueueCenter::class];

    foreach ($classes as $class) {
        expect($class::getNavigationGroup())->toBe(SharedNavigationGroups::MONITORING);
    }

    $sorts = array_map(fn (string $class): ?int => $class::getNavigationSort(), $classes);

    expect($sorts)->toBe([98, 99, 100]);
});

it('en Negocios DEL USUARIO va arriba y abierto, y MONITOREO Y SEGURIDAD al final y plegado', function (): void {
    $groups = BusinessPanelNavigationGroups::definitions();
    $labels = BusinessPanelNavigationGroups::labels();

    expect($labels[0])->toBe(SharedNavigationGroups::USER)
        ->and($groups[0]->isCollapsed())->toBeFalse()
        ->and(end($labels))->toBe(SharedNavigationGroups::MONITORING)
        ->and(end($groups)->isCollapsed())->toBeTrue();
});

it('el acordeón del menú deja DEL USUARIO fuera: no lo pliega al entrar ni al abrir otro grupo', function (): void {
    $html = view('filament.business.partials.sidebar-navigation-accordion-script')->render();

    expect($html)
        ->toContain('const alwaysOpenGroupLabels = JSON.parse(')
        ->toContain('DEL USUARIO')
        ->toContain('navigationGroupLabels.filter(isAccordionGroup)')
        ->toContain('label !== group && isAccordionGroup(label)');
});
