<?php

declare(strict_types=1);

use App\Models\Collection as BillingCollection;
use App\Support\Filament\GlobalSearchAffiliationCollectionExpirations;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

it('pickNextCollectionRow elige el primer registro con vencimiento en o después de hoy', function (): void {
    $today = Carbon::parse('2026-05-10');
    $rows = new EloquentCollection([
        new BillingCollection(['expiration_date' => '2026-03-01']),
        new BillingCollection(['expiration_date' => '2026-06-01']),
    ]);

    $row = GlobalSearchAffiliationCollectionExpirations::pickNextCollectionRow($today, $rows);

    expect($row)->not->toBeNull()
        ->and((string) $row->expiration_date)->toBe('2026-06-01');
});

it('con fechas d/m/Y ordena por calendario y elige el primer vencimiento en o después de hoy', function (): void {
    $today = Carbon::parse('2026-05-10');
    $rows = new EloquentCollection([
        new BillingCollection(['expiration_date' => '01/06/2026']),
        new BillingCollection(['expiration_date' => '01/03/2026']),
    ]);

    $row = GlobalSearchAffiliationCollectionExpirations::pickNextCollectionRow($today, $rows);

    expect($row)->not->toBeNull()
        ->and((string) $row->expiration_date)->toBe('01/06/2026');
});

it('con vencimientos d/m/Y todos en el pasado elige el más antiguo (primera cuota impaga)', function (): void {
    $today = Carbon::parse('2026-05-11');
    $rows = new EloquentCollection([
        new BillingCollection(['expiration_date' => '04/01/2026']),
        new BillingCollection(['expiration_date' => '04/04/2026']),
        new BillingCollection(['expiration_date' => '04/10/2025']),
    ]);

    $row = GlobalSearchAffiliationCollectionExpirations::pickNextCollectionRow($today, $rows);

    expect($row)->not->toBeNull()
        ->and((string) $row->expiration_date)->toBe('04/10/2025');
});

it('calcula días vencidos desde expiration_date en formato d/m/Y', function (): void {
    $days = GlobalSearchAffiliationCollectionExpirations::calendarDaysOverdueSinceStoredExpiration(
        '04/10/2025',
        Carbon::parse('2026-05-11'),
    );

    expect($days)->toBe(219);
});

it('parseStoredDateToStartOfDay interpreta d/m/Y como día/mes/año', function (): void {
    $parsed = GlobalSearchAffiliationCollectionExpirations::parseStoredDateToStartOfDay('04/01/2026');

    expect($parsed)->not->toBeNull()
        ->and($parsed->toDateString())->toBe('2026-01-04');
});

it('elige la primera fecha de vencimiento en o después de hoy como próximo pago', function (): void {
    $today = Carbon::parse('2026-05-10');
    $dates = [
        Carbon::parse('2026-03-01')->startOfDay(),
        Carbon::parse('2026-06-01')->startOfDay(),
    ];

    $next = GlobalSearchAffiliationCollectionExpirations::pickNextPaymentDate($today, $dates);

    expect($next)->not->toBeNull()
        ->and($next->toDateString())->toBe('2026-06-01');
});

it('si todas las fechas son pasadas usa la más antigua como referencia de próximo pago', function (): void {
    $today = Carbon::parse('2026-05-10');
    $dates = [
        Carbon::parse('2026-03-01')->startOfDay(),
        Carbon::parse('2026-04-01')->startOfDay(),
    ];

    $next = GlobalSearchAffiliationCollectionExpirations::pickNextPaymentDate($today, $dates);

    expect($next)->not->toBeNull()
        ->and($next->toDateString())->toBe('2026-03-01');
});

it('si hoy coincide con un vencimiento lo toma como próximo pago', function (): void {
    $today = Carbon::parse('2026-05-10');
    $dates = [
        Carbon::parse('2026-05-10')->startOfDay(),
        Carbon::parse('2026-06-01')->startOfDay(),
    ];

    $next = GlobalSearchAffiliationCollectionExpirations::pickNextPaymentDate($today, $dates);

    expect($next)->not->toBeNull()
        ->and($next->toDateString())->toBe('2026-05-10');
});
