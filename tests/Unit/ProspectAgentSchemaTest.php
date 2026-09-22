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
        ->toContain("Tab::make('Segmentación')")
        ->toContain("TextInput::make('website')")
        ->toContain("Textarea::make('social_networks')")
        ->toContain("Textarea::make('address')")
        ->toContain("Repeater::make('prospectAgentContacts')")
        ->toContain('->maxItems(3)')
        ->toContain("->addActionLabel('Agregar contacto')")
        ->toContain('discardEmptyContact');
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
        ->toContain("Tab::make('Auditoría')")
        ->toContain("TextEntry::make('website')")
        ->toContain("TextEntry::make('social_networks')")
        ->toContain("TextEntry::make('address')")
        ->toContain("RepeatableEntry::make('prospectAgentContacts')");
});

it('el modal de notas del prospecto deja la tarea opcional y la nota en texto largo', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/ProspectAgents/Pages/ViewProspectAgent.php');
    $migration = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_09_22_115155_change_observation_to_longtext_on_prospect_agent_observations_table.php');
    $infolist = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/ProspectAgents/Schemas/ProspectAgentInfolist.php');

    expect($source)
        ->toContain("Select::make('prospect_agent_task_id')")
        ->toContain('->nullable()')
        ->toContain('prospectTaskOptions()')
        ->toContain("->where('prospect_agent_id', \$this->record->getKey())")
        ->toContain("Textarea::make('observations')")
        ->toContain('->rows(8)')
        ->not->toContain("where('status', 'PENDIENTE')");

    expect($migration)
        ->toContain("\$table->longText('observation')->nullable(false)->change()");

    expect($infolist)
        ->toContain("TextEntry::make('observation')")
        ->toContain('->wrap()')
        ->not->toContain('->limit(120)');
});

it('el listado de prospectos titula la captación de Tu Doctor Group', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/ProspectAgents/Pages/ListProspectAgents.php');

    expect($source)
        ->toContain("protected static ?string \$title = 'Captación de Tu Doctor Group';")
        ->toContain('function getSubheading()')
        ->toContain('Prospectos de la red comercial.')
        ->not->toContain('Prospectos TuDrGroup');
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
