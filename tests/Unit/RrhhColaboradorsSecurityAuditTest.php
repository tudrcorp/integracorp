<?php

declare(strict_types=1);

it('registra trazas de seguridad al crear y actualizar colaboradores', function (): void {
    $createPagePath = dirname(__DIR__, 2).'/app/Filament/Administration/Resources/RrhhColaboradors/Pages/CreateRrhhColaborador.php';
    $editPagePath = dirname(__DIR__, 2).'/app/Filament/Administration/Resources/RrhhColaboradors/Pages/EditRrhhColaborador.php';

    $createPageContents = file_get_contents($createPagePath);
    $editPageContents = file_get_contents($editPagePath);

    expect($createPageContents)
        ->toContain('SecurityAudit::log')
        ->toContain('AUDIT_ADMIN_RRHH_COLABORADOR_CREATED')
        ->toContain('AUDIT_ADMIN_RRHH_COLABORADOR_CREATE_FAILED');

    expect($editPageContents)
        ->toContain('SecurityAudit::log')
        ->toContain('AUDIT_ADMIN_RRHH_COLABORADOR_UPDATED')
        ->toContain('AUDIT_ADMIN_RRHH_COLABORADOR_UPDATE_FAILED')
        ->toContain('changed_fields');
});
