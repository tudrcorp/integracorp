<?php

declare(strict_types=1);

use App\Filament\Business\Resources\TravelAgencies\Tables\TravelAgenciesTable;
use App\Filament\Business\Resources\TravelAgents\Tables\TravelAgentsTable;

it('define el configurador de tabla de agentes de viaje', function (): void {
    expect(method_exists(TravelAgentsTable::class, 'configure'))->toBeTrue();
});

it('define el configurador de tabla de agencias de viaje', function (): void {
    expect(method_exists(TravelAgenciesTable::class, 'configure'))->toBeTrue();
});
