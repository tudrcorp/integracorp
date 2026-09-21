<?php

declare(strict_types=1);

use App\Models\Agency;
use App\Models\AgencyType;
use App\Models\Agent;
use App\Models\ReferidorAssignment;
use App\Support\CommercialStructure\ReferidorAssignmentException;
use App\Support\CommercialStructure\ReferidorAssignmentService;
use Illuminate\Support\Collection;

function referidorAssignmentBasePath(string $path): string
{
    return dirname(__DIR__, 2).'/'.ltrim($path, '/');
}

it('existe migración referidor_id en agencias y agentes', function (): void {
    $files = glob(referidorAssignmentBasePath('database/migrations/*add_referidor_id_to_agencies_and_agents_tables.php'));

    expect($files)->not->toBeEmpty();

    $src = file_get_contents($files[0]);

    expect($src)
        ->toContain("foreignId('referidor_id')")
        ->toContain("constrained('agencies')")
        ->toContain('nullOnDelete()')
        ->toContain("Schema::hasColumn('agencies', 'referidor_id')")
        ->toContain("Schema::hasColumn('agents', 'referidor_id')");
});

it('expone referidor_id y relaciones en agencias y agentes', function (): void {
    expect((new Agency)->getFillable())->toContain('referidor_id')
        ->and((new Agency)->getFillable())->toContain('referidor_agent_id')
        ->and((new Agent)->getFillable())->toContain('referidor_id')
        ->and((new Agent)->getFillable())->toContain('referidor_agent_id');

    $agency = file_get_contents(referidorAssignmentBasePath('app/Models/Agency.php'));
    $agent = file_get_contents(referidorAssignmentBasePath('app/Models/Agent.php'));

    expect($agency)
        ->toContain('function referidor()')
        ->toContain('function referidorAgent()')
        ->toContain('function referredGeneralAgencies()')
        ->toContain('function referredAgents()')
        ->toContain('function referrerAgencies()')
        ->toContain('function referrerAgents()')
        ->toContain("'referidor_assignments'")
        ->not->toContain("where('agency_type_id', '!=', 1)");

    expect($agent)
        ->toContain('function referidor()')
        ->toContain('function referidorAgent()')
        ->toContain('function referredGeneralAgencies()')
        ->toContain('function referredAgents()')
        ->toContain('function referrerAgencies()')
        ->toContain('function referrerAgents()')
        ->toContain("'referidor_assignments'")
        ->not->toContain("where('agency_type_id', '!=', 1)");
});

it('normaliza captura y limpia campos virtuales de asignación', function (): void {
    $data = [
        'is_referidor' => true,
        'agency_type_id' => 1,
        ReferidorAssignmentService::GENERAL_AGENCY_IDS_FIELD => ['12', 12, 0, '', null, 18],
        ReferidorAssignmentService::AGENT_IDS_FIELD => [7, '7', 9],
        'name_corporative' => 'MASTER DEMO',
    ];

    $captured = ReferidorAssignmentService::capture($data);

    expect($captured['general_agency_ids'])->toBe([12, 18])
        ->and($captured['agent_ids'])->toBe([7, 9]);

    $stripped = ReferidorAssignmentService::strip($data);

    expect($stripped)->not->toHaveKey(ReferidorAssignmentService::GENERAL_AGENCY_IDS_FIELD)
        ->and($stripped)->not->toHaveKey(ReferidorAssignmentService::AGENT_IDS_FIELD)
        ->and($stripped['is_referidor'])->toBeTrue()
        ->and($stripped['name_corporative'])->toBe('MASTER DEMO');
});

it('reconoce agencias master y generales referidoras', function (): void {
    $masterReferrer = (new Agency)->forceFill([
        'agency_type_id' => 1,
        'is_referidor' => true,
    ]);
    $masterPlain = (new Agency)->forceFill([
        'agency_type_id' => 1,
        'is_referidor' => false,
    ]);
    $generalReferrer = (new Agency)->forceFill([
        'agency_type_id' => 2,
        'is_referidor' => true,
    ]);
    $generalPlain = (new Agency)->forceFill([
        'agency_type_id' => 2,
        'is_referidor' => false,
    ]);

    expect(ReferidorAssignmentService::isReferrerAgency($masterReferrer))->toBeTrue()
        ->and(ReferidorAssignmentService::isReferrerAgency($masterPlain))->toBeFalse()
        ->and(ReferidorAssignmentService::isReferrerAgency($generalReferrer))->toBeTrue()
        ->and(ReferidorAssignmentService::isReferrerAgency($generalPlain))->toBeFalse();

    $service = file_get_contents(referidorAssignmentBasePath('app/Support/CommercialStructure/ReferidorAssignmentService.php'));

    expect($service)
        ->toContain('whereKeyNot($referrer->id)')
        ->toContain('fn (int $id): bool => $id !== $referrerId')
        ->toContain('ReferidorAssignment::query()')
        ->toContain('forReferrer($referrer)')
        ->not->toContain('applyAssignableOwnerConstraint')
        ->not->toContain('MASTER_AGENCY_TYPE_ID')
        ->not->toContain("where('agency_type_id', '!=', self::MASTER_AGENCY_TYPE_ID)");
});

it('arma una clave única por par referidor-referido', function (): void {
    $referrer = (new Agency)->forceFill(['id' => 5]);
    $referredAgency = (new Agency)->forceFill(['id' => 12]);
    $referredAgent = (new Agent)->forceFill(['id' => 8]);

    expect(ReferidorAssignment::keyFor($referrer, $referredAgency))->toBe('agency:5|agency:12')
        ->and(ReferidorAssignment::keyFor($referrer, $referredAgent))->toBe('agency:5|agent:8');
});

it('lista varios referidores de un mismo referido', function (): void {
    $first = (new Agency)->forceFill([
        'id' => 1,
        'code' => 'TDG-201',
        'name_corporative' => 'Referidor A',
        'status' => 'ACTIVO',
    ]);
    $first->setRelation('typeAgency', new AgencyType(['definition' => 'MASTER']));

    $second = (new Agent)->forceFill([
        'id' => 4,
        'name' => 'Referidor E',
        'code_agent' => 'AGT-004',
    ]);
    $second->setRelation('typeAgent', null);

    $referred = (new Agency)->forceFill(['id' => 20, 'name_corporative' => 'Referido B']);
    $referred->setRelation('referrerAgencies', new Collection([$first]));
    $referred->setRelation('referrerAgents', new Collection([$second]));

    expect(ReferidorAssignmentService::hasAssignedReferrer($referred))->toBeTrue()
        ->and(ReferidorAssignmentService::assignedReferrerLabel($referred))
        ->toContain('AGT-004 — Referidor E')
        ->toContain('TDG-201 — Referidor A (MASTER) (ACTIVO)');
});

it('etiqueta agencias master y generales sin ocultar el tipo', function (): void {
    $master = (new Agency)->forceFill([
        'id' => 10,
        'code' => 'TDG-201',
        'name_corporative' => 'Casa Master',
        'status' => 'ACTIVO',
        'agency_type_id' => 1,
    ]);
    $master->setRelation('typeAgency', new AgencyType(['definition' => 'MASTER']));

    $general = (new Agency)->forceFill([
        'id' => 11,
        'code' => 'TDG-310',
        'name_corporative' => 'Red General',
        'status' => 'ACTIVO',
        'agency_type_id' => 2,
    ]);
    $general->setRelation('typeAgency', new AgencyType(['definition' => 'GENERAL']));

    expect(ReferidorAssignmentService::generalAgencyLabel($master))
        ->toBe('TDG-201 — Casa Master (MASTER) (ACTIVO)')
        ->and(ReferidorAssignmentService::generalAgencyLabel($general))
        ->toBe('TDG-310 — Red General (GENERAL) (ACTIVO)');
});

it('describe excepciones de asignación sin restringir a agencias generales', function (): void {
    expect(ReferidorAssignmentException::notReferrerAgency()->getMessage())
        ->toBe('Solo una agencia o un agente marcado como referidor puede asignar agencias o agentes.')
        ->and(ReferidorAssignmentException::agencyNotAssignable()->getMessage())
        ->toBe('Una o más agencias no están disponibles para este referidor. Recargue el formulario e intente de nuevo.');
});

it('existe migración referidor_agent_id en agencias y agentes', function (): void {
    $files = glob(referidorAssignmentBasePath('database/migrations/*add_referidor_agent_id_to_agencies_and_agents_tables.php'));

    expect($files)->not->toBeEmpty();

    $src = file_get_contents($files[0]);

    expect($src)
        ->toContain("foreignId('referidor_agent_id')")
        ->toContain("constrained('agents')")
        ->toContain('nullOnDelete()')
        ->toContain("Schema::hasColumn('agencies', 'referidor_agent_id')")
        ->toContain("Schema::hasColumn('agents', 'referidor_agent_id')");
});

it('existe migración de asignaciones múltiples de referidor', function (): void {
    $files = glob(referidorAssignmentBasePath('database/migrations/*create_referidor_assignments_table.php'));

    expect($files)->not->toBeEmpty();

    $src = file_get_contents($files[0]);

    expect($src)
        ->toContain("Schema::hasTable('referidor_assignments')")
        ->toContain("unique('assignment_key', 'ref_asg_key_unique')")
        ->toContain("foreignId('referrer_agency_id')")
        ->toContain("foreignId('referrer_agent_id')")
        ->toContain("foreignId('referred_agency_id')")
        ->toContain("foreignId('referred_agent_id')")
        ->toContain('backfillFromLegacyColumns');
});

it('reconoce agentes y subagentes referidores', function (): void {
    $agentReferrer = (new Agent)->forceFill([
        'agent_type_id' => 2,
        'is_referidor' => true,
    ]);
    $agentPlain = (new Agent)->forceFill([
        'agent_type_id' => 2,
        'is_referidor' => false,
    ]);
    $subAgentReferrer = (new Agent)->forceFill([
        'agent_type_id' => 3,
        'is_referidor' => true,
    ]);

    expect(ReferidorAssignmentService::isReferrerAgent($agentReferrer))->toBeTrue()
        ->and(ReferidorAssignmentService::isReferrerAgent($agentPlain))->toBeFalse()
        ->and(ReferidorAssignmentService::isReferrerAgent($subAgentReferrer))->toBeTrue()
        ->and(ReferidorAssignmentService::isReferrer($agentReferrer))->toBeTrue();
});

it('agrega dos selects múltiples de referidor en formularios internos de agente', function (): void {
    $forms = [
        'app/Filament/Business/Resources/Agents/Schemas/AgentForm.php',
        'app/Filament/Administration/Resources/Agents/Schemas/AgentForm.php',
        'app/Filament/Resources/Agents/Schemas/AgentForm.php',
        'app/Filament/Master/Resources/Agents/Schemas/AgentForm.php',
        'app/Filament/General/Resources/Agents/Schemas/AgentForm.php',
    ];

    foreach ($forms as $form) {
        $src = file_get_contents(referidorAssignmentBasePath($form));

        expect($src)
            ->toContain('ReferidorAssignmentFields::section')
            ->toContain('ReferidorToggle::make');
    }
});

it('muestra la red de referidor en fichas de agente', function (): void {
    $infolists = [
        'app/Filament/Shared/CommercialStructure/AgentInfolist.php',
        'app/Filament/Master/Resources/Agents/Schemas/AgentInfolist.php',
        'app/Filament/General/Resources/Agents/Schemas/AgentInfolist.php',
    ];

    foreach ($infolists as $infolist) {
        expect(file_get_contents(referidorAssignmentBasePath($infolist)))
            ->toContain("->label('Agencias referidas')")
            ->toContain("->label('Referidores')")
            ->toContain('ReferidorAssignmentService::isReferrerAgent');
    }
});

it('agrega dos selects múltiples de referidor en formularios internos de agencia', function (): void {
    $forms = [
        'app/Filament/Business/Resources/Agencies/Schemas/AgencyForm.php',
        'app/Filament/Administration/Resources/Agencies/Schemas/AgencyForm.php',
        'app/Filament/Resources/Agencies/Schemas/AgencyForm.php',
        'app/Filament/Master/Resources/Agencies/Schemas/AgencyForm.php',
        'app/Filament/General/Resources/Agencies/Schemas/AgencyForm.php',
    ];

    foreach ($forms as $form) {
        $src = file_get_contents(referidorAssignmentBasePath($form));

        expect($src)
            ->toContain('ReferidorAssignmentFields::section')
            ->toContain('ReferidorToggle::make');
    }

    $fields = file_get_contents(referidorAssignmentBasePath('app/Filament/Shared/CommercialStructure/ReferidorAssignmentFields.php'));

    expect($fields)
        ->toContain('Select::make(ReferidorAssignmentService::GENERAL_AGENCY_IDS_FIELD)')
        ->toContain('Select::make(ReferidorAssignmentService::AGENT_IDS_FIELD)')
        ->toContain('->multiple()')
        ->toContain("->label('Agencias')")
        ->toContain("->label('Agentes y subagentes')")
        ->toContain('Un referido puede tener varios referidores')
        ->toContain('A puede referir a B, C y D')
        ->toContain("return (bool) \$get('is_referidor') && ReferidorAccess::userCanManage();")
        ->not->toContain('agency_type_id');
});

it('muestra la red de referidor en fichas de agencia', function (): void {
    $infolists = [
        'app/Filament/Shared/CommercialStructure/AgencyInfolist.php',
        'app/Filament/Master/Resources/Agencies/Schemas/AgencyInfolist.php',
        'app/Filament/General/Resources/Agencies/Schemas/AgencyInfolist.php',
    ];

    foreach ($infolists as $infolist) {
        expect(file_get_contents(referidorAssignmentBasePath($infolist)))
            ->toContain("->label('Agencias referidas')")
            ->toContain("->label('Referidores')")
            ->toContain('ReferidorAssignmentService::isReferrerAgency');
    }
});

it('persiste asignaciones de referidor al crear y editar agencias internas', function (): void {
    $pages = [
        'app/Filament/Business/Resources/Agencies/Pages/EditAgency.php',
        'app/Filament/Business/Resources/Agencies/Pages/CreateAgency.php',
        'app/Filament/Administration/Resources/Agencies/Pages/EditAgency.php',
        'app/Filament/Administration/Resources/Agencies/Pages/CreateAgency.php',
        'app/Filament/Resources/Agencies/Pages/EditAgency.php',
        'app/Filament/Resources/Agencies/Pages/CreateAgency.php',
        'app/Filament/Master/Resources/Agencies/Pages/EditAgency.php',
        'app/Filament/Master/Resources/Agencies/Pages/CreateAgency.php',
        'app/Filament/General/Resources/Agencies/Pages/EditAgency.php',
        'app/Filament/General/Resources/Agencies/Pages/CreateAgency.php',
        'app/Filament/Business/Resources/Agents/Pages/EditAgent.php',
        'app/Filament/Business/Resources/Agents/Pages/CreateAgent.php',
        'app/Filament/Administration/Resources/Agents/Pages/EditAgent.php',
        'app/Filament/Administration/Resources/Agents/Pages/CreateAgent.php',
        'app/Filament/Resources/Agents/Pages/EditAgent.php',
        'app/Filament/Resources/Agents/Pages/CreateAgent.php',
        'app/Filament/Master/Resources/Agents/Pages/EditAgent.php',
        'app/Filament/Master/Resources/Agents/Pages/CreateAgent.php',
        'app/Filament/General/Resources/Agents/Pages/EditAgent.php',
        'app/Filament/General/Resources/Agents/Pages/CreateAgent.php',
    ];

    foreach ($pages as $page) {
        expect(file_get_contents(referidorAssignmentBasePath($page)))
            ->toContain('use SyncsReferidorAssignments')
            ->toContain('captureReferidorAssignments')
            ->toContain('persistCapturedReferidorAssignments');
    }
});

it('exige el permiso de gestionar referidor para el check y la red', function (): void {
    $toggle = file_get_contents(referidorAssignmentBasePath('app/Filament/Shared/CommercialStructure/ReferidorToggle.php'));
    $access = file_get_contents(referidorAssignmentBasePath('app/Support/CommercialStructure/ReferidorAccess.php'));
    $trait = file_get_contents(referidorAssignmentBasePath('app/Filament/Shared/CommercialStructure/Concerns/SyncsReferidorAssignments.php'));
    $registry = file_get_contents(referidorAssignmentBasePath('app/Support/Filament/BusinessFilamentActionPermissionRegistry.php'));

    expect($access)
        ->toContain("PERMISSION_SLUG = 'gestionar-referidor'")
        ->toContain('defined($constant)')
        ->toContain('UserNavigationAccess::canPerformModuleAction')
        ->toContain("'ADMINISTRACION'")
        ->not->toContain('BusinessFilamentActionPermissionRegistry::MANAGE_REFERIDOR,');

    expect($toggle)
        ->toContain('->disabled(fn (): bool => $forceReadOnly || ! ReferidorAccess::userCanManage())')
        ->toContain('->dehydrated(fn (): bool => ! $forceReadOnly && ReferidorAccess::userCanManage())');

    expect($trait)
        ->toContain('if (! ReferidorAccess::userCanManage())')
        ->toContain('unset($data[\'referidor_percentage\'])')
        ->toContain('ReferidorPercentageField::normalizeFormData($data)')
        ->toContain('return ReferidorAssignmentService::strip($data);');

    expect($registry)
        ->toContain("MANAGE_REFERIDOR = 'gestionar-referidor'")
        ->toContain("'name' => 'Asignación de referidor'")
        ->toContain("'group' => 'ESTRUCTURA COMERCIAL'");
});

it('resuelve el slug de referidor sin reventar si falta la constante', function (): void {
    expect(\App\Support\CommercialStructure\ReferidorAccess::permissionSlug())->toBe('gestionar-referidor')
        ->and(\App\Support\CommercialStructure\ReferidorAccess::userCanManage())->toBeFalse();
});
