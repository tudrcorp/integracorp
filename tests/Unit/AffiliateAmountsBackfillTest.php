<?php

declare(strict_types=1);

use App\Models\Affiliate;
use App\Models\Affiliation;
use App\Support\Affiliations\AffiliateAmountsBackfill;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

beforeEach(fn () => DB::beginTransaction());
afterEach(fn () => DB::rollBack());

/**
 * Una afiliación individual vigente con un solo afiliado y montos que cuadran, para vaciarlos.
 */
function backfillPricedAffiliation(): ?Affiliation
{
    return Affiliation::query()
        ->where('status', 'ACTIVA')
        ->where('fee_anual', '>', 0)
        ->whereHas('affiliates', fn ($query) => $query->where('fee', '>', 0), '=', 1)
        ->whereDoesntHave('affiliates', fn ($query) => $query->where(fn ($inner) => $inner->whereNull('fee')->orWhere('fee', '<=', 0)))
        ->latest('id')
        ->limit(200)
        ->get()
        ->first(function (Affiliation $affiliation): bool {
            $affiliate = Affiliate::query()->where('affiliation_id', $affiliation->getKey())->first();

            if ($affiliate === null || abs((float) $affiliate->fee - (float) $affiliation->fee_anual) >= 0.01) {
                return false;
            }

            // Que el cálculo de hoy dé la misma tarifa (la edad o el tarifario pudieron cambiar).
            $amounts = app(App\Support\AffiliationAffiliateFeeCalculator::class)->calculateAffiliateAmounts($affiliation, $affiliate);

            return $amounts !== null
                && abs(round((float) $amounts['annual_fee'] + App\Support\Upgrades\IndividualAffiliateUpgradeManager::activeTotalFor($affiliate), 2) - (float) $affiliate->fee) < 0.01;
        });
}

it('completa los afiliados sin montos sin tocar la tarifa de la afiliación y deja bitácora', function (): void {
    $affiliation = backfillPricedAffiliation();

    if ($affiliation === null) {
        $this->markTestSkipped('No hay una afiliación de referencia en esta base.');
    }

    $affiliate = Affiliate::query()->where('affiliation_id', $affiliation->getKey())->first();
    $expectedFee = (float) $affiliate->fee;
    $affiliate->forceFill(['plan_id' => null, 'coverage_id' => null, 'age_range_id' => null, 'fee' => null, 'total_amount' => null])->save();
    $feeBefore = (string) $affiliation->fee_anual;
    $totalBefore = (string) $affiliation->total_amount;

    $backfill = AffiliateAmountsBackfill::make();
    $plan = $backfill->plan($affiliation);

    expect($plan['status'])->toBe(AffiliateAmountsBackfill::STATUS_READY)
        ->and((float) $plan['lines'][0]['annual_fee'])->toBe($expectedFee)
        ->and(Affiliate::query()->find($affiliate->getKey())->fee)->toBeNull();

    $result = $backfill->apply($affiliation, 'SISTEMA (test)');
    $affiliate->refresh();
    $affiliation->refresh();

    expect($result['status'])->toBe(AffiliateAmountsBackfill::STATUS_READY)
        ->and((float) $affiliate->fee)->toBe($expectedFee)
        ->and($affiliate->plan_id)->not->toBeNull()
        ->and($affiliate->age_range_id)->not->toBeNull()
        ->and((string) $affiliation->fee_anual)->toBe($feeBefore)
        ->and((string) $affiliation->total_amount)->toBe($totalBefore)
        ->and($affiliation->status_log_affiliations()->where('action', AffiliateAmountsBackfill::LOG_ACTION)->exists())->toBeTrue()
        ->and($backfill->apply($affiliation, 'SISTEMA (test)')['status'])->toBe(AffiliateAmountsBackfill::STATUS_NOTHING);
});

it('si la suma no cuadra con la tarifa de la afiliación no cambia nada', function (): void {
    $affiliation = backfillPricedAffiliation();

    if ($affiliation === null) {
        $this->markTestSkipped('No hay una afiliación de referencia en esta base.');
    }

    $affiliate = Affiliate::query()->where('affiliation_id', $affiliation->getKey())->first();
    $affiliate->forceFill(['fee' => null, 'total_amount' => null])->save();
    $affiliation->forceFill(['fee_anual' => round((float) $affiliation->fee_anual + 100, 2)])->save();
    $logsBefore = $affiliation->status_log_affiliations()->count();

    $result = AffiliateAmountsBackfill::make()->apply($affiliation, 'SISTEMA (test)');

    expect($result['status'])->toBe(AffiliateAmountsBackfill::STATUS_MISMATCH)
        ->and($result['message'])->toContain('no cuadra')
        ->and(Affiliate::query()->find($affiliate->getKey())->fee)->toBeNull()
        ->and($affiliation->status_log_affiliations()->count())->toBe($logsBefore);
});

it('el comando sin --execute solo muestra la vista previa', function (): void {
    $affiliation = backfillPricedAffiliation();

    if ($affiliation === null) {
        $this->markTestSkipped('No hay una afiliación de referencia en esta base.');
    }

    $affiliate = Affiliate::query()->where('affiliation_id', $affiliation->getKey())->first();
    $affiliate->forceFill(['fee' => null])->save();

    Artisan::call('affiliations:fill-affiliate-amounts', ['codes' => [$affiliation->code]]);

    expect(Artisan::output())->toContain('vista previa')->toContain('[LISTA]')
        ->and(Affiliate::query()->find($affiliate->getKey())->fee)->toBeNull();
});

it('el comando exige códigos o --missing y avisa los códigos que no existen', function (): void {
    expect(Artisan::call('affiliations:fill-affiliate-amounts'))->toBe(1)
        ->and(Artisan::output())->toContain('--missing')
        ->and(Artisan::call('affiliations:fill-affiliate-amounts', ['codes' => ['TDEC-IND-NO-EXISTE']]))->toBe(1)
        ->and(Artisan::output())->toContain('No se encontraron: TDEC-IND-NO-EXISTE');
});
