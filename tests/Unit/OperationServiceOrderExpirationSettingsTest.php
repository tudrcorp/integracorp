<?php

declare(strict_types=1);

use App\Filament\Operations\Pages\ManageOperationServiceOrderParameters;
use App\Jobs\ExpireOperationServiceOrders;
use App\Models\OperationCoordinationService;
use App\Models\OperationServiceOrder;
use App\Models\OperationServiceOrderSetting;
use App\Support\Filament\DepartmentNavigationPermissionRegistry;
use App\Support\Filament\UserFormPermissionOptions;
use App\Support\Operations\OperationServiceOrderValidity;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(Tests\TestCase::class);

/**
 * La caducidad de órdenes de servicio sale de Operaciones → Parámetros de órdenes.
 * Lo que escribe en la base corre dentro de una transacción que siempre se revierte.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    OperationServiceOrderValidity::overrideSettings(null);
});

afterEach(function (): void {
    OperationServiceOrderValidity::overrideSettings(null);
    Carbon::setTestNow();
    DB::rollBack();
});

function expirationTestOrder(string $status, string $approvedAt): OperationServiceOrder
{
    $order = new OperationServiceOrder;
    $order->setRawAttributes(['status' => $status, 'approved_at' => $approvedAt], true);

    return $order;
}

it('usa los días configurados para calcular el vencimiento', function (): void {
    Carbon::setTestNow('2026-06-15 12:00:00');
    OperationServiceOrderValidity::overrideSettings(true, 3);

    $order = expirationTestOrder('EN GESTION', '2026-06-13 09:00:00');

    expect(OperationServiceOrderValidity::validityDays())->toBe(3)
        ->and(OperationServiceOrderValidity::expiresAt($order)?->format('Y-m-d H:i:s'))->toBe('2026-06-16 23:59:59')
        ->and(OperationServiceOrderValidity::remainingDays($order))->toBe(1)
        ->and(OperationServiceOrderValidity::vigenciaShortLabel($order))->toBe('Vence en 1 día')
        ->and(OperationServiceOrderValidity::isExpired(expirationTestOrder('EN GESTION', '2026-06-11 09:00:00')))->toBeTrue();
});

it('con la caducidad apagada ninguna orden abierta vence ni muestra cuenta regresiva', function (): void {
    Carbon::setTestNow('2026-06-15 12:00:00');
    OperationServiceOrderValidity::overrideSettings(false);

    $old = expirationTestOrder('EN GESTION', '2025-01-01 09:00:00');

    expect(OperationServiceOrderValidity::isExpired($old))->toBeFalse()
        ->and(OperationServiceOrderValidity::expireIfNeeded($old))->toBeFalse()
        ->and(OperationServiceOrderValidity::remainingDays($old))->toBeNull()
        ->and(OperationServiceOrderValidity::vigenciaShortLabel($old))->toBeNull()
        ->and(OperationServiceOrderValidity::shouldHighlightVigencia($old))->toBeFalse()
        ->and(OperationServiceOrderValidity::expireEligibleOrders())->toBe(0);
});

it('una orden ya CADUCADA se sigue mostrando así aunque se apague la caducidad', function (): void {
    OperationServiceOrderValidity::overrideSettings(false);

    $expired = expirationTestOrder('CADUCADA', '2026-01-01 09:00:00');

    expect(OperationServiceOrderValidity::vigenciaShortLabel($expired))->toBe('Vigencia vencida')
        ->and(OperationServiceOrderValidity::vigenciaTone($expired))->toBe('danger');
});

it('acota los días entre 1 y 365', function (int $input, int $expected): void {
    expect(OperationServiceOrderValidity::clampDays($input))->toBe($expected);
})->with([
    'cero' => [0, 1],
    'negativo' => [-5, 1],
    'normal' => [15, 15],
    'excesivo' => [9999, 365],
]);

it('sin configuración guardada mantiene la regla histórica de 10 días activada', function (): void {
    if (! Schema::hasTable('operation_service_order_settings')) {
        $this->markTestSkipped('Falta aplicar la migración de parámetros de órdenes.');
    }

    DB::table('operation_service_order_settings')->delete();
    OperationServiceOrderValidity::flushSettings();

    expect(OperationServiceOrderValidity::expirationEnabled())->toBeTrue()
        ->and(OperationServiceOrderValidity::validityDays())->toBe(10);
});

it('lee la configuración guardada y la refresca al vaciar la memoria', function (): void {
    if (! Schema::hasTable('operation_service_order_settings')) {
        $this->markTestSkipped('Falta aplicar la migración de parámetros de órdenes.');
    }

    OperationServiceOrderSetting::current()->update(['expiration_enabled' => true, 'validity_days' => 21]);
    OperationServiceOrderValidity::flushSettings();

    expect(OperationServiceOrderValidity::validityDays())->toBe(21);

    OperationServiceOrderSetting::current()->update(['expiration_enabled' => false]);
    OperationServiceOrderValidity::flushSettings();

    expect(OperationServiceOrderValidity::expirationEnabled())->toBeFalse();
});

it('cuenta y caduca solo las órdenes abiertas que superan el plazo', function (): void {
    $coordinationId = OperationCoordinationService::query()->value('id');

    if ($coordinationId === null) {
        $this->markTestSkipped('La base no tiene coordinaciones para colgar órdenes de prueba.');
    }

    $make = function (string $status, int $daysAgo) use ($coordinationId): OperationServiceOrder {
        return OperationServiceOrder::query()->forceCreate([
            'operation_coordination_service_id' => $coordinationId,
            'order_number' => 'ORD-QA-EXP-'.Str::upper(Str::random(8)),
            'service_type' => 'LABORATORIOS',
            'status' => $status,
            'approved_at' => now()->subDays($daysAgo),
            'created_by' => 'QA',
        ]);
    };

    $floor = OperationServiceOrderValidity::countOverdueWith(5);

    $overdue = $make('EN GESTION', 8);
    $fresh = $make('EN GESTION', 2);
    $finalized = $make('FINALIZADO', 30);

    expect(OperationServiceOrderValidity::countOverdueWith(5))->toBe($floor + 1);

    OperationServiceOrderValidity::overrideSettings(false);
    expect(OperationServiceOrderValidity::expireEligibleOrders('QA'))->toBe(0)
        ->and($overdue->refresh()->status)->toBe('EN GESTION');

    OperationServiceOrderValidity::overrideSettings(true, 5);
    OperationServiceOrderValidity::expireEligibleOrders('QA');

    expect($overdue->refresh()->status)->toBe('CADUCADA')
        ->and($fresh->refresh()->status)->toBe('EN GESTION')
        ->and($finalized->refresh()->status)->toBe('FINALIZADO');
});

it('el job no caduca nada cuando la caducidad está apagada', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/ExpireOperationServiceOrders.php');

    expect($source)
        ->toContain('OperationServiceOrderValidity::flushSettings()')
        ->toContain('OperationServiceOrderValidity::expirationEnabled()')
        ->toContain('OperationServiceOrderValidity::expireEligibleOrders');

    expect(class_exists(ExpireOperationServiceOrders::class))->toBeTrue();
});

it('ya no quedan 10 días fijos en la finalización ni en la tabla de órdenes', function (): void {
    $documents = file_get_contents(dirname(__DIR__, 2).'/app/Support/Operations/OperationServiceOrderDocuments.php');
    $table = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationServiceOrders/Tables/OperationServiceOrdersTable.php');

    expect($documents)->toContain('OperationServiceOrderValidity::validityDays()')
        ->not->toContain('VALIDITY_DAYS');

    expect($table)->toContain('OperationServiceOrderValidity::validityDays()')
        ->not->toContain('Vigencia de 10 días');
});

it('la página de parámetros está en Operaciones con su propio permiso', function (): void {
    expect(ManageOperationServiceOrderParameters::getNavigationLabel())->toBe('Parámetros de órdenes')
        ->and(ManageOperationServiceOrderParameters::getNavigationGroup())->toBe('COORDINACIÓN DE SERVICIOS')
        ->and(DepartmentNavigationPermissionRegistry::slugsFor(ManageOperationServiceOrderParameters::class))->toBe(['parametros-ordenes-servicio'])
        ->and(DepartmentNavigationPermissionRegistry::moduleFor(ManageOperationServiceOrderParameters::class))->toBe('OPERACIONES')
        ->and(UserFormPermissionOptions::navToLegacySlugAliases()['manageoperationserviceorderparameters'] ?? null)->toBe(['parametros-ordenes-servicio']);

    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Pages/ManageOperationServiceOrderParameters.php');

    expect($source)
        ->toContain('AuthorizesDepartmentNavigation')
        ->toContain("Toggle::make('expiration_enabled')")
        ->toContain("TextInput::make('validity_days')")
        ->toContain('->requiresConfirmation(')
        ->toContain('DB::transaction(')
        ->toContain("'updated_by' => \$user->name");
});
