<?php

declare(strict_types=1);

use App\Models\Helpdesk;
use Filament\Facades\Filament;
use Tests\TestCase;

uses(TestCase::class);

it('genera la URL de creación de helpdesk según el panel activo', function (string $panelId) {
    Filament::setCurrentPanel($panelId);

    $url = Filament::getResourceUrl(Helpdesk::class, 'create');

    expect($url)->toContain($panelId);
})->with(['administration', 'business', 'operations', 'marketing']);

it('genera la URL de edición de helpdesk según el panel activo', function (string $panelId) {
    Filament::setCurrentPanel($panelId);

    $ticket = Helpdesk::make([
        'description' => 'Ticket de prueba',
        'priority' => 'MEDIA',
    ]);
    $ticket->id = 42;
    $ticket->exists = true;

    $url = Filament::getResourceUrl(Helpdesk::class, 'edit', ['record' => $ticket]);

    expect($url)->toContain($panelId);
    expect($url)->toContain('42');
})->with(['administration', 'business', 'operations', 'marketing']);
