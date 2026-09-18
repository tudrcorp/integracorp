<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\Helpdesks\Widgets\HelpdeskStatusWeeklyChart;
use App\Models\RrhhColaborador;
use App\Models\Supplier;
use App\Models\User;
use App\Support\HelpdeskFormSchema;
use Illuminate\Support\Facades\Auth;

uses(Tests\TestCase::class);

beforeEach(fn () => Auth::forgetUser());
afterEach(fn () => Auth::forgetUser());

function actingAsSupplierAnalystForHelpdesk(): User
{
    $user = new User([
        'name' => 'Analista ATENMEDI',
        'email' => 'analista@atenmedi.com',
        'status' => 'ACTIVO',
        'departament' => ['OPERACIONES'],
        'supplier_id' => 15,
        'is_proveedor_amd' => true,
    ]);
    $user->setRelation('supplier', new Supplier(['gestion_integracorp' => true]));

    Auth::setUser($user);

    return $user;
}

function actingAsInternalOperationsAnalyst(): User
{
    $user = new User([
        'name' => 'Analista TDG',
        'email' => 'analista@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['OPERACIONES'],
    ]);

    Auth::setUser($user);

    return $user;
}

it('oculta el gráfico anual de tickets al analista de proveedor', function (): void {
    actingAsSupplierAnalystForHelpdesk();

    expect(HelpdeskStatusWeeklyChart::canView())->toBeFalse();
});

it('mantiene el gráfico anual de tickets para el analista interno', function (): void {
    actingAsInternalOperationsAnalyst();

    expect(HelpdeskStatusWeeklyChart::canView())->toBeTrue();
});

it('mantiene el gráfico anual de tickets cuando no hay sesión', function (): void {
    expect(HelpdeskStatusWeeklyChart::canView())->toBeTrue();
});

it('restringe las personas involucradas solo para el analista de proveedor', function (): void {
    expect(HelpdeskFormSchema::restrictsColaboradoresToOperationsSupport())->toBeFalse();

    actingAsInternalOperationsAnalyst();
    expect(HelpdeskFormSchema::restrictsColaboradoresToOperationsSupport())->toBeFalse();

    actingAsSupplierAnalystForHelpdesk();
    expect(HelpdeskFormSchema::restrictsColaboradoresToOperationsSupport())->toBeTrue();
});

it('acota la consulta de colaboradores a Operaciones y Sistemas', function (): void {
    actingAsSupplierAnalystForHelpdesk();

    $query = HelpdeskFormSchema::applySupplierAnalystColaboradorScope(RrhhColaborador::query());

    expect($query->toSql())->toContain('exists')
        ->and($query->getBindings())->toContain('OPERACIONES', 'SISTEMAS');
});

it('no toca la consulta de colaboradores del analista interno', function (): void {
    actingAsInternalOperationsAnalyst();

    $query = HelpdeskFormSchema::applySupplierAnalystColaboradorScope(RrhhColaborador::query());

    expect($query->toSql())->not->toContain('exists')
        ->and($query->getBindings())->toBe([]);
});

it('declara Operaciones y Sistemas como los departamentos de soporte', function (): void {
    expect(HelpdeskFormSchema::OPERATIONS_SUPPORT_DEPARTMENTS)->toBe(['OPERACIONES', 'SISTEMAS']);
});

it('aplica el filtro a los dos selectores de personas involucradas', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Support/HelpdeskFormSchema.php');

    expect($source)
        ->toContain('modifyQueryUsing: fn (Builder $query): Builder => self::applySupplierAnalystColaboradorScope($query)')
        ->toContain('self::applySupplierAnalystColaboradorScope($query);')
        ->toContain('->options(self::rrhhColaboradorOptionsForHelpdeskMultiselect())');
});
