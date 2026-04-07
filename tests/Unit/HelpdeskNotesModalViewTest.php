<?php

declare(strict_types=1);

use App\Models\HelpDesk;
use Tests\TestCase;

uses(TestCase::class);

it('renderiza el modal de notas helpdesk', function (): void {
    $record = new HelpDesk;
    $record->id = 42;
    $record->created_by = 'Ana';
    $record->updated_by = 'Equipo soporte';

    $html = view('filament.business.helpdesks.notes-modal', [
        'record' => $record,
        'observation' => "Línea 1\nLínea 2",
        'updatedAtFormatted' => '27/03/2026 14:30',
        'updatedRelative' => 'hace 2 horas',
        'daysElapsed' => 0,
        'updatedBy' => 'Equipo soporte',
    ])->render();

    expect($html)->toContain('fi-helpdesk-notes-modal')
        ->and($html)->toContain('Última actualización')
        ->and($html)->toContain('Línea 1')
        ->and($html)->toContain('Equipo soporte');
});
