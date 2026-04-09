<?php

declare(strict_types=1);

use App\Filament\Business\Resources\Agents\Widgets\TotalSaleMonthlyNowVsLastAgent;
use Carbon\Carbon;
use Tests\TestCase;

uses(TestCase::class);

afterEach(function (): void {
    Carbon::setTestNow();
});

it('en marzo del año en curso ofrece solo meses anteriores del mismo año, del más reciente al más antiguo', function () {
    Carbon::setTestNow(Carbon::parse('2026-03-10 12:00:00'));

    $method = new ReflectionMethod(TotalSaleMonthlyNowVsLastAgent::class, 'getFilters');
    $widget = new TotalSaleMonthlyNowVsLastAgent;
    /** @var array<string, string>|null $filters */
    $filters = $method->invoke($widget);

    expect($filters)->toBeArray()
        ->and(array_keys($filters ?? []))->toBe(['2026-02', '2026-01'])
        ->and($filters['2026-02'])->toContain('2026')
        ->and($filters['2026-01'])->toContain('2026');
});

it('en enero no ofrece filtros de comparación en el año en curso', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-15 12:00:00'));

    $method = new ReflectionMethod(TotalSaleMonthlyNowVsLastAgent::class, 'getFilters');
    $widget = new TotalSaleMonthlyNowVsLastAgent;

    expect($method->invoke($widget))->toBeNull();
});
