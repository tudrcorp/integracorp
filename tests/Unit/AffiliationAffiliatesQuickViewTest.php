<?php

declare(strict_types=1);

use App\Filament\Business\Resources\AffiliationCorporates\AffiliationCorporateResource;
use App\Filament\Business\Resources\AffiliationCorporates\Pages\EditAffiliationCorporate;
use App\Filament\Business\Resources\AffiliationCorporates\Pages\ManageAffiliationCorporateAffiliates;
use App\Filament\Business\Resources\AffiliationCorporates\RelationManagers\CorporateAffiliatesQuickRelationManager;
use App\Filament\Business\Resources\AffiliationCorporates\RelationManagers\CorporateAffiliatesRelationManager;
use App\Filament\Business\Resources\Affiliations\AffiliationResource;
use App\Filament\Business\Resources\Affiliations\Pages\ManageAffiliationAffiliates;
use App\Models\AffiliateCorporate;
use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    DB::beginTransaction();
    Mail::fake();
    Queue::fake();
    Filament\Facades\Filament::setCurrentPanel('business');
});

afterEach(function (): void {
    DB::rollBack();
});

function aqvUser(array $departments = ['SUPERADMIN', 'NEGOCIOS']): User
{
    return User::factory()->make(['id' => 987960, 'name' => 'Usuario Afiliados Pest', 'email' => 'afiliados-pest-'.uniqid().'@tudrencasa.com', 'status' => 'ACTIVO', 'departament' => $departments]);
}

function aqvCorporate(): AffiliationCorporate
{
    $owner = AffiliationCorporate::query()->whereHas('affiliationCorporatePlans')->firstOrFail()->replicate();
    $owner->forceFill(['code' => 'PEST-COR-'.Str::upper(Str::random(6)), 'name_corporate' => 'EMPRESA VISTA AFILIADOS PEST', 'poblation' => 9, 'fee_anual' => 50, 'total_amount' => 12.5, 'payment_frequency' => 'TRIMESTRAL', 'status' => 'ACTIVA'])->save();

    $affiliate = new AffiliateCorporate;
    $affiliate->forceFill([
        'affiliation_corporate_id' => $owner->id, 'affiliation_type' => 'ESTANDARD', 'first_name' => 'PRUDENCIA', 'last_name' => 'VISTAPEST ARELLANO',
        'nro_identificacion' => (string) random_int(80000000, 89999999), 'age' => 40, 'condition_medical' => 'SANO', 'fee' => 120, 'subtotal_anual' => 120,
        'payment_frequency' => 'TRIMESTRAL', 'status' => 'ACTIVO', 'created_by' => 1,
    ])->save();

    return $owner;
}

it('la pantalla corporativa muestra los datos principales y la misma gestión de afiliados de la edición', function (): void {
    $owner = aqvCorporate();

    Livewire::actingAs(aqvUser())
        ->test(ManageAffiliationCorporateAffiliates::class, ['record' => $owner->id])
        ->assertOk()
        ->assertSee('Afiliados · '.$owner->code)
        ->assertSee('EMPRESA VISTA AFILIADOS PEST')
        ->assertSee('Población activa')
        ->assertSee('1 pers.')
        ->assertSee('no cuadran con la población activa')
        ->assertSee('corporate-affiliates-quick-relation-manager');

    $table = Livewire::actingAs(aqvUser())
        ->test(CorporateAffiliatesQuickRelationManager::class, ['ownerRecord' => $owner, 'pageClass' => EditAffiliationCorporate::class])
        ->call('loadTable')
        ->assertOk()
        ->assertSee('PRUDENCIA')
        ->assertTableActionExists('edit');

    expect($table->instance()->getTable()->getPollingInterval())->toBeNull()
        ->and(is_subclass_of(CorporateAffiliatesQuickRelationManager::class, CorporateAffiliatesRelationManager::class))->toBeTrue();
});

it('la pantalla individual muestra titular y plan y la tabla del grupo familiar de la edición', function (): void {
    $affiliation = Affiliation::query()->whereHas('affiliates')->whereNull('deleted_at')->latest('id')->firstOrFail();

    Livewire::actingAs(aqvUser())
        ->test(ManageAffiliationAffiliates::class, ['record' => $affiliation->id])
        ->assertOk()
        ->assertSee('Afiliados · '.$affiliation->code)
        ->assertSee('Titular')
        ->assertSee('Afiliados activos')
        ->assertSee('affiliates-relation-manager');
});

it('sin acceso al recurso las pantallas responden 403', function (): void {
    $owner = aqvCorporate();

    Livewire::actingAs(aqvUser(['MARKETING']))
        ->test(ManageAffiliationCorporateAffiliates::class, ['record' => $owner->id])
        ->assertForbidden();
});

it('las tablas de afiliaciones tienen el botón «Ver afiliado(s)» como primera columna con el enlace a la pantalla', function (): void {
    $owner = aqvCorporate();

    expect(AffiliationCorporateResource::getUrl('affiliates', ['record' => $owner]))->toEndWith('/affiliation-corporates/'.$owner->id.'/afiliados')
        ->and(AffiliationResource::getUrl('affiliates', ['record' => 1]))->toEndWith('/affiliations/1/afiliados');

    Livewire::actingAs(aqvUser())
        ->test(App\Filament\Business\Resources\AffiliationCorporates\Pages\ListAffiliationCorporates::class)
        ->call('loadTable')
        ->searchTable($owner->code)
        ->assertTableColumnStateSet('view_affiliates', 'Ver afiliado(s)', $owner);

    foreach (['app/Filament/Business/Resources/AffiliationCorporates/Tables/AffiliationCorporatesTable.php', 'app/Filament/Business/Resources/Affiliations/Tables/AffiliationsTable.php'] as $file) {
        $source = (string) file_get_contents(base_path($file));
        expect(strpos($source, "TextColumn::make('view_affiliates')"))->toBeLessThan(strpos($source, "TextColumn::make('code')"));
    }
});

it('la tabla de afiliados corporativos muestra el voucher por beneficio vigente, no el voucher único anterior', function (): void {
    $rm = CorporateAffiliatesRelationManager::class;
    $affiliate = (new AffiliateCorporate)->forceFill(['vaucherIls' => 'TD-AGACGH', 'dateInit' => '04/05/2026', 'dateEnd' => '30/10/2026', 'numberDays' => 179]);

    $voucher = fn (string $code, string $from, string $to): App\Models\AffiliateCorporateIlsVoucher => (new App\Models\AffiliateCorporateIlsVoucher)->forceFill(['voucher_code' => $code, 'date_init' => $from, 'date_end' => $to, 'number_days' => 179]);

    $affiliate->setRelation('ilsVouchers', new Illuminate\Database\Eloquent\Collection([$voucher('TDC-7A8C82', '2026-10-30', '2027-04-27')]));
    expect($rm::currentIlsVoucherCodes($affiliate))->toBe('TDC-7A8C82')
        ->and($rm::currentIlsPeriod($affiliate))->toMatchArray(['from' => '30/10/2026', 'to' => '27/04/2027', 'end' => '2027-04-27']);

    $affiliate->setRelation('ilsVouchers', new Illuminate\Database\Eloquent\Collection([$voucher('TDC-B', '2026-11-01', '2027-06-01'), $voucher('TDC-A', '2026-10-01', '2027-03-01')]));
    expect($rm::currentIlsVoucherCodes($affiliate))->toBe('TDC-B +1')
        ->and($rm::currentIlsPeriod($affiliate))->toMatchArray(['from' => '01/10/2026', 'to' => '01/03/2027']);

    $affiliate->setRelation('ilsVouchers', new Illuminate\Database\Eloquent\Collection);
    expect($rm::currentIlsVoucherCodes($affiliate))->toBe('TD-AGACGH')
        ->and($rm::currentIlsPeriod($affiliate)['to'])->toBe('30/10/2026');

    $source = (string) file_get_contents(base_path('app/Filament/Business/Resources/AffiliationCorporates/RelationManagers/CorporateAffiliatesRelationManager.php'));
    expect($source)->toContain("TextColumn::make('ils_current_voucher')")
        ->and($source)->toContain("TextColumn::make('ils_current_from')")
        ->and($source)->not->toContain("TextColumn::make('dateInit')");
});
