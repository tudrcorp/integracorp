<?php

declare(strict_types=1);

use App\Filament\Administration\Resources\AnnualCollections\AnnualCollectionResource;
use App\Filament\Administration\Resources\Collections\Pages\ListCollections;
use App\Models\Collection;
use App\Models\User;
use App\Support\Collections\CollectionReceivableReport;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * Gestión de Cobranza no muestra ni suma cuotas de afiliaciones excluidas, inactivas o
 * anuladas: sus totales cuadran con Cuentas por cobrar. Solo lee la base.
 */
beforeEach(function (): void {
    Filament::setCurrentPanel('administration');
    Auth::setUser(User::factory()->make([
        'id' => 1,
        'email' => 'prueba@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['SUPERADMIN', 'ADMINISTRACION'],
        'is_superAdmin' => true,
    ]));
});

function gestionFilteredQuery(): Illuminate\Database\Eloquent\Builder
{
    return Livewire::test(ListCollections::class)->instance()->getFilteredTableQuery();
}

it('la tabla de Gestión no trae cuotas de afiliaciones dadas de baja', function (): void {
    $excluded = CollectionReceivableReport::EXCLUDED_AFFILIATION_STATUSES;

    $leaked = gestionFilteredQuery()
        ->where(function ($query) use ($excluded): void {
            $query->whereHas('affiliationByCode', fn ($affiliation) => $affiliation->whereIn('status', $excluded))
                ->orWhereHas('affiliationCorporateByCode', fn ($affiliation) => $affiliation->whereIn('status', $excluded));
        })
        ->count();

    expect($leaked)->toBe(0);
});

it('por cobrar y vencido de Gestión cuadran con Cuentas por cobrar', function (): void {
    $today = CarbonImmutable::today()->toDateString();
    $pending = gestionFilteredQuery()->reorder()->where('collections.status', CollectionReceivableReport::PENDING_STATUS);
    $receivable = CollectionReceivableReport::summary(AnnualCollectionResource::getEloquentQuery());

    expect((clone $pending)->count())->toBe($receivable['pending_count'])
        ->and(round((float) (clone $pending)->sum('total_amount'), 2))->toBe(round($receivable['pending_amount'], 2))
        ->and((clone $pending)->where('filter_next_payment_date', '<', $today)->count())->toBe($receivable['overdue_count'])
        ->and(round((float) (clone $pending)->where('filter_next_payment_date', '<', $today)->sum('total_amount'), 2))
        ->toBe(round($receivable['overdue_amount'], 2));
});

it('una cuota de afiliación dada de baja sigue resolviéndose por id (no rompe acciones abiertas)', function (): void {
    $excluded = Collection::query()
        ->whereHas('affiliationByCode', fn ($affiliation) => $affiliation->whereIn('status', CollectionReceivableReport::EXCLUDED_AFFILIATION_STATUSES))
        ->value('id');

    if ($excluded === null) {
        $this->markTestSkipped('No hay cuotas de afiliaciones dadas de baja.');
    }

    $table = Livewire::test(ListCollections::class)->instance()->getTable();

    expect($table->getQuery(isResolvingRecord: true)->whereKey($excluded)->exists())->toBeTrue()
        ->and($table->getQuery()->whereKey($excluded)->exists())->toBeFalse();
});
