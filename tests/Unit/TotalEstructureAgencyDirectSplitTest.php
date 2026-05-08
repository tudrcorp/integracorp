<?php

declare(strict_types=1);

use App\Filament\Business\Resources\Agencies\Widgets\TotalEstructureAgency;
use App\Models\Agency;

it('clasifica ventas directas en bucket master cuando la agencia es master valida', function (): void {
    $widget = new class extends TotalEstructureAgency
    {
        /**
         * @return array{0: float, 1: float}
         */
        public function testSplitDirectSalesByAgencyType(Agency $agency, float $ventasDirectas): array
        {
            return $this->splitDirectSalesByAgencyType($agency, $ventasDirectas);
        }
    };

    $agency = new Agency([
        'code' => 'M001',
        'owner_code' => 'M001',
        'agency_type_id' => 1,
        'status' => 'ACTIVO',
    ]);

    expect($widget->testSplitDirectSalesByAgencyType($agency, 1500.50))
        ->toBe([1500.50, 0.0]);
});

it('clasifica ventas directas en bucket general cuando la agencia no es master valida', function (): void {
    $widget = new class extends TotalEstructureAgency
    {
        /**
         * @return array{0: float, 1: float}
         */
        public function testSplitDirectSalesByAgencyType(Agency $agency, float $ventasDirectas): array
        {
            return $this->splitDirectSalesByAgencyType($agency, $ventasDirectas);
        }
    };

    $agency = new Agency([
        'code' => 'G001',
        'owner_code' => 'M001',
        'agency_type_id' => 3,
        'status' => 'ACTIVO',
    ]);

    expect($widget->testSplitDirectSalesByAgencyType($agency, 800.00))
        ->toBe([0.0, 800.00]);
});
