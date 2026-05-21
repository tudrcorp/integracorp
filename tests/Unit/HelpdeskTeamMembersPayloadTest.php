<?php

declare(strict_types=1);

use App\Support\HelpdeskTeamMembersPayload;

it('devuelve arreglo vacío cuando no hay ids de colaboradores', function (): void {
    expect(HelpdeskTeamMembersPayload::fromColaboradorIds([]))->toBe([]);
});

it('expone la sección de equipo en el schema compartido del formulario helpdesk', function (): void {
    $path = dirname(__DIR__, 2).'/app/Support/HelpdeskFormSchema.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("Tabs::make('helpdeskFormTabs')")
        ->toContain("Tab::make('Equipo de ejecución')")
        ->toContain("TextInput::make('team')")
        ->toContain("Select::make('team_colaborador_ids')")
        ->toContain('->native(false)')
        ->toContain("'min:2'");
});

it('delega el formulario helpdesk de cada panel al schema compartido', function (string $panel): void {
    $path = dirname(__DIR__, 2)."/app/Filament/{$panel}/Resources/Helpdesks/Schemas/HelpdeskForm.php";
    $contents = file_get_contents($path);

    expect($contents)->toContain('HelpdeskFormSchema::configure');
})->with(['Business', 'Administration', 'Marketing', 'Operations']);

it('no recorta los desplegables de select en secciones helpdesk ios', function (): void {
    $css = file_get_contents(dirname(__DIR__, 2).'/resources/css/filament/admin/theme.css');

    expect($css)
        ->toContain('.fi-helpdesk-ios-section .fi-section {')
        ->toContain('overflow: visible;')
        ->toContain('.fi-helpdesk-ios-section .fi-fo-field:has(.fi-fo-select-trigger:focus-within)');
});

it('muestra el equipo en las tablas helpdesk de todos los paneles', function (string $panel): void {
    $path = dirname(__DIR__, 2)."/app/Filament/{$panel}/Resources/Helpdesks/Tables/HelpdesksTable.php";
    $contents = file_get_contents($path);

    expect($contents)->toContain('HelpdeskTableTeamColumns::make()');
})->with(['Business', 'Administration', 'Marketing', 'Operations']);

it('formatea integrantes del equipo con teléfono en columnas compartidas', function (): void {
    $path = dirname(__DIR__, 2).'/app/Support/HelpdeskTableTeamColumns.php';
    $contents = file_get_contents($path);

    expect($contents)->toContain('telefono_corporativo');
});

it('prepara el equipo y notifica por whatsapp al crear ticket en todos los paneles', function (string $panel): void {
    $path = dirname(__DIR__, 2)."/app/Filament/{$panel}/Resources/Helpdesks/Pages/CreateHelpdesk.php";
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('PreparesHelpdeskTeamOnCreate')
        ->toContain('prepareHelpdeskTeamForCreate')
        ->toContain('dispatchHelpdeskCreateNotifications')
        ->toContain('DispatchesHelpdeskCreateNotifications');
})->with(['Business', 'Administration', 'Marketing', 'Operations']);
