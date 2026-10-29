<?php

declare(strict_types=1);

use App\Filament\Administration\Resources\PopulationBillingAdjustments\Pages\ListPopulationBillingAdjustments;
use App\Filament\Administration\Resources\PopulationBillingAdjustments\Pages\ViewPopulationBillingAdjustment;
use App\Models\AffiliationCorporate;
use App\Models\Collection;
use App\Models\CollectionAdjustment;
use App\Models\CorporateBillingAdjustment;
use App\Models\CorporatePopulationUpdate;
use App\Models\User;
use App\Support\AffiliationCorporates\BillingAdjustments\PopulationBillingAdjuster;
use App\Support\AffiliationCorporates\Population\PopulationUpdateRollback;
use App\Support\Affiliations\DateCorrection\AffiliationDateCorrector;
use App\Support\Collections\CollectionPaymentOptions;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * Ajuste por población con el caso real de MAPFRE (afiliación 54, carga del 21/10/2026: 24 altas
 * y 4 bajas). Escribe dentro de una transacción que siempre se revierte.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    Queue::fake();

    $this->update = CorporatePopulationUpdate::query()
        ->where('affiliation_corporate_id', 54)
        ->where('status', CorporatePopulationUpdate::STATUS_APPLIED)
        ->first();

    if ($this->update === null || ! PopulationBillingAdjuster::tablesReady()) {
        $this->markTestSkipped('Esta base no tiene la carga de población aplicada de MAPFRE o falta la migración.');
    }

    $this->actor = User::factory()->create([
        'email' => 'qa.ajuste.poblacion.'.uniqid().'@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['SUPERADMIN', 'ADMINISTRACION'],
    ]);
    $this->actingAs($this->actor);
    Filament::setCurrentPanel('administration');

    /** Si en la base ya hay un ajuste real aplicado, se revierte dentro de la transacción (el rollback lo restituye). */
    if (($existing = PopulationBillingAdjuster::activeAdjustmentFor($this->update)) !== null) {
        PopulationBillingAdjuster::revert($existing, 'Preparación del test (se deshace al terminar)', $this->actor);
    }

    $this->amountsBefore = Collection::query()->where('affiliation_code', 'TDEC-COR-00054')->whereNull('billing_adjustment_id')->orderBy('id')->pluck('total_amount', 'id')->all();
});

afterEach(function (): void {
    DB::rollBack();
});

function mapfreInstallments(): array
{
    return Collection::query()->where('affiliation_code', 'TDEC-COR-00054')->whereNull('billing_adjustment_id')->where('status', 'POR PAGAR')->orderBy('id')->pluck('total_amount')->map(fn (mixed $amount): string => (string) $amount)->all();
}

it('calcula el ajuste de MAPFRE con los números que se verifican a mano', function (): void {
    $plan = PopulationBillingAdjuster::plan($this->update, '21/10/2026');

    expect($plan['blockers'])->toBe([])
        ->and($plan['frequency'])->toBe('TRIMESTRAL')
        ->and($plan['cycle_start']->format('d/m/Y'))->toBe('26/08/2026')
        ->and($plan['calculation']['persons_added'])->toBe(24)
        ->and($plan['calculation']['persons_removed'])->toBe(4)
        ->and($plan['calculation']['charge_cents'])->toBe(38_280)
        ->and($plan['calculation']['installment_delta_cents'])->toBe(81_500)
        ->and(count($plan['calculation']['adjusted']))->toBe(3)
        ->and($plan['charge_due']->format('d/m/Y'))->toBe('26/11/2026')
        ->and($plan['warnings'][0] ?? '')->toContain('ya existía antes de esta carga');
});

it('aplica: ajusta las cuotas, emite el aviso marcado, deja rastro y bloquea la reversión de la población', function (): void {
    $plan = PopulationBillingAdjuster::plan($this->update, '21/10/2026');
    $adjustment = PopulationBillingAdjuster::apply($this->update, '21/10/2026', 'Inclusión de 24 personas según archivo de MAPFRE', $this->actor, $plan['fingerprint']);

    $charge = Collection::query()->findOrFail($adjustment->charge_collection_id);

    expect(mapfreInstallments())->toBe(['109454.50', '109454.50', '109454.50'])
        ->and((string) $charge->total_amount)->toBe('382.80')
        ->and($charge->status)->toBe('POR PAGAR')
        ->and($charge->next_payment_date)->toBe('26/11/2026')
        ->and((int) $charge->billing_adjustment_id)->toBe((int) $adjustment->getKey())
        ->and($adjustment->lines()->count())->toBe(28)
        ->and(CollectionAdjustment::query()->where('batch_uuid', $adjustment->public_id)->where('mode', CollectionAdjustment::MODE_POPULATION)->count())->toBe(4)
        ->and(PopulationUpdateRollback::blockingReason($this->update))->toContain('ajuste de facturación aplicado')
        ->and(PopulationBillingAdjuster::plan($this->update, '21/10/2026')['blockers'])->toContain('Esta carga ya tiene un ajuste aplicado. Para recalcularlo, revierta primero el ajuste actual.')
        ->and(AffiliationDateCorrector::calendar(AffiliationCorporate::query()->findOrFail(54))['misaligned'])->toBe([]);

    Queue::assertPushed(App\Jobs\GeneratePopulationBillingAdjustmentPdf::class);

    $labels = CollectionPaymentOptions::forAffiliationCode('TDEC-COR-00054');

    expect($labels[$charge->getKey()])->toContain('AJUSTE POR POBLACIÓN')->toContain('US$ 382,80');
});

it('revierte exacto: las cuotas vuelven a su monto y el aviso queda ANULADO', function (): void {
    $plan = PopulationBillingAdjuster::plan($this->update, '21/10/2026');
    $adjustment = PopulationBillingAdjuster::apply($this->update, '21/10/2026', 'Inclusión de 24 personas según archivo de MAPFRE', $this->actor, $plan['fingerprint']);

    PopulationBillingAdjuster::revert($adjustment, 'La fecha efectiva correcta era otra', $this->actor);

    expect(Collection::query()->where('affiliation_code', 'TDEC-COR-00054')->whereNull('billing_adjustment_id')->orderBy('id')->pluck('total_amount', 'id')->all())->toBe($this->amountsBefore)
        ->and(Collection::query()->findOrFail($adjustment->charge_collection_id)->status)->toBe('ANULADO')
        ->and($adjustment->fresh()->status)->toBe(CorporateBillingAdjustment::STATUS_REVERTED)
        ->and(PopulationUpdateRollback::blockingReason($this->update))->toBeNull()
        ->and(PopulationBillingAdjuster::plan($this->update, '21/10/2026')['blockers'])->toBe([]);
});

it('no revierte un ajuste cuyo aviso ya se cobró', function (): void {
    $plan = PopulationBillingAdjuster::plan($this->update, '21/10/2026');
    $adjustment = PopulationBillingAdjuster::apply($this->update, '21/10/2026', 'Inclusión de 24 personas según archivo de MAPFRE', $this->actor, $plan['fingerprint']);
    Collection::query()->whereKey($adjustment->charge_collection_id)->update(['status' => 'PAGADO']);

    expect(PopulationBillingAdjuster::revertBlocker($adjustment->fresh()))->toContain('no se puede revertir un ajuste cobrado');

    PopulationBillingAdjuster::revert($adjustment->fresh(), 'Intento de revertir algo cobrado', $this->actor);
})->throws(RuntimeException::class, 'no se puede revertir un ajuste cobrado');

it('no aplica si la cobranza cambió desde la vista previa', function (): void {
    $plan = PopulationBillingAdjuster::plan($this->update, '21/10/2026');
    Collection::query()->where('affiliation_code', 'TDEC-COR-00054')->where('collection_invoice_number', '09-00914')->update(['total_amount' => 108_000]);

    PopulationBillingAdjuster::apply($this->update, '21/10/2026', 'Inclusión de 24 personas según archivo de MAPFRE', $this->actor, $plan['fingerprint']);
})->throws(RuntimeException::class, 'Los datos cambiaron desde la vista previa');

it('exige motivo, una fecha dentro de la ventana y el permiso propio', function (): void {
    $plan = PopulationBillingAdjuster::plan($this->update, '21/10/2026');

    expect(fn () => PopulationBillingAdjuster::apply($this->update, '21/10/2026', 'corto', $this->actor, $plan['fingerprint']))
        ->toThrow(RuntimeException::class, 'mínimo 15 caracteres')
        ->and(PopulationBillingAdjuster::plan($this->update, '01/08/2026')['blockers'][0] ?? '')->toContain('La fecha efectiva debe estar entre')
        ->and(PopulationBillingAdjuster::plan($this->update, '15/11/2026')['blockers'])->toBe([]);

    $analyst = User::factory()->create(['email' => 'qa.ajuste.sinpermiso.'.uniqid().'@tudrencasa.com', 'status' => 'ACTIVO', 'departament' => ['ADMINISTRACION']]);
    $this->actingAs($analyst);

    expect(fn () => PopulationBillingAdjuster::apply($this->update, '21/10/2026', 'Inclusión de 24 personas según archivo de MAPFRE', $analyst, $plan['fingerprint']))
        ->toThrow(RuntimeException::class, 'No tiene permiso');
});

it('muestra la bandeja y la ficha con el cálculo explicado, y aplica desde la acción', function (): void {
    Livewire::test(ListPopulationBillingAdjustments::class)
        ->assertSuccessful()
        ->assertSee('Ajustes por población')
        ->assertSee('TDEC-COR-00054')
        ->assertSee('Por ajustar')
        ->assertActionVisible(TestAction::make('applyPopulationBillingAdjustment')->table($this->update));

    Livewire::test(ViewPopulationBillingAdjustment::class, ['record' => $this->update->getKey()])
        ->assertSuccessful()
        ->assertSee('Cómo se calculó')
        ->assertSee('US$ 40,75 × 36 ÷ 92')
        ->assertSee('US$ 382,80')
        ->assertSee('09-00913')
        ->mountAction('applyPopulationBillingAdjustment')
        ->set('mountedActions.0.data.reason', 'Inclusión de 24 personas según archivo de MAPFRE')
        ->set('mountedActions.0.data.confirmed', true)
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect(PopulationBillingAdjuster::activeAdjustmentFor($this->update))->not->toBeNull()
        ->and(mapfreInstallments())->toBe(['109454.50', '109454.50', '109454.50']);
});

it('registra el menú y el permiso de acción en Administración, y los módulos del calendario ignoran los avisos por ajuste', function (): void {
    $root = dirname(__DIR__, 2).'/';

    expect((string) file_get_contents($root.'app/Support/Filament/DepartmentNavigationPermissionRegistry.php'))->toContain("PopulationBillingAdjustmentResource::class => ['ajustes-por-poblacion']")
        ->and(App\Support\Filament\BusinessFilamentActionPermissionRegistry::modulesForSlug('aplicar-ajustes-por-poblacion'))->toBe(['ADMINISTRACION']);

    foreach ([
        'app/Support/Affiliations/DateCorrection/AffiliationDateCorrector.php' => 2,
        'app/Support/AffiliationCorporates/CorporatePaymentFrequencyChanger.php' => 1,
        'app/Support/AffiliationCorporates/CorporateAffiliateUpgradeManager.php' => 3,
    ] as $file => $expected) {
        expect(substr_count((string) file_get_contents($root.$file), 'calendarInstallments()'))->toBe($expected);
    }
});

it('sirve el PDF al instante: vista previa en línea, descarga como adjunto, y lo genera si el job aún no lo dejó', function (): void {
    Illuminate\Support\Facades\Storage::fake('local');
    $plan = PopulationBillingAdjuster::plan($this->update, '21/10/2026');
    $adjustment = PopulationBillingAdjuster::apply($this->update, '21/10/2026', 'Inclusión de 24 personas según archivo de MAPFRE', $this->actor, $plan['fingerprint']);
    $path = App\Jobs\GeneratePopulationBillingAdjustmentPdf::pathFor($adjustment);

    Illuminate\Support\Facades\Storage::disk('local')->assertMissing($path);

    $inline = $this->get(route('administration.population-billing-adjustments.pdf', ['adjustment' => $adjustment->getKey()]));
    $inline->assertOk();

    expect($inline->headers->get('Content-Type'))->toBe('application/pdf')
        ->and((string) $inline->headers->get('Content-Disposition'))->toStartWith('inline')
        ->and((string) $adjustment->fresh()->pdf_path)->toBe($path);

    Illuminate\Support\Facades\Storage::disk('local')->assertExists($path);

    $download = $this->get(route('administration.population-billing-adjustments.pdf', ['adjustment' => $adjustment->getKey(), 'download' => 1]));
    $download->assertOk();

    expect((string) $download->headers->get('Content-Disposition'))->toStartWith('attachment')->toContain('Aviso-ajuste-poblacion-tdec-cor-00054');
});

it('no deja ver el PDF a quien no tiene el menú de ajustes por población', function (): void {
    Illuminate\Support\Facades\Storage::fake('local');
    $plan = PopulationBillingAdjuster::plan($this->update, '21/10/2026');
    $adjustment = PopulationBillingAdjuster::apply($this->update, '21/10/2026', 'Inclusión de 24 personas según archivo de MAPFRE', $this->actor, $plan['fingerprint']);

    $this->actingAs(User::factory()->create(['email' => 'qa.ajuste.pdf.'.uniqid().'@tudrencasa.com', 'status' => 'ACTIVO', 'departament' => ['ADMINISTRACION']]));

    $this->get(route('administration.population-billing-adjustments.pdf', ['adjustment' => $adjustment->getKey()]))->assertForbidden();
});

it('al aplicar abre la ficha con la vista previa del aviso, que muestra el spinner y la descarga directa', function (): void {
    Livewire::test(ViewPopulationBillingAdjustment::class, ['record' => $this->update->getKey()])
        ->mountAction('applyPopulationBillingAdjustment')
        ->set('mountedActions.0.data.reason', 'Inclusión de 24 personas según archivo de MAPFRE')
        ->set('mountedActions.0.data.confirmed', true)
        ->callMountedAction()
        ->assertRedirectContains('action=previewPopulationBillingAdjustmentPdf');

    $adjustment = PopulationBillingAdjuster::activeAdjustmentFor($this->update);

    Livewire::test(ViewPopulationBillingAdjustment::class, ['record' => $this->update->getKey()])
        ->assertActionVisible('previewPopulationBillingAdjustmentPdf')
        ->mountAction('previewPopulationBillingAdjustmentPdf')
        ->assertActionMounted('previewPopulationBillingAdjustmentPdf');

    $html = view('filament.administration.population-billing-adjustments.pdf-preview', [
        'previewUrl' => App\Filament\Administration\Resources\PopulationBillingAdjustments\Actions\PopulationBillingAdjustmentActions::pdfUrl($adjustment),
        'downloadUrl' => App\Filament\Administration\Resources\PopulationBillingAdjustments\Actions\PopulationBillingAdjustmentActions::pdfUrl($adjustment, download: true),
        'reverted' => false,
    ])->render();

    expect($html)
        ->toContain('Preparando el aviso')
        ->toContain('ic-pba-spinner')
        ->toContain('x-on:load="loaded()"')
        ->toContain(route('administration.population-billing-adjustments.pdf', ['adjustment' => $adjustment->getKey(), 'download' => 1]))
        ->not->toContain('fue revertido');
});
