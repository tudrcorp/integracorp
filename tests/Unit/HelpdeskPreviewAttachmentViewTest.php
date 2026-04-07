<?php

declare(strict_types=1);

use App\Models\HelpDesk;
use Tests\TestCase;

uses(TestCase::class);

it('renderiza la vista previa de adjunto helpdesk', function (): void {
    $record = new HelpDesk;
    $record->image = 'helpdesks-documents/ejemplo.png';
    $record->created_by = 'Usuario prueba';

    $html = view('filament.business.helpdesks.preview-attachment', [
        'record' => $record,
        'url' => 'https://example.test/storage/helpdesks-documents/ejemplo.png',
        'extension' => 'png',
        'missing' => false,
    ])->render();

    expect($html)->toContain('fi-helpdesk-attachment-preview')
        ->and($html)->toContain('Vista previa del adjunto');
});
