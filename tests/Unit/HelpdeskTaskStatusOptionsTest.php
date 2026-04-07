<?php

declare(strict_types=1);

use App\Models\HelpDesk;
use App\Support\HelpdeskTaskStatusOptions;

it('en alta no ofrece TERMINADO ni CANCELADO', function () {
    $options = HelpdeskTaskStatusOptions::forSelect(null, 'María');

    expect($options)->toHaveKeys(['PENDIENTE POR INICIAR', 'EN PROCESO'])
        ->and($options)->not->toHaveKey('TERMINADO')
        ->and($options)->not->toHaveKey('CANCELADO');
});

it('en edición el creador ve los cuatro estados', function () {
    $record = new HelpDesk;
    $record->created_by = 'Ana';
    $record->status = 'EN PROCESO';

    $options = HelpdeskTaskStatusOptions::forSelect($record, 'Ana');

    expect($options)->toHaveCount(4)->toHaveKeys(['TERMINADO', 'CANCELADO']);
});

it('en edición quien no creó la tarea no ve TERMINADO ni CANCELADO salvo que el estado actual sea terminal', function () {
    $open = new HelpDesk;
    $open->created_by = 'Ana';
    $open->status = 'EN PROCESO';

    expect(HelpdeskTaskStatusOptions::forSelect($open, 'Pedro'))->toHaveCount(2);

    $done = new HelpDesk;
    $done->created_by = 'Ana';
    $done->status = 'TERMINADO';

    $opts = HelpdeskTaskStatusOptions::forSelect($done, 'Pedro');
    expect($opts)->toHaveKey('TERMINADO')->not->toHaveKey('CANCELADO');
});

it('sanitizeStatusForSave impide estado no permitido para quien no es el creador', function () {
    $record = new HelpDesk;
    $record->created_by = 'Ana';
    $record->status = 'EN PROCESO';

    $sanitized = HelpdeskTaskStatusOptions::sanitizeStatusForSave($record, 'TERMINADO', 'Pedro');

    expect($sanitized)->toBe('EN PROCESO');
});

it('sanitizeStatusForSave permite al creador cerrar el ticket', function () {
    $record = new HelpDesk;
    $record->created_by = 'Ana';
    $record->status = 'EN PROCESO';

    $sanitized = HelpdeskTaskStatusOptions::sanitizeStatusForSave($record, 'TERMINADO', 'Ana');

    expect($sanitized)->toBe('TERMINADO');
});
