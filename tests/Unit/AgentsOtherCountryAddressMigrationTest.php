<?php

declare(strict_types=1);

it('agrega campos de direccion en otro pais a la tabla agents', function (): void {
    $migration = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_06_03_214106_add_other_country_address_fields_to_agents_table.php');

    expect($migration)
        ->toContain("Schema::table('agents'")
        ->toContain('country_other_country')
        ->toContain('state_other_country')
        ->toContain('city_other_country')
        ->toContain('postal_code_other_country')
        ->toContain('address_other_country');

    expect(file_get_contents(dirname(__DIR__, 2).'/app/Models/Agent.php'))
        ->toContain("'country_other_country'")
        ->toContain("'state_other_country'")
        ->toContain("'city_other_country'")
        ->toContain("'postal_code_other_country'")
        ->toContain("'address_other_country'");
});
