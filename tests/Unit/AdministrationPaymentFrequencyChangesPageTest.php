<?php

declare(strict_types=1);

use App\Filament\Administration\Resources\AffiliationCorporatePaymentFrequencyChanges\AffiliationCorporatePaymentFrequencyChangeResource;
use App\Filament\Administration\Resources\AffiliationCorporatePaymentFrequencyChanges\Pages\ListAffiliationCorporatePaymentFrequencyChanges;
use App\Filament\Administration\Resources\AffiliationCorporatePaymentFrequencyChanges\Pages\ViewAffiliationCorporatePaymentFrequencyChange;
use App\Models\AffiliationCorporatePaymentFrequencyChange;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * Renderiza las páginas reales del panel. Escribe un registro de prueba dentro
 * de una transacción que siempre se revierte: la base de desarrollo no cambia.
 */
beforeEach(function (): void {
    if (! Schema::hasTable('affiliation_corporate_payment_frequency_changes')) {
        $this->markTestSkipped('Falta aplicar la migración del registro de cambios de frecuencia.');
    }

    DB::beginTransaction();
    Filament::setCurrentPanel('administration');
});

afterEach(function (): void {
    DB::rollBack();
});

function administrationUser(array $departments): User
{
    $user = User::factory()->make(['id' => 999999, 'name' => 'Revisor Administración', 'status' => 'ACTIVO']);
    $user->setRawAttributes([...$user->getAttributes(), 'departament' => json_encode($departments)], true);

    return $user;
}

function sampleFrequencyChange(): AffiliationCorporatePaymentFrequencyChange
{
    return AffiliationCorporatePaymentFrequencyChange::query()->create([
        'batch_uuid' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
        'affiliation_corporate_id' => 99999999,
        'affiliation_code' => 'TDEC-COR-TEST',
        'affiliation_name' => 'EMPRESA DE PRUEBA',
        'previous_frequency' => 'TRIMESTRAL',
        'new_frequency' => 'MENSUAL',
        'fee_anual' => 1200,
        'previous_total_amount' => 300,
        'new_total_amount' => 100,
        'pending_balance' => 300,
        'cancelled_collections' => [['id' => 1, 'invoice' => '09-00900', 'date' => '10/10/2026', 'amount' => 300, 'months' => 3]],
        'created_collections' => [
            ['id' => 2, 'invoice' => '09-00901', 'date' => '10/10/2026', 'amount' => 100, 'months' => 1],
            ['id' => 3, 'invoice' => '09-00902', 'date' => '10/11/2026', 'amount' => 100, 'months' => 1],
            ['id' => 4, 'invoice' => '09-00903', 'date' => '10/12/2026', 'amount' => 100, 'months' => 1],
        ],
        'snapshot' => ['affiliation' => ['payment_frequency' => 'TRIMESTRAL', 'total_amount' => 300]],
        'status' => 'APLICADO',
        'performed_by_name' => 'Analista Negocios',
    ]);
}

it('solo Administración y SUPERADMIN ven el registro', function (array $departments, bool $expected): void {
    $this->actingAs(administrationUser($departments));

    expect(AffiliationCorporatePaymentFrequencyChangeResource::canAccess())->toBe($expected)
        ->and(AffiliationCorporatePaymentFrequencyChangeResource::canCreate())->toBeFalse();
})->with([
    'administración' => [['ADMINISTRACION'], true],
    'superadmin' => [['SUPERADMIN'], true],
    'negocios' => [['NEGOCIOS'], false],
]);

it('la lista muestra el cambio por validar', function (): void {
    $this->actingAs(administrationUser(['ADMINISTRACION']));
    $change = sampleFrequencyChange();

    Livewire::test(ListAffiliationCorporatePaymentFrequencyChanges::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$change])
        ->assertSee('TDEC-COR-TEST')
        ->assertSee('Trimestral → Mensual')
        ->assertSee('Por validar');
});

it('la ficha muestra ambas listas de avisos y bloquea el reverso si la afiliación no existe', function (): void {
    $this->actingAs(administrationUser(['SUPERADMIN', 'ADMINISTRACION']));
    $change = sampleFrequencyChange();

    Livewire::test(ViewAffiliationCorporatePaymentFrequencyChange::class, ['record' => $change->getKey()])
        ->assertOk()
        ->assertSee('Avisos de cobro cancelados')
        ->assertSee('09-00900')
        ->assertSee('Avisos de cobro nuevos')
        ->assertSee('09-00903')
        ->assertSee('Analista Negocios')
        ->assertActionVisible('validateChange')
        ->assertActionVisible('reverseChange')
        ->assertActionDisabled('reverseChange');
});

it('un analista de Administración sin el permiso no ve el botón de revertir', function (): void {
    $this->actingAs(administrationUser(['ADMINISTRACION']));
    $change = sampleFrequencyChange();

    Livewire::test(ViewAffiliationCorporatePaymentFrequencyChange::class, ['record' => $change->getKey()])
        ->assertOk()
        ->assertActionVisible('validateChange')
        ->assertActionHidden('reverseChange');
});
