<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

use App\Filament\Business\Resources\ProspectAgents\Schemas\ProspectAgentForm;
use App\Filament\Business\Resources\ProspectAgents\Schemas\ProspectAgentInfolist;
use Filament\Schemas\Schema;

it('configura el formulario ProspectAgent sin error', function (): void {
    $schema = Schema::make();
    $configured = ProspectAgentForm::configure($schema);

    expect($configured)->toBeInstanceOf(Schema::class);
});

it('formulario ProspectAgent usa pestañas con estilos del sistema', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/ProspectAgents/Schemas/ProspectAgentForm.php');

    expect($source)
        ->toContain("Tabs::make('prospectAgentFormTabs')")
        ->toContain('persistTab')
        ->toContain('TABS_CONTAINER')
        ->toContain('IOS_SECTION_CLASS')
        ->toContain('IOS_INNER_CLASS')
        ->toContain("Tab::make('Datos del prospecto')")
        ->toContain("Tab::make('Embudo comercial')")
        ->toContain("Tab::make('Ubicación')")
        ->toContain("Tab::make('Segmentación')");
});

it('configura el infolist ProspectAgent sin error', function (): void {
    $schema = Schema::make();
    $configured = ProspectAgentInfolist::configure($schema);

    expect($configured)->toBeInstanceOf(Schema::class);
});

it('infolist ProspectAgent usa pestañas con estilos del sistema', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/ProspectAgents/Schemas/ProspectAgentInfolist.php');

    expect($source)
        ->toContain("Tabs::make('prospectAgentInfolistTabs')")
        ->toContain('persistTab')
        ->toContain('TABS_CONTAINER')
        ->toContain('IOS_SECTION_CLASS')
        ->toContain('IOS_INNER_CLASS')
        ->toContain("Tab::make('Información del prospecto')")
        ->toContain("Tab::make('Contacto')")
        ->toContain("Tab::make('Seguimiento')")
        ->toContain("Tab::make('Auditoría')");
});

it('pagina ver prospecto usa botones con estilo iOS', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/ProspectAgents/Pages/ViewProspectAgent.php');

    expect($source)
        ->toContain('IOS_GRAY_BUTTON_CLASS')
        ->toContain('IOS_PRIMARY_BUTTON_CLASS')
        ->toContain('IOS_SUCCESS_BUTTON_CLASS')
        ->toContain('ticket-btn-ios-gray')
        ->toContain('aviso-btn-ios-primary')
        ->toContain('aviso-btn-ios-success')
        ->toContain('modalSubmitAction');
});

it('pagina editar prospecto usa botones con estilo iOS', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/ProspectAgents/Pages/EditProspectAgent.php');

    expect($source)
        ->toContain('IOS_DANGER_BUTTON_CLASS')
        ->toContain('IOS_PRIMARY_BUTTON_CLASS')
        ->toContain('aviso-btn-ios-danger')
        ->toContain('aviso-btn-ios-primary')
        ->toContain('ViewAction::make()')
        ->toContain('DeleteAction::make()');
});
