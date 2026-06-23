<?php

declare(strict_types=1);

use App\Http\Controllers\AgencyController;

uses(Tests\TestCase::class);

it('asigna owner_code igual al code para agencia master independiente', function (): void {
    expect(AgencyController::resolveOwnerCodeForAgency(1, 'TDG-120'))->toBe('TDG-120');
});

it('asigna TDG-100 como owner_code para agencia master bajo TDG', function (): void {
    expect(AgencyController::resolveOwnerCodeForAgency(1, 'TDG-120', 'TDG-100'))->toBe('TDG-100')
        ->and(AgencyController::resolveOwnerCodeForAgency(1, 'TDG-120', 'TDG'))->toBe('TDG-100');
});

it('asigna codigo de agencia master como owner_code para agencia general', function (): void {
    expect(AgencyController::resolveOwnerCodeForAgency(3, 'TDG-193', 'TDG-120'))->toBe('TDG-120')
        ->and(AgencyController::resolveOwnerCodeForAgency(3, 'TDG-193', 'TDG'))->toBe('TDG-100');
});
