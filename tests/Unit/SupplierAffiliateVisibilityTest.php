<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\AffiliateCorporates\AffiliateCorporateResource;
use App\Filament\Operations\Resources\Affiliates\AffiliateResource;
use App\Filament\Operations\Resources\CompanyAssociates\NuevosNegociosAssociateResource;
use App\Models\Affiliate;
use App\Models\AffiliateCorporate;
use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\Company;
use App\Models\CompanyAssociate;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Exports\AffiliateCorporateCsvExportService;
use App\Support\Exports\AffiliateCsvExportService;
use App\Support\Exports\CorporateAffiliationsExportService;
use App\Support\Exports\IndividualAffiliationsExportService;
use App\Support\Operations\SupplierAffiliateVisibility;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Once;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    DB::beginTransaction();
    Once::flush();

    $this->supplier = Supplier::query()->create([
        'name' => 'PROVEEDOR PRUEBA VISIBILIDAD',
        'gestion_integracorp' => true,
        'integracorp_alias' => 'ZZPRUEBA PROV',
    ]);

    $now = now();

    $affiliationFor = fn (?string $providers, string $code): int => DB::table('affiliations')->insertGetId([
        'code' => $code, 'code_agency' => 'TDG-100', 'status' => 'ACTIVA',
        'service_providers' => $providers, 'created_at' => $now, 'updated_at' => $now,
    ]);
    $affiliateFor = fn (int $affiliationId, string $name): int => DB::table('affiliates')->insertGetId([
        'affiliation_id' => $affiliationId, 'full_name' => $name, 'nro_identificacion' => 'V-0',
        'sex' => 'M', 'relationship' => 'TITULAR', 'status' => 'ACTIVO', 'created_at' => $now, 'updated_at' => $now,
    ]);

    $this->ownAffiliate = $affiliateFor($affiliationFor('["ZZPRUEBA PROV","ILS"]', 'ZZTEST-1'), 'PROPIO');
    $this->foreignAffiliate = $affiliateFor($affiliationFor('["ILS"]', 'ZZTEST-2'), 'AJENO');
    $this->invalidJsonAffiliate = $affiliateFor($affiliationFor('', 'ZZTEST-3'), 'JSON VACIO');
    $this->nullAffiliate = $affiliateFor($affiliationFor(null, 'ZZTEST-4'), 'SIN PROVEEDOR');
    $this->individualIds = [$this->ownAffiliate, $this->foreignAffiliate, $this->invalidJsonAffiliate, $this->nullAffiliate];

    $corporateFor = fn (?string $providers): int => DB::table('affiliation_corporates')->insertGetId([
        'email_contact' => 'z@z.com', 'fee_anual' => 0, 'code_agency' => 'TDG-100', 'name_corporate' => 'ZZ CORP',
        'rif' => 'J-0', 'address' => 'x', 'city_id' => 1, 'country_id' => 1, 'region_id' => 1, 'phone' => '0',
        'email' => 'z@z.com', 'full_name_contact' => 'x', 'nro_identificacion_contact' => '0', 'phone_contact' => '0',
        'corporate_quote_id' => 0, 'created_by' => 'test', 'status' => 'ACTIVA', 'payment_frequency' => 'ANUAL',
        'owner_code' => 'TDG-100', 'service_providers' => $providers, 'created_at' => $now, 'updated_at' => $now,
    ]);
    $this->ownCorporate = DB::table('affiliate_corporates')->insertGetId([
        'affiliation_corporate_id' => $corporateFor('["ZZPRUEBA PROV"]'), 'created_at' => $now, 'updated_at' => $now,
    ]);
    $this->foreignCorporate = DB::table('affiliate_corporates')->insertGetId([
        'affiliation_corporate_id' => $corporateFor('["TDEC"]'), 'created_at' => $now, 'updated_at' => $now,
    ]);
    $this->corporateIds = [$this->ownCorporate, $this->foreignCorporate];

    $associateFor = function (?array $providers) use ($now): int {
        $company = Company::query()->create(['name' => 'ZZ EMPRESA', 'rif' => 'J-1', 'service_providers' => $providers]);
        $responsibleId = DB::table('company_responsibles')->insertGetId([
            'company_id' => $company->id, 'full_name' => 'RESP', 'identity_card' => '0', 'created_at' => $now, 'updated_at' => $now,
        ]);

        return DB::table('company_associates')->insertGetId([
            'company_id' => $company->id, 'company_responsible_id' => $responsibleId, 'full_name' => 'ASOC',
            'identity_card' => '0', 'birth_date' => '2000-01-01', 'age' => 26, 'sex' => 'M', 'contact_full_name' => 'x',
            'identity_document' => 'x', 'registered_at' => $now, 'created_at' => $now, 'updated_at' => $now,
        ]);
    };
    $this->ownAssociate = $associateFor(['ZZPRUEBA PROV']);
    $this->foreignAssociate = $associateFor(['ILS']);
    $this->noProviderAssociate = $associateFor(null);
    $this->associateIds = [$this->ownAssociate, $this->foreignAssociate, $this->noProviderAssociate];
});

afterEach(function (): void {
    DB::rollBack();
    Once::flush();
});

function actAsSupplierUser(?int $supplierId): void
{
    Once::flush();
    Auth::setUser(User::factory()->make(['id' => 999999, 'supplier_id' => $supplierId]));
}

it('no restringe a los analistas internos sin proveedor', function (): void {
    actAsSupplierUser(null);

    expect(SupplierAffiliateVisibility::isRestricted())->toBeFalse()
        ->and(AffiliateResource::getEloquentQuery()->whereKey($this->individualIds)->count())->toBe(4)
        ->and(AffiliateCorporateResource::getEloquentQuery()->whereKey($this->corporateIds)->count())->toBe(2)
        ->and(NuevosNegociosAssociateResource::getEloquentQuery()->whereKey($this->associateIds)->count())->toBe(3);
});

it('el usuario del proveedor solo ve los afiliados individuales con su alias', function (): void {
    actAsSupplierUser($this->supplier->id);

    expect(AffiliateResource::getEloquentQuery()->whereKey($this->individualIds)->pluck('id')->all())
        ->toBe([$this->ownAffiliate])
        ->and(AffiliateResource::getEloquentQuery()->whereKey($this->foreignAffiliate)->exists())->toBeFalse();
});

it('el usuario del proveedor solo ve los afiliados corporativos con su alias', function (): void {
    actAsSupplierUser($this->supplier->id);

    expect(AffiliateCorporateResource::getEloquentQuery()->whereKey($this->corporateIds)->pluck('id')->all())
        ->toBe([$this->ownCorporate]);
});

it('el usuario del proveedor solo ve asociados de empresas con su alias', function (): void {
    actAsSupplierUser($this->supplier->id);

    expect(NuevosNegociosAssociateResource::getEloquentQuery()->whereKey($this->associateIds)->pluck('id')->all())
        ->toBe([$this->ownAssociate]);
});

it('no ve ningún afiliado si el proveedor no tiene alias', function (): void {
    $this->supplier->forceFill(['integracorp_alias' => null])->save();
    actAsSupplierUser($this->supplier->id);

    expect(SupplierAffiliateVisibility::restriction())->toBe(SupplierAffiliateVisibility::DENY_ALL)
        ->and(AffiliateResource::getEloquentQuery()->whereKey($this->individualIds)->count())->toBe(0)
        ->and(AffiliateCorporateResource::getEloquentQuery()->whereKey($this->corporateIds)->count())->toBe(0)
        ->and(NuevosNegociosAssociateResource::getEloquentQuery()->whereKey($this->associateIds)->count())->toBe(0);
});

it('no ve ningún afiliado si el proveedor no tiene la gestión activa o no existe', function (): void {
    $this->supplier->forceFill(['gestion_integracorp' => false])->save();
    actAsSupplierUser($this->supplier->id);

    expect(AffiliateResource::getEloquentQuery()->whereKey($this->individualIds)->count())->toBe(0);

    actAsSupplierUser(987654321);

    expect(AffiliateResource::getEloquentQuery()->whereKey($this->individualIds)->count())->toBe(0);
});

it('aplica el filtro a las exportaciones y reportes', function (): void {
    actAsSupplierUser($this->supplier->id);

    expect(AffiliateCsvExportService::query(['affiliate_ids' => $this->individualIds], 'business')->pluck('id')->all())
        ->toBe([$this->ownAffiliate])
        ->and(AffiliateCorporateCsvExportService::query(['affiliate_corporate_ids' => $this->corporateIds], 'operations')->pluck('id')->all())
        ->toBe([$this->ownCorporate])
        ->and(IndividualAffiliationsExportService::affiliationQuery()->where('code', 'like', 'ZZTEST-%')->pluck('code')->all())
        ->toBe(['ZZTEST-1'])
        ->and(CorporateAffiliationsExportService::affiliationQuery()->where('name_corporate', 'ZZ CORP')->count())
        ->toBe(1);
});

it('reconoce alias con acentos guardados con escapes JSON', function (): void {
    $this->supplier->forceFill(['integracorp_alias' => 'CLÍNICA ZZ'])->save();
    $affiliation = Affiliation::query()->whereKey(Affiliate::query()->whereKey($this->foreignAffiliate)->value('affiliation_id'))->first();
    $affiliation->forceFill(['service_providers' => ['CLÍNICA ZZ']])->saveQuietly();
    actAsSupplierUser($this->supplier->id);

    expect(AffiliateResource::getEloquentQuery()->whereKey($this->individualIds)->pluck('id')->all())
        ->toBe([$this->foreignAffiliate]);
});

it('conecta el filtro en la navegación, el widget y el export de Nuevos Negocios', function (): void {
    $root = dirname(__DIR__, 2);

    expect(file_get_contents($root.'/app/Filament/Operations/Widgets/AffiliationChart.php'))
        ->toContain('SupplierAffiliateVisibility::applyToAffiliates(Affiliate::query())')
        ->toContain('SupplierAffiliateVisibility::applyToAffiliations(Affiliation::query())')
        ->and(file_get_contents($root.'/app/Http/Controllers/CompanyAssociateExportCsvController.php'))
        ->toContain('SupplierAffiliateVisibility::applyToCompanyAssociates(CompanyAssociate::query(), $user)')
        ->and(file_get_contents($root.'/app/Filament/Operations/Resources/Affiliates/AffiliateResource.php'))
        ->toContain('$todayCount = static::getEloquentQuery()')
        ->and(file_get_contents($root.'/app/Filament/Operations/Resources/CompanyAssociates/NuevosNegociosAssociateResource.php'))
        ->toContain('SupplierAffiliateVisibility::applyToCompanyAssociates(static::getModel()::query())');
});

it('la empresa guarda sus proveedores de servicios y el formulario usa el campo compartido', function (): void {
    $company = Company::query()->create(['name' => 'ZZ EMPRESA 2', 'rif' => 'J-2', 'service_providers' => ['ILS']]);

    expect($company->refresh()->service_providers)->toBe(['ILS'])
        ->and(file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Companies/Schemas/CompanyForm.php'))
        ->toContain('AffiliationServiceProvidersField::make()')
        ->and(AffiliationCorporate::class)->toBeString()
        ->and(AffiliateCorporate::class)->toBeString()
        ->and(CompanyAssociate::class)->toBeString();
});
