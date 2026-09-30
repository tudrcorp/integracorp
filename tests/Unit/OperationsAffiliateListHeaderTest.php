<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\AffiliateCorporates\AffiliateCorporateResource;
use App\Filament\Operations\Resources\Affiliates\AffiliateResource;
use App\Filament\Operations\Resources\CompanyAssociates\NuevosNegociosAssociateResource;
use App\Support\Operations\AffiliateListHeaderSummary;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

beforeEach(fn () => Auth::forgetUser());

it('los conteos de afiliados individuales cuadran con la tabla sin restricción de proveedor', function (): void {
    $summary = AffiliateListHeaderSummary::forAffiliates(AffiliateResource::getEloquentQuery());
    $base = AffiliateResource::getEloquentQuery();

    expect($summary['total'])->toBe((clone $base)->count())
        ->and($summary['active'])->toBe((clone $base)->where('status', 'ACTIVO')->count())
        ->and($summary['pre_approved'])->toBe((clone $base)->where('status', 'PRE-APROBADA')->count())
        ->and($summary['excluded'])->toBe((clone $base)->where('status', 'EXCLUIDO')->count())
        ->and($summary['today'])->toBe((clone $base)->where('status', 'ACTIVO')->whereDate('created_at', today())->count())
        ->and($summary['companies'])->toBe(0);
});

it('los conteos de afiliados corporativos incluyen las afiliaciones distintas', function (): void {
    $summary = AffiliateListHeaderSummary::forAffiliates(AffiliateCorporateResource::getEloquentQuery(), companyColumn: 'affiliation_corporate_id');
    $base = AffiliateCorporateResource::getEloquentQuery();

    expect($summary['total'])->toBe((clone $base)->count())
        ->and($summary['active'])->toBe((clone $base)->where('status', 'ACTIVO')->count())
        ->and($summary['companies'])->toBe((clone $base)->distinct()->count('affiliation_corporate_id'));
});

it('los conteos de nuevos negocios separan activos, sin voucher y anulados', function (): void {
    $summary = AffiliateListHeaderSummary::forCompanyAssociates(NuevosNegociosAssociateResource::getEloquentQuery());
    $base = fn () => DB::table('company_associates');

    expect($summary['total'])->toBe(NuevosNegociosAssociateResource::getEloquentQuery()->count())
        ->and($summary['active'])->toBe($base()->where('status', 'ACTIVO')->count())
        ->and($summary['without_voucher'])->toBe($base()->where('status', 'ACTIVO-SIN-VAUCHER-ILS')->count())
        ->and($summary['cancelled'])->toBe($base()->where('status', 'ANULADO')->count())
        ->and($summary['companies'])->toBe($base()->distinct()->count('company_id'));
});

it('las tres páginas usan el encabezado compartido sobre la consulta del recurso (filtro por proveedor incluido)', function (string $page, string $resource): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/'.$page);

    expect($source)
        ->toContain('filament.operations.partials.list-header')
        ->toContain($resource.'::getEloquentQuery()');
})->with([
    'individuales' => ['Affiliates/Pages/ListAffiliates.php', 'AffiliateResource'],
    'corporativos' => ['AffiliateCorporates/Pages/ListAffiliateCorporates.php', 'AffiliateCorporateResource'],
    'nuevos negocios' => ['CompanyAssociates/Pages/ListCompanyAssociates.php', 'NuevosNegociosAssociateResource'],
]);

it('el encabezado compartido pinta en gris los indicadores en cero y oculta los chips sin registros', function (): void {
    $stats = [
        ['label' => 'Activos', 'value' => 1500, 'icon' => 'heroicon-m-check-circle', 'tone' => 'success', 'hint' => 'Vigentes'],
        ['label' => 'Excluidos', 'value' => 0, 'icon' => 'heroicon-m-no-symbol', 'tone' => 'danger', 'hint' => 'Retirados'],
    ];

    $html = view('filament.operations.partials.list-header', [
        'icon' => 'heroicon-o-user', 'eyebrow' => 'Afiliados · TDEC', 'title' => 'Afiliados individuales',
        'total' => 2300, 'description' => 'Descripción', 'stats' => $stats,
    ])->render();

    $empty = view('filament.operations.partials.list-header', [
        'title' => 'Afiliados individuales', 'total' => 0, 'stats' => $stats,
    ])->render();

    expect($html)
        ->toContain('Afiliados · TDEC')
        ->toContain('2.300')
        ->toContain('1.500')
        ->toContain('text-emerald-700')
        ->not->toContain('text-rose-700')
        ->and($empty)->not->toContain('Activos');
});
