<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\HelpdeskTicketVisibility;
use App\Support\HelpdeskUserAccess;

it('permite cola global a SISTEMAS, SUPERADMIN y al Product Owner Scrum', function (): void {
    $systems = new User;
    $systems->departament = ['SISTEMAS'];

    $superAdmin = new User;
    $superAdmin->departament = ['SUPERADMIN'];

    $negocios = new User;
    $negocios->departament = ['NEGOCIOS'];

    $productOwner = new User;
    $productOwner->name = 'Becky Acosta';
    $productOwner->departament = ['NEGOCIOS'];

    expect(HelpdeskTicketVisibility::canViewGlobalQueue($systems))->toBeTrue()
        ->and(HelpdeskTicketVisibility::canViewGlobalQueue($superAdmin))->toBeTrue()
        ->and(HelpdeskTicketVisibility::canViewGlobalQueue($productOwner))->toBeTrue()
        ->and(HelpdeskTicketVisibility::canViewGlobalQueue($negocios))->toBeFalse()
        ->and(HelpdeskTicketVisibility::canViewGlobalQueue(null))->toBeFalse();
});

it('reconoce SISTEMAS y SUPERADMIN con separadores en el departamento', function (): void {
    $systems = new User;
    $systems->departament = ['Coord Sistemas'];

    $superAdmin = new User;
    $superAdmin->departament = ['Super Admin'];

    expect(HelpdeskUserAccess::hasSystemsDepartment($systems))->toBeTrue()
        ->and(HelpdeskTicketVisibility::canViewGlobalQueue($systems))->toBeTrue()
        ->and(HelpdeskTicketVisibility::canViewGlobalQueue($superAdmin))->toBeTrue();
});

it('HelpdeskTableConfigurator usa visibilidad compartida y filtros de cola', function (): void {
    $path = dirname(__DIR__, 2).'/app/Support/HelpdeskTableConfigurator.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('HelpdeskTicketVisibility::constrainVisible')
        ->toContain("Tab::make('Míos')")
        ->toContain("Tab::make('Sin asignar')")
        ->toContain('constrainToMine')
        ->toContain('constrainUnassigned')
        ->toContain('SelectFilter::make(\'status\')')
        ->toContain('SelectFilter::make(\'priority\')')
        ->toContain('SelectFilter::make(\'ticket_type\')')
        ->toContain('SelectFilter::make(\'rrhhColaboradores\')')
        ->toContain('SelectFilter::make(\'created_by\')')
        ->toContain('->deferFilters(false)');
});

it('HelpdeskUnreadNoteTracker reutiliza la misma visibilidad de cola', function (): void {
    $path = dirname(__DIR__, 2).'/app/Support/HelpdeskUnreadNoteTracker.php';

    expect(file_get_contents($path))
        ->toContain('HelpdeskTicketVisibility::constrainVisible')
        ->not->toContain('where(\'created_by\', $user->name)');
});

it('export csv de helpdesk restringe ids a tickets visibles', function (): void {
    $path = dirname(__DIR__, 2).'/app/Http/Controllers/HelpdeskExportCsvController.php';

    expect(file_get_contents($path))
        ->toContain('HelpdeskTicketVisibility::constrainVisible')
        ->toContain('whereIn(\'id\', $ids)');
});

it('oculta el inbox del Product Owner a la cola global de sistemas', function (): void {
    $path = dirname(__DIR__, 2).'/app/Support/HelpdeskTicketVisibility.php';

    expect(file_get_contents($path))
        ->toContain('hideProductOwnerInboxUnlessMine')
        ->toContain('HelpdeskBusinessScrumRoles::isProductOwnerUser');
});

it('tabs de cola global solo se registran para SISTEMAS o SUPERADMIN', function (): void {
    $path = dirname(__DIR__, 2).'/app/Support/HelpdeskTableConfigurator.php';

    expect(file_get_contents($path))
        ->toContain('HelpdeskTicketVisibility::canViewGlobalQueue()')
        ->toContain("\$tabs['mios']")
        ->toContain("\$tabs['sin_asignar']");
});
