<?php

declare(strict_types=1);

it('registra auditoría en modelo y logger dedicado', function (): void {
    $root = dirname(__DIR__, 2);
    $logger = file_get_contents($root.'/app/Support/Logging/GuardDutyShiftLogger.php');
    expect($logger)->toContain('GuardDutyShiftLogger')
        ->and($logger)->toContain('OPERACIONES: Rol de guardia');

    $model = file_get_contents($root.'/app/Models/OperationOnCallUser.php');
    expect($model)->toContain('GuardDutyShiftLogger::record')
        ->and($model)->toContain("'created'")
        ->and($model)->toContain("'updated'")
        ->and($model)->toContain("'deleted'");
});

it('el recurso Roles de Guardia queda inaccesible para cualquier usuario', function (): void {
    $root = dirname(__DIR__, 2);
    $resource = file_get_contents($root.'/app/Filament/Operations/Resources/OperationOnCallUsers/OperationOnCallUserResource.php');
    expect($resource)->toContain('public static function canAccess(): bool')
        ->and($resource)->toContain('return false')
        ->and($resource)->toContain('canDeleteAny');
});
