<?php

declare(strict_types=1);

use App\Filament\Business\Resources\Agencies\Widgets\TotalEstructureAgency;
use App\Models\Agency;
use Illuminate\Support\Collection;

it('solo agrega estructuras con Master activa existente en el consolidado', function (): void {
    $widget = new class TotalEstructureAgencyAggregateMasterCodesTest TotalEstructureAgency
    {
        /**
         * @param  Collection<int, Agency>  $agencies
         * @return list<string>
         */
        public function testResolveAggregateMasterCodes(Collection $agencies): array
        {
            return $this->resolveAggregateMasterCodes($agencies);
        }
    };

    $agencies = collect([
        new Agency([
            'code' => 'M001',
            'owner_code' => 'M001',
            'agency_type_id' => 1,
            'status' => 'ACTIVO',
        ]),
        new Agency([
            'code' => 'G001',
            'owner_code' => 'M001',
            'agency_type_id' => 3,
            'status' => 'ACTIVO',
        ]),
        // General con owner_code huérfano: NO debería crear una "estructura" en el consolidado.
        new Agency([
            'code' => 'G999',
            'owner_code' => 'M999',
            'agency_type_id' => 3,
            'status' => 'ACTIVO',
        ]),
        // Master inactiva: no debe contar para el consolidado.
        new Agency([
            'code' => 'M002',
            'owner_code' => 'M002',
            'agency_type_id' => 1,
            'status' => 'INACTIVO',
        ]),
    ]);

    expect($widget->testResolveAggregateMasterCodes($agencies))->toBe(['M001']);
});
