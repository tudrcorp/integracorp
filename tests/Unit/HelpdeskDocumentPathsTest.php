<?php

declare(strict_types=1);

use App\Models\HelpDesk;
use App\Support\HelpdeskDocumentPaths;

it('normaliza una ruta única en image', function (): void {
    $record = new HelpDesk;
    $record->image = 'helpdesks-documents/foto.png';

    expect(HelpdeskDocumentPaths::paths($record))->toBe(['helpdesks-documents/foto.png']);
});

it('normaliza JSON de rutas en image', function (): void {
    $record = new HelpDesk;
    $record->image = json_encode(['a/x.pdf', 'b/y.png']);

    expect(HelpdeskDocumentPaths::paths($record))->toBe(['a/x.pdf', 'b/y.png']);
});

it('devuelve vacío sin image', function (): void {
    $record = new HelpDesk;
    $record->image = null;

    expect(HelpdeskDocumentPaths::paths($record))->toBe([]);
});
