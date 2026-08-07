<?php

declare(strict_types=1);

use App\Support\HelpdeskTeamMembersPayload;

it('devuelve arreglo vacío cuando no hay ids de colaboradores', function (): void {
    expect(HelpdeskTeamMembersPayload::fromColaboradorIds([]))->toBe([]);
});

it('ya no expone la seccion de equipo de ejecucion en el formulario helpdesk', function (): void {
    $path = dirname(__DIR__, 2).'/app/Support/HelpdeskFormSchema.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("Tabs::make('helpdeskFormTabs')")
        ->not->toContain("Tab::make('Equipo de ejecución')")
        ->not->toContain('executionTeamTabSchema')
        ->not->toContain("TextInput::make('team')");
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

it('no muestra columnas de equipo de ejecucion en tablas helpdesk', function (string $panel): void {
    $path = dirname(__DIR__, 2)."/app/Filament/{$panel}/Resources/Helpdesks/Tables/HelpdesksTable.php";
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('HelpdeskTableConfigurator::configure')
        ->not->toContain('HelpdeskTableTeamColumns');
})->with(['Business', 'Administration', 'Marketing', 'Operations']);

it('elimina el helper muerto de columnas de equipo', function (): void {
    expect(file_exists(dirname(__DIR__, 2).'/app/Support/HelpdeskTableTeamColumns.php'))->toBeFalse();
});

it('crea tickets sin preparar equipo de ejecucion en todos los paneles', function (string $panel): void {
    $path = dirname(__DIR__, 2)."/app/Filament/{$panel}/Resources/Helpdesks/Pages/CreateHelpdesk.php";
    $contents = file_get_contents($path);

    expect($contents)
        ->not->toContain('PreparesHelpdeskTeamOnCreate')
        ->not->toContain('prepareHelpdeskTeamForCreate')
        ->toContain('dispatchHelpdeskCreateNotifications')
        ->toContain('DispatchesHelpdeskCreateNotifications');
})->with(['Business', 'Administration', 'Marketing', 'Operations']);
