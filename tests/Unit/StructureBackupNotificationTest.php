<?php

declare(strict_types=1);

use App\Enums\SystemNotificationKey;

uses(Tests\TestCase::class);

it('las exportaciones de estructura usan destinatarios del centro de notificaciones', function (): void {
    $individual = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/ExportIndividualAffiliations.php');
    $corporate = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/ExportCorporateAffiliations.php');
    $entity = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/ExportScheduledEntity.php');
    $console = file_get_contents(dirname(__DIR__, 2).'/routes/console.php');

    expect($individual)
        ->toContain('SystemNotificationKey::StructureBackup')
        ->toContain('Respaldo de Estructura');

    expect($corporate)
        ->toContain('SystemNotificationKey::StructureBackup');

    expect($entity)
        ->toContain('SystemNotificationKey::StructureBackup');

    expect($console)
        ->toContain('structure_backup')
        ->toContain('Respaldo de Estructura');
});

it('expone la etiqueta y telefonos por defecto de respaldo de estructura', function (): void {
    expect(SystemNotificationKey::StructureBackup->label())
        ->toBe('Respaldo de Estructura')
        ->and(SystemNotificationKey::StructureBackup->defaultPhones())
        ->toBe(['04127018390', '04143027250']);
});
