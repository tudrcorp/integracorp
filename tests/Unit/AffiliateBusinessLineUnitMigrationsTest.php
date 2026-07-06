<?php

declare(strict_types=1);

it('define columnas de linea y unidad de negocio en affiliates', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_06_17_090546_add_business_line_and_business_unit_to_affiliates_table.php');

    expect($source)
        ->toContain("Schema::table('affiliates'")
        ->toContain("foreignId('business_unit_id')")
        ->toContain("foreignId('business_line_id')")
        ->toContain("constrained('business_units')")
        ->toContain("constrained('business_lines')");
});

it('define columnas de linea y unidad de negocio en affiliate_corporates', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_06_17_090547_add_business_line_and_business_unit_to_affiliate_corporates_table.php');

    expect($source)
        ->toContain("Schema::table('affiliate_corporates'")
        ->toContain("foreignId('business_unit_id')")
        ->toContain("foreignId('business_line_id')")
        ->toContain("constrained('business_units')")
        ->toContain("constrained('business_lines')");
});
