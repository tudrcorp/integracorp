<?php

declare(strict_types=1);

use App\Enums\AdministrationReportType;
use App\Filament\Exports\CommissionExporter;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\Commission;
use App\Support\Administration\Reports\CollectionReportBuilder;
use App\Support\Administration\Reports\CollectionReportFilters;
use App\Support\Administration\Reports\CommissionsDetailReport;
use Carbon\CarbonImmutable;

uses(Tests\TestCase::class);

/**
 * Comisión en memoria (sin base de datos) con su agencia y su agente ya cargados.
 */
function commissionWithStructure(?Agency $agency, ?Agent $agent): Commission
{
    $commission = new Commission(['code' => '11-000001', 'code_agency' => $agency?->code]);
    $commission->setRelation('agency', $agency);
    $commission->setRelation('agent', $agent);

    return $commission;
}

it('el subagente muestra a su agente responsable como agente y a sí mismo como subagente', function (): void {
    $owner = new Agent(['name' => 'ANA MARÍA PÉREZ', 'agent_type_id' => 2]);
    $subagent = new Agent(['name' => 'Tammy Jennifer Rodríguez', 'agent_type_id' => 3, 'owner_agent' => '213']);
    $subagent->setRelation('ownerAgent', $owner);

    $master = new Agency(['code' => 'TDG-150', 'name_corporative' => 'Twylite Investments', 'agency_type_id' => 1]);
    $commission = commissionWithStructure($master, $subagent);

    expect($commission->masterAgencyDisplayName())->toBe('Twylite Investments')
        ->and($commission->generalAgencyDisplayName())->toBe('-')
        ->and($commission->structureAgentName())->toBe('ANA MARÍA PÉREZ')
        ->and($commission->subagentName())->toBe('Tammy Jennifer Rodríguez');
});

it('un agente directo va como agente y sin subagente; una general muestra su master', function (): void {
    $agent = new Agent(['name' => 'Fabiola Antelo', 'agent_type_id' => 2]);
    $masterAgency = new Agency(['code' => 'TDG-120', 'name_corporative' => 'YV SOLUTIONS', 'agency_type_id' => 1]);
    $general = new Agency(['code' => 'TDG-121', 'name_corporative' => 'Sandra Marin Munoz', 'agency_type_id' => 3]);
    $general->setRelation('masterAgency', $masterAgency);

    $commission = commissionWithStructure($general, $agent);

    expect($commission->masterAgencyDisplayName())->toBe('YV SOLUTIONS')
        ->and($commission->generalAgencyDisplayName())->toBe('Sandra Marin Munoz')
        ->and($commission->structureAgentName())->toBe('Fabiola Antelo')
        ->and($commission->subagentName())->toBe('-');
});

it('un subagente sin responsable válido queda como agente, sin inventar la jerarquía', function (): void {
    $subagent = new Agent(['name' => 'NELSON ACOSTA', 'agent_type_id' => 3, 'owner_agent' => '85']);
    $subagent->setRelation('ownerAgent', null);

    $commission = commissionWithStructure(null, $subagent);

    expect($commission->structureAgentName())->toBe('NELSON ACOSTA')
        ->and($commission->subagentName())->toBe('-')
        ->and(commissionWithStructure(null, null)->structureAgentName())->toBe('-');
});

it('el Excel de Detallado de Comisiones trae toda la estructura y precarga las relaciones', function (): void {
    $labels = collect(CommissionExporter::getColumns())->map(fn ($column) => $column->getLabel())->all();
    $source = (string) file_get_contents(dirname(__DIR__, 2).'/app/Filament/Exports/CommissionExporter.php');

    expect(array_slice($labels, 0, 7))->toBe(['FECHA', 'Nro. Venta', 'AGENCIA MASTER', 'AGENCIA GENERAL', 'AGENTE', 'SUBAGENTE', 'REFERIDOR'])
        ->and($labels)->toContain('COMISION AGENCIA MASTER USD', 'COMISION AGENCIA GENERAL VES', 'COMISION AGENTE USD')
        ->and(collect($labels)->duplicates()->all())->toBe([])
        ->and($source)
        ->toContain('public static function modifyQuery(Builder $query): Builder')
        ->toContain("'agent.ownerAgent'")
        ->toContain("'agency.masterAgency'")
        ->toContain('CommissionReferidorPercentage::eagerLoadRelations()')
        ->toContain('La exportación de comisiones terminó');
});

it('el Centro de reportes ofrece el Detallado de comisiones por fecha de cálculo y sin estatus de cobro', function (): void {
    $type = AdministrationReportType::CommissionsDetail;
    $filters = CollectionReportFilters::fromArray(
        ['type' => $type->value, 'period' => CollectionReportFilters::PERIOD_THIS_YEAR, 'collection_status' => 'por_pagar'],
        CarbonImmutable::parse('2026-11-05 10:00'),
    );

    expect(AdministrationReportType::cases())->toContain($type)
        ->and($type->label())->toBe('Detallado de comisiones')
        ->and($type->rowNoun())->toBe(['comisión', 'comisiones'])
        ->and($type->fileSlug())->toBe('detallado-comisiones')
        ->and(array_keys(CollectionReportFilters::periodOptionsFor($type)))
        ->toBe([CollectionReportFilters::PERIOD_THIS_MONTH, CollectionReportFilters::PERIOD_LAST_MONTH, CollectionReportFilters::PERIOD_THIS_YEAR, CollectionReportFilters::PERIOD_ALL, CollectionReportFilters::PERIOD_CUSTOM])
        ->and(CollectionReportFilters::periodOptionsFor(AdministrationReportType::CollectionsGeneral))->toBe(CollectionReportFilters::periodOptions())
        ->and($filters->from?->toDateString())->toBe('2026-01-01')
        ->and($filters->to?->toDateString())->toBe('2026-12-31')
        ->and($filters->collectionStatus)->toBeNull()
        ->and($filters->describe())->toBe(['Fecha de cálculo: del 01/01/2026 al 31/12/2026'])
        ->and(CollectionReportFilters::fromArray($filters->toArray())->from?->toDateString())->toBe('2026-01-01');

    $page = (string) file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Pages/CentroDeReportes.php');

    expect($page)
        ->toContain('CollectionReportFilters::periodOptionsFor(self::reportType($get))')
        ->toContain('->visible(fn (Get $get): bool => ! self::isCommissions($get) && ! self::isCapitation($get))')
        ->toContain('Por fecha de cálculo de la comisión.');
});

it('cada fila del reporte de comisiones tiene una celda por encabezado y la estructura en orden', function (): void {
    $owner = new Agent(['name' => 'IBL GROUP', 'agent_type_id' => 2]);
    $subagent = new Agent(['name' => 'NELSON ACOSTA', 'agent_type_id' => 3, 'owner_agent' => '85']);
    $subagent->setRelation('ownerAgent', $owner);
    $master = new Agency(['code' => 'TDG-128', 'name_corporative' => 'Hinestroza Consultores', 'agency_type_id' => 1]);

    $commission = commissionWithStructure($master, $subagent);
    $commission->forceFill(['created_at' => '2026-10-02 10:00:00', 'commission_agent_usd' => '12.5', 'pay_amount_usd' => '80']);
    $commission->setRelation('plan', null);
    $commission->setRelation('coverage', null);

    $row = CommissionsDetailReport::row($commission);

    expect($row)->toHaveCount(count(CommissionsDetailReport::headings()))
        ->and($row[CommissionsDetailReport::COL_DATE])->toBe('02/10/2026')
        ->and($row[CommissionsDetailReport::COL_MASTER])->toBe('Hinestroza Consultores')
        ->and($row[CommissionsDetailReport::COL_GENERAL])->toBeNull()
        ->and($row[CommissionsDetailReport::COL_AGENT])->toBe('IBL GROUP')
        ->and($row[CommissionsDetailReport::COL_SUBAGENT])->toBe('NELSON ACOSTA')
        ->and($row[CommissionsDetailReport::COL_AGENT_USD])->toBe(12.5)
        ->and($row[CommissionsDetailReport::COL_PAY_USD])->toBe(80.0);
});

it('el conteo del reporte de comisiones lee la base sin escribir y respeta el periodo', function (): void {
    $year = CollectionReportFilters::fromArray(['type' => 'commissions_detail', 'period' => CollectionReportFilters::PERIOD_THIS_YEAR]);
    $all = CollectionReportFilters::fromArray(['type' => 'commissions_detail', 'period' => CollectionReportFilters::PERIOD_ALL]);

    expect(CollectionReportBuilder::count($year))->toBe(Commission::query()->whereYear('created_at', now()->year)->count())
        ->and(CollectionReportBuilder::count($all))->toBe(Commission::query()->count());
});
