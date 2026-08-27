<?php

declare(strict_types=1);

use App\Models\Agency;
use App\Models\Agent;

function isReferidorBasePath(string $path): string
{
    return dirname(__DIR__, 2).'/'.ltrim($path, '/');
}

it('existe migración is_referidor en agencias y agentes', function (): void {
    $files = glob(isReferidorBasePath('database/migrations/*add_is_referidor_to_agencies_and_agents_tables.php'));

    expect($files)->not->toBeEmpty();

    $src = file_get_contents($files[0]);

    expect($src)
        ->toContain("Schema::table('agencies'")
        ->toContain("Schema::table('agents'")
        ->toContain("boolean('is_referidor')")
        ->toContain('->default(false)')
        ->toContain("Schema::hasColumn('agencies', 'is_referidor')")
        ->toContain("Schema::hasColumn('agents', 'is_referidor')");
});

it('expone is_referidor como boolean en agencias y agentes', function (): void {
    expect((new Agency)->getFillable())->toContain('is_referidor')
        ->and((new Agent)->getFillable())->toContain('is_referidor');

    expect(file_get_contents(isReferidorBasePath('app/Models/Agency.php')))
        ->toContain("'is_referidor' => 'boolean'");

    expect(file_get_contents(isReferidorBasePath('app/Models/Agent.php')))
        ->toContain("'is_referidor' => 'boolean'");
});

it('agrega Es Referidor en formularios de agencia y agente', function (): void {
    $forms = [
        'app/Filament/Business/Resources/Agencies/Schemas/AgencyForm.php',
        'app/Filament/Business/Resources/Agents/Schemas/AgentForm.php',
        'app/Filament/Administration/Resources/Agencies/Schemas/AgencyForm.php',
        'app/Filament/Administration/Resources/Agents/Schemas/AgentForm.php',
        'app/Filament/Resources/Agencies/Schemas/AgencyForm.php',
        'app/Filament/Resources/Agents/Schemas/AgentForm.php',
        'app/Filament/Marketing/Resources/Agencies/Schemas/AgencyForm.php',
        'app/Filament/Marketing/Resources/Agents/Schemas/AgentForm.php',
        'app/Filament/Master/Resources/Agencies/Schemas/AgencyForm.php',
        'app/Filament/Master/Resources/Agents/Schemas/AgentForm.php',
        'app/Filament/General/Resources/Agencies/Schemas/AgencyForm.php',
        'app/Filament/General/Resources/Agents/Schemas/AgentForm.php',
        'app/Filament/Agents/Resources/Agents/Schemas/AgentForm.php',
    ];

    foreach ($forms as $form) {
        expect(file_get_contents(isReferidorBasePath($form)))
            ->toContain('ReferidorToggle::make');
    }

    expect(file_get_contents(isReferidorBasePath('app/Filament/Agents/Resources/Agents/Schemas/AgentForm.php')))
        ->toContain('ReferidorToggle::make(forceReadOnly: true)');

    expect(file_get_contents(isReferidorBasePath('app/Filament/Shared/CommercialStructure/ReferidorToggle.php')))
        ->toContain("Toggle::make('is_referidor')")
        ->toContain("->label('Es Referidor')")
        ->toContain('ReferidorAccess::userCanManage()');
});

it('coloca Es Referidor como último campo visible del grupo de identificación', function (): void {
    $forms = [
        'app/Filament/Business/Resources/Agencies/Schemas/AgencyForm.php' => "Select::make('ownerAccountManagers')",
        'app/Filament/Administration/Resources/Agencies/Schemas/AgencyForm.php' => "Select::make('ownerAccountManagers')",
        'app/Filament/Business/Resources/Agents/Schemas/AgentForm.php' => "Select::make('ownerAccountManagers')",
        'app/Filament/Administration/Resources/Agents/Schemas/AgentForm.php' => "Select::make('ownerAccountManagers')",
    ];

    foreach ($forms as $form => $previousField) {
        $src = file_get_contents(isReferidorBasePath($form));
        $togglePos = strpos($src, 'ReferidorToggle::make');
        $percentagePos = strpos($src, 'ReferidorPercentageField::make');
        $previousPos = strpos($src, $previousField);

        expect($togglePos)->toBeInt()->toBeGreaterThan($previousPos)
            ->and($percentagePos)->toBeInt()->toBeGreaterThan($togglePos);
    }
});

it('existe migración referidor_percentage en agencias y agentes', function (): void {
    $files = glob(isReferidorBasePath('database/migrations/*add_referidor_percentage_to_agencies_and_agents_tables.php'));

    expect($files)->not->toBeEmpty();

    $src = file_get_contents($files[0]);

    expect($src)
        ->toContain("Schema::table('agencies'")
        ->toContain("Schema::table('agents'")
        ->toContain("decimal('referidor_percentage', 5, 2)")
        ->toContain('->nullable()')
        ->toContain("Schema::hasColumn('agencies', 'referidor_percentage')")
        ->toContain("Schema::hasColumn('agents', 'referidor_percentage')");
});

it('expone referidor_percentage como decimal en agencias y agentes', function (): void {
    expect((new Agency)->getFillable())->toContain('referidor_percentage')
        ->and((new Agent)->getFillable())->toContain('referidor_percentage');

    expect(file_get_contents(isReferidorBasePath('app/Models/Agency.php')))
        ->toContain("'referidor_percentage' => 'decimal:2'");

    expect(file_get_contents(isReferidorBasePath('app/Models/Agent.php')))
        ->toContain("'referidor_percentage' => 'decimal:2'");
});

it('muestra el porcentaje de referidor justo al activar Es Referidor', function (): void {
    $forms = [
        'app/Filament/Business/Resources/Agencies/Schemas/AgencyForm.php',
        'app/Filament/Business/Resources/Agents/Schemas/AgentForm.php',
        'app/Filament/Administration/Resources/Agencies/Schemas/AgencyForm.php',
        'app/Filament/Administration/Resources/Agents/Schemas/AgentForm.php',
        'app/Filament/Resources/Agencies/Schemas/AgencyForm.php',
        'app/Filament/Resources/Agents/Schemas/AgentForm.php',
        'app/Filament/Marketing/Resources/Agencies/Schemas/AgencyForm.php',
        'app/Filament/Marketing/Resources/Agents/Schemas/AgentForm.php',
        'app/Filament/Master/Resources/Agencies/Schemas/AgencyForm.php',
        'app/Filament/Master/Resources/Agents/Schemas/AgentForm.php',
        'app/Filament/General/Resources/Agencies/Schemas/AgencyForm.php',
        'app/Filament/General/Resources/Agents/Schemas/AgentForm.php',
        'app/Filament/Agents/Resources/Agents/Schemas/AgentForm.php',
    ];

    foreach ($forms as $form) {
        expect(file_get_contents(isReferidorBasePath($form)))
            ->toContain('ReferidorPercentageField::make');
    }

    expect(file_get_contents(isReferidorBasePath('app/Filament/Agents/Resources/Agents/Schemas/AgentForm.php')))
        ->toContain('ReferidorPercentageField::make(forceReadOnly: true)');

    $field = file_get_contents(isReferidorBasePath('app/Filament/Shared/CommercialStructure/ReferidorPercentageField.php'));

    expect($field)
        ->toContain("TextInput::make('referidor_percentage')")
        ->toContain("->label('Porcentaje de referidor')")
        ->toContain('->numeric()')
        ->toContain('->minValue(0)')
        ->toContain('->maxValue(100)')
        ->toContain("->required(fn (Get \$get): bool => (bool) \$get('is_referidor') && ! \$forceReadOnly && ReferidorAccess::userCanManage())")
        ->toContain("->visible(fn (Get \$get): bool => (bool) \$get('is_referidor'))")
        ->toContain('ReferidorAccess::userCanManage()')
        ->toContain('El referidor debe tener un porcentaje asignado.');

    $toggle = file_get_contents(isReferidorBasePath('app/Filament/Shared/CommercialStructure/ReferidorToggle.php'));

    expect($toggle)
        ->toContain("\$set('referidor_percentage', null)");
});

it('muestra el porcentaje de referidor en tablas de agencia y agente', function (): void {
    $tables = [
        'app/Filament/Business/Resources/Agencies/Tables/AgenciesTable.php',
        'app/Filament/Business/Resources/Agents/Tables/AgentsTable.php',
        'app/Filament/Administration/Resources/Agencies/Tables/AgenciesTable.php',
        'app/Filament/Administration/Resources/Agents/Tables/AgentsTable.php',
        'app/Filament/Resources/Agencies/Tables/AgenciesTable.php',
        'app/Filament/Resources/Agents/Tables/AgentsTable.php',
        'app/Filament/Marketing/Resources/Agencies/Tables/AgenciesTable.php',
        'app/Filament/Marketing/Resources/Agents/Tables/AgentsTable.php',
        'app/Filament/Master/Resources/Agencies/Tables/AgenciesTable.php',
        'app/Filament/Master/Resources/Agents/Tables/AgentsTable.php',
        'app/Filament/General/Resources/Agencies/Tables/AgenciesTable.php',
        'app/Filament/General/Resources/Agents/Tables/AgentsTable.php',
        'app/Filament/Agents/Resources/Agents/Tables/AgentsTable.php',
    ];

    foreach ($tables as $table) {
        expect(file_get_contents(isReferidorBasePath($table)))
            ->toContain('ReferidorPercentageField::column()');
    }
});

it('muestra el porcentaje de referidor en infolists de agencia y agente', function (): void {
    $infolists = [
        'app/Filament/Shared/CommercialStructure/AgencyInfolist.php',
        'app/Filament/Shared/CommercialStructure/AgentInfolist.php',
        'app/Filament/Resources/Agencies/Schemas/AgencyInfolist.php',
        'app/Filament/Resources/Agents/Schemas/AgentInfolist.php',
        'app/Filament/Marketing/Resources/Agencies/Schemas/AgencyInfolist.php',
        'app/Filament/Marketing/Resources/Agents/Schemas/AgentInfolist.php',
        'app/Filament/Master/Resources/Agencies/Schemas/AgencyInfolist.php',
        'app/Filament/Master/Resources/Agents/Schemas/AgentInfolist.php',
        'app/Filament/General/Resources/Agencies/Schemas/AgencyInfolist.php',
        'app/Filament/General/Resources/Agents/Schemas/AgentInfolist.php',
        'app/Filament/Agents/Resources/Agents/Schemas/AgentInfolist.php',
    ];

    foreach ($infolists as $infolist) {
        expect(file_get_contents(isReferidorBasePath($infolist)))
            ->toContain('ReferidorPercentageField::entry()');
    }
});

it('agrega Es Referidor en tablas de agencia y agente', function (): void {
    $tables = [
        'app/Filament/Business/Resources/Agencies/Tables/AgenciesTable.php',
        'app/Filament/Business/Resources/Agents/Tables/AgentsTable.php',
        'app/Filament/Administration/Resources/Agencies/Tables/AgenciesTable.php',
        'app/Filament/Administration/Resources/Agents/Tables/AgentsTable.php',
        'app/Filament/Resources/Agencies/Tables/AgenciesTable.php',
        'app/Filament/Resources/Agents/Tables/AgentsTable.php',
        'app/Filament/Marketing/Resources/Agencies/Tables/AgenciesTable.php',
        'app/Filament/Marketing/Resources/Agents/Tables/AgentsTable.php',
        'app/Filament/Master/Resources/Agencies/Tables/AgenciesTable.php',
        'app/Filament/Master/Resources/Agents/Tables/AgentsTable.php',
        'app/Filament/General/Resources/Agencies/Tables/AgenciesTable.php',
        'app/Filament/General/Resources/Agents/Tables/AgentsTable.php',
        'app/Filament/Agents/Resources/Agents/Tables/AgentsTable.php',
    ];

    foreach ($tables as $table) {
        expect(file_get_contents(isReferidorBasePath($table)))
            ->toContain("IconColumn::make('is_referidor')")
            ->toContain("->label('Es Referidor')");
    }
});

it('filtra Es Referidor en tablas internas de agencias y agentes', function (): void {
    $tables = [
        'app/Filament/Business/Resources/Agencies/Tables/AgenciesTable.php',
        'app/Filament/Business/Resources/Agents/Tables/AgentsTable.php',
        'app/Filament/Administration/Resources/Agencies/Tables/AgenciesTable.php',
        'app/Filament/Administration/Resources/Agents/Tables/AgentsTable.php',
    ];

    foreach ($tables as $table) {
        expect(file_get_contents(isReferidorBasePath($table)))
            ->toContain("TernaryFilter::make('is_referidor')");
    }
});

it('agrega Es Referidor en infolists de agencia y agente', function (): void {
    $infolists = [
        'app/Filament/Shared/CommercialStructure/AgencyInfolist.php',
        'app/Filament/Shared/CommercialStructure/AgentInfolist.php',
        'app/Filament/Resources/Agencies/Schemas/AgencyInfolist.php',
        'app/Filament/Resources/Agents/Schemas/AgentInfolist.php',
        'app/Filament/Marketing/Resources/Agencies/Schemas/AgencyInfolist.php',
        'app/Filament/Marketing/Resources/Agents/Schemas/AgentInfolist.php',
        'app/Filament/Master/Resources/Agencies/Schemas/AgencyInfolist.php',
        'app/Filament/Master/Resources/Agents/Schemas/AgentInfolist.php',
        'app/Filament/General/Resources/Agencies/Schemas/AgencyInfolist.php',
        'app/Filament/General/Resources/Agents/Schemas/AgentInfolist.php',
        'app/Filament/Agents/Resources/Agents/Schemas/AgentInfolist.php',
    ];

    foreach ($infolists as $infolist) {
        expect(file_get_contents(isReferidorBasePath($infolist)))
            ->toContain("IconEntry::make('is_referidor')")
            ->toContain("->label('Es Referidor')");
    }
});
