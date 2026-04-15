<?php

declare(strict_types=1);

use App\Models\HelpDesk;
use Filament\Facades\Filament;
use Tests\TestCase;

uses(TestCase::class);

it('genera la URL de creación de helpdesk según el panel activo', function (string $panelId) {
    Filament::setCurrentPanel($panelId);

    $url = Filament::getResourceUrl(HelpDesk::class, 'create');

    expect($url)->toContain($panelId);
})->with(['administration', 'business', 'operations', 'marketing']);

it('genera la URL de edición de helpdesk según el panel activo', function (string $panelId) {
    Filament::setCurrentPanel($panelId);

    $ticket = HelpDesk::make([
        'description' => 'Ticket de prueba',
        'priority' => 'MEDIA',
    ]);
    $ticket->id = 42;
    $ticket->exists = true;

    $url = Filament::getResourceUrl(HelpDesk::class, 'edit', ['record' => $ticket]);

    expect($url)->toContain($panelId);
    expect($url)->toContain('42');
})->with(['administration', 'business', 'operations', 'marketing']);

it('CreateHelpdesk redirige al índice tras crear en todos los paneles', function (string $panel): void {
    $path = dirname(__DIR__, 2)."/app/Filament/{$panel}/Resources/Helpdesks/Pages/CreateHelpdesk.php";
    $src = file_get_contents($path);

    expect($src)->toContain('protected function getRedirectUrl(): string')
        ->and($src)->toContain("::getUrl('index')");
})->with(['Business', 'Administration', 'Marketing', 'Operations']);
