<?php

declare(strict_types=1);

use App\Models\Agency;
use App\Models\AgencyType;
use App\Models\Agent;
use App\Models\AgentType;
use App\Support\Filament\CommercialStructurePageHeader;

it('arma el encabezado de edición de agencia con los datos principales', function (): void {
    $agency = new Agency([
        'code' => 'TDG-201',
        'name_corporative' => 'AGENCIA DEMO C.A.',
        'status' => 'ACTIVO',
        'email' => 'agencia@tudrencasa.com',
        'phone' => '04141234567',
        'rif' => '123456789',
        'is_referidor' => true,
    ]);
    $agency->setRelation('typeAgency', new AgencyType(['definition' => 'MASTER']));

    $html = (string) CommercialStructurePageHeader::forAgency($agency, context: 'edit');

    expect($html)
        ->toContain('Editar agencia · TDG-201')
        ->toContain('AGENCIA DEMO C.A.')
        ->toContain('ACTIVO')
        ->toContain('MASTER')
        ->toContain('Referidor')
        ->toContain('agencia@tudrencasa.com')
        ->toContain('04141234567')
        ->toContain('RIF: 123456789')
        ->not->toContain('Editar Informacion de la Agencias');
});

it('oculta la etiqueta de referidor cuando la agencia no lo es', function (): void {
    $agency = new Agency([
        'code' => 'TDG-100',
        'name_corporative' => 'CASA MATRIZ',
        'status' => 'ACTIVO',
        'is_referidor' => false,
    ]);
    $agency->setRelation('typeAgency', new AgencyType(['definition' => 'MASTER']));

    $html = (string) CommercialStructurePageHeader::forAgency($agency);

    expect($html)
        ->toContain('Agencia · TDG-100')
        ->not->toContain('Referidor')
        ->not->toContain('Editar agencia');
});

it('escapa html peligroso en el encabezado de agencia', function (): void {
    $agency = new Agency([
        'code' => 'TDG-999',
        'name_corporative' => '<script>alert(1)</script>',
        'status' => 'INACTIVO',
        'email' => '<b>mail</b>',
    ]);
    $agency->setRelation('typeAgency', null);

    $html = (string) CommercialStructurePageHeader::forAgency($agency, context: 'edit');

    expect($html)
        ->toContain('&lt;script&gt;alert(1)&lt;/script&gt;')
        ->not->toContain('<script>alert(1)</script>')
        ->toContain('INACTIVO')
        ->toContain('Sin tipo');
});

it('arma el encabezado de edición de agente con código, tipo y agencia', function (): void {
    $agent = new Agent([
        'code_agent' => 'TDG-201-01',
        'name' => 'JUAN PEREZ',
        'status' => 'POR REVISION',
        'email' => 'juan@tudrencasa.com',
        'phone' => '04249876543',
        'ci' => '12345678',
        'owner_code' => 'TDG-201',
        'is_referidor' => false,
    ]);
    $agent->setRelation('typeAgent', new AgentType(['definition' => 'AGENTE']));

    $html = (string) CommercialStructurePageHeader::forAgent($agent, context: 'edit');

    expect($html)
        ->toContain('Editar agente · TDG-201-01')
        ->toContain('JUAN PEREZ')
        ->toContain('POR REVISION')
        ->toContain('AGENTE')
        ->toContain('juan@tudrencasa.com')
        ->toContain('04249876543')
        ->toContain('C.I.: 12345678')
        ->toContain('Agencia: TDG-201')
        ->not->toContain('Referidor')
        ->not->toContain('Editar Informacio del Agente');
});

it('usa fallbacks cuando el agente no tiene datos principales', function (): void {
    $agent = new Agent;
    $agent->setRelation('typeAgent', null);

    $html = (string) CommercialStructurePageHeader::forAgent($agent);

    expect($html)
        ->toContain('Agente · Sin código')
        ->toContain('Sin nombre')
        ->toContain('SIN ESTADO')
        ->toContain('Sin tipo')
        ->toContain('Sin correo')
        ->toContain('Sin teléfono');
});

it('la edición de agencia en administración usa el encabezado compartido', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Agencies/Pages/EditAgency.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('public function getTitle(): string|Htmlable')
        ->toContain("CommercialStructurePageHeader::forAgency(\$agency, context: 'edit')")
        ->toContain("Action::make('back')")
        ->toContain("->label('Volver')")
        ->toContain("AgencyResource::getUrl('view'")
        ->not->toContain("protected static ?string \$title = 'Editar Informacion de la Agencias'");
});

it('la edición de agente en administración usa el encabezado compartido', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Agents/Pages/EditAgent.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('public function getTitle(): string|Htmlable')
        ->toContain("CommercialStructurePageHeader::forAgent(\$agent, context: 'edit')")
        ->toContain("Action::make('back')")
        ->toContain("->label('Volver')")
        ->toContain("AgentResource::getUrl('view'")
        ->not->toContain("protected static ?string \$title = 'Editar Informacio del Agente'");
});

it('las fichas de administración reutilizan el mismo encabezado', function (): void {
    $agency = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Agencies/Pages/ViewAgency.php');
    $agent = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Agents/Pages/ViewAgent.php');

    expect($agency)
        ->toContain('CommercialStructurePageHeader::forAgency($agency)')
        ->and($agent)
        ->toContain('CommercialStructurePageHeader::forAgent($agent)');
});

it('la edición de agencia en negocios usa el encabezado compartido', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Agencies/Pages/EditAgency.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('public function getTitle(): string|Htmlable')
        ->toContain("CommercialStructurePageHeader::forAgency(\$agency, context: 'edit')")
        ->toContain("Action::make('back')")
        ->toContain("->label('Volver')")
        ->toContain("AgencyResource::getUrl('view'")
        ->toContain('AUDIT_BUSINESS_AGENCY_UPDATED')
        ->not->toContain('Formularios de edicio');
});

it('la edición de agente en negocios usa el encabezado compartido', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Agents/Pages/EditAgent.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('public function getTitle(): string|Htmlable')
        ->toContain("CommercialStructurePageHeader::forAgent(\$agent, context: 'edit')")
        ->toContain("Action::make('back')")
        ->toContain("->label('Volver')")
        ->toContain("AgentResource::getUrl('view'")
        ->toContain('AUDIT_BUSINESS_AGENT_UPDATED')
        ->not->toContain('Formularios de edicio');
});
