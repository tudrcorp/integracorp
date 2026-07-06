<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

use App\Filament\Business\Resources\BusinessAppointments\Schemas\BusinessAppointmentsForm;
use App\Filament\Business\Resources\BusinessAppointments\Schemas\BusinessAppointmentsInfolist;
use Filament\Schemas\Schema;

it('configura el formulario BusinessAppointments sin error', function (): void {
    $schema = Schema::make();
    $configured = BusinessAppointmentsForm::configure($schema);

    expect($configured)->toBeInstanceOf(Schema::class);
});

it('formulario BusinessAppointments usa pestañas con estilos del sistema', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/BusinessAppointments/Schemas/BusinessAppointmentsForm.php');

    expect($source)
        ->toContain("Tabs::make('businessAppointmentsFormTabs')")
        ->toContain('persistTab')
        ->toContain('TABS_CONTAINER')
        ->toContain('IOS_SECTION_CLASS')
        ->toContain('IOS_INNER_CLASS')
        ->toContain("Tab::make('Datos de la cita')")
        ->toContain("Tab::make('Ubicación')")
        ->toContain("Tab::make('Estado de la cita')");
});

it('configura el infolist BusinessAppointments sin error', function (): void {
    $schema = Schema::make();
    $configured = BusinessAppointmentsInfolist::configure($schema);

    expect($configured)->toBeInstanceOf(Schema::class);
});

it('infolist BusinessAppointments usa pestañas con estilos del sistema', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/BusinessAppointments/Schemas/BusinessAppointmentsInfolist.php');

    expect($source)
        ->toContain("Tabs::make('businessAppointmentsInfolistTabs')")
        ->toContain('persistTab')
        ->toContain('TABS_CONTAINER')
        ->toContain('IOS_SECTION_CLASS')
        ->toContain('IOS_INNER_CLASS')
        ->toContain("Tab::make('Resumen de la cita')")
        ->toContain("Tab::make('Observaciones')")
        ->toContain("Tab::make('Auditoría')");
});

it('pagina ver cita usa botones con estilo iOS', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/BusinessAppointments/Pages/ViewBusinessAppointments.php');

    expect($source)
        ->toContain('IOS_GRAY_BUTTON_CLASS')
        ->toContain('IOS_SUCCESS_BUTTON_CLASS')
        ->toContain('ticket-btn-ios-gray')
        ->toContain('aviso-btn-ios-success')
        ->toContain('modalSubmitAction');
});

it('pagina editar cita usa botones con estilo iOS', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/BusinessAppointments/Pages/EditBusinessAppointments.php');

    expect($source)
        ->toContain('IOS_DANGER_BUTTON_CLASS')
        ->toContain('IOS_PRIMARY_BUTTON_CLASS')
        ->toContain('aviso-btn-ios-danger')
        ->toContain('aviso-btn-ios-primary')
        ->toContain('ViewAction::make()')
        ->toContain('DeleteAction::make()');
});
