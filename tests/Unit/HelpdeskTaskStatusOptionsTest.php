<?php

declare(strict_types=1);

use App\Models\HelpDesk;
use App\Support\HelpdeskTaskStatusOptions;

it('el creador del ticket solo ve terminado y cancelado en el modal', function (): void {
    $record = new HelpDesk;
    $record->created_by = 'Ana';
    $record->status = 'EN PROCESO';

    $options = HelpdeskTaskStatusOptions::forSelect($record, 'Ana', isAssignee: false);

    expect($options)->toHaveCount(2)
        ->toHaveKeys(['TERMINADO', 'CANCELADO'])
        ->not->toHaveKey('EN PROCESO');
});

it('el asignado ve estados operativos sin terminado ni cancelado', function (): void {
    $record = new HelpDesk;
    $record->created_by = 'Ana';
    $record->status = 'EN PROCESO';

    $options = HelpdeskTaskStatusOptions::forSelect($record, 'Pedro', isAssignee: true);

    expect($options)->toHaveCount(7)
        ->toHaveKey('EN DESARROLLO')
        ->not->toHaveKey('TERMINADO')
        ->not->toHaveKey('CANCELADO');
});

it('si es creador y asignado ve todos los estados', function (): void {
    $record = new HelpDesk;
    $record->created_by = 'Ana';
    $record->status = 'EN PROCESO';

    expect(HelpdeskTaskStatusOptions::forSelect($record, 'Ana', isAssignee: true))->toHaveCount(9);
});

it('sanitize impide al asignado guardar terminado', function (): void {
    $record = new HelpDesk;
    $record->created_by = 'Ana';
    $record->status = 'EN PROCESO';

    $sanitized = HelpdeskTaskStatusOptions::sanitizeStatusForSave($record, 'TERMINADO', 'Pedro', isAssignee: true);

    expect($sanitized)->toBe('EN PROCESO');
});

it('sanitize permite al asignado avanzar a desarrollo', function (): void {
    $record = new HelpDesk;
    $record->created_by = 'Ana';
    $record->status = 'EN PROCESO';

    $sanitized = HelpdeskTaskStatusOptions::sanitizeStatusForSave($record, 'EN DESARROLLO', 'Pedro', isAssignee: true);

    expect($sanitized)->toBe('EN DESARROLLO');
});

it('sanitize permite al creador cerrar el ticket', function (): void {
    $record = new HelpDesk;
    $record->created_by = 'Ana';
    $record->status = 'EN PROCESO';

    $sanitized = HelpdeskTaskStatusOptions::sanitizeStatusForSave($record, 'TERMINADO', 'Ana', isAssignee: false);

    expect($sanitized)->toBe('TERMINADO');
});

it('sanitize impide al creador usar estados operativos', function (): void {
    $record = new HelpDesk;
    $record->created_by = 'Ana';
    $record->status = 'EN PROCESO';

    $sanitized = HelpdeskTaskStatusOptions::sanitizeStatusForSave($record, 'EN DESARROLLO', 'Ana', isAssignee: false);

    expect($sanitized)->toBe('EN PROCESO');
});
