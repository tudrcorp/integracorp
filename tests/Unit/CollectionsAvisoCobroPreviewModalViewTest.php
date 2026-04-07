<?php

declare(strict_types=1);

use App\Models\Collection;

uses(Tests\TestCase::class);

it('renderiza la vista modal de vista previa de aviso de cobro', function (): void {
    $collection = new Collection([
        'collection_invoice_number' => 'TEST-1',
    ]);
    $collection->id = 1;

    $html = view('filament.administration.collections.aviso-cobro-preview-modal', [
        'collection' => $collection,
    ])->render();

    expect($html)
        ->toContain('avisoCobroPanel')
        ->toContain('regenerate()')
        ->toContain('regenerate-async');
});
