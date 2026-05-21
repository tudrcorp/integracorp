<?php

declare(strict_types=1);

it('define los campos requeridos en la migración de operation_quote_generators', function (): void {
    $path = dirname(__DIR__, 2).'/database/migrations/2026_05_18_131052_create_operation_quote_generators_table.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("Schema::create('operation_quote_generators'")
        ->and($contents)->toContain("foreignId('telemedicine_patient_id')")
        ->and($contents)->toContain("foreignId('telemedicine_case_id')")
        ->and($contents)->toContain("foreignId('operation_coordination_service_id')")
        ->and($contents)->toContain("json('items')")
        ->and($contents)->toContain("decimal('costo_dolares'")
        ->and($contents)->toContain("decimal('costo_bolivares'")
        ->and($contents)->toContain("decimal('porcentaje_ganancia'")
        ->and($contents)->toContain("decimal('subtotal'")
        ->and($contents)->toContain("decimal('total'");
});
