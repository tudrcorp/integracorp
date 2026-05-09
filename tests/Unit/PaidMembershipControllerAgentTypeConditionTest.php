<?php

declare(strict_types=1);

it('compara tipo de agente usando agent_type_id al calcular comision de agente', function (): void {
    $controllerPath = dirname(__DIR__, 2).'/app/Http/Controllers/PaidMembershipController.php';
    $controllerContent = file_get_contents($controllerPath);

    expect($controllerContent)
        ->toContain('$agent_type->agent_type_id == 2')
        ->not->toContain('$agent_type == 2');
});
