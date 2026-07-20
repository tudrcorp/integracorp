<?php

declare(strict_types=1);

it('migra observations de coordinación a text para acumular cancelaciones', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_07_15_101031_change_observations_to_text_on_operation_coordination_services_table.php');

    expect($contents)
        ->toContain("->text('observations')")
        ->toContain("->string('observations')")
        ->toContain('->change()')
        ->toContain('operation_coordination_services');
});
