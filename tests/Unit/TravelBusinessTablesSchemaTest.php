<?php

declare(strict_types=1);

use App\Filament\Business\Resources\BusinessAppointments\Tables\BusinessAppointmentsTable;
use App\Filament\Business\Resources\ProspectAgents\Tables\ProspectAgentsTable;
use App\Filament\Business\Resources\TravelAgencies\Tables\TravelAgenciesTable;
use App\Filament\Business\Resources\TravelAgents\Tables\TravelAgentsTable;

it('define el configurador de tabla de agentes de viaje', function (): void {
    expect(method_exists(TravelAgentsTable::class, 'configure'))->toBeTrue();
});

it('define el configurador de tabla de agencias de viaje', function (): void {
    expect(method_exists(TravelAgenciesTable::class, 'configure'))->toBeTrue();
});

it('define el configurador de tabla de prospectos', function (): void {
    expect(method_exists(ProspectAgentsTable::class, 'configure'))->toBeTrue();
});

it('define el configurador de tabla de citas de negocios', function (): void {
    expect(method_exists(BusinessAppointmentsTable::class, 'configure'))->toBeTrue();
});
