<?php

declare(strict_types=1);

use App\Models\Affiliation;
use Illuminate\Support\Facades\Route;

uses(Tests\TestCase::class);

it('registra rutas de documentos de afiliación en business', function (): void {
    expect(Route::has('business.affiliation-documents.regenerate-async'))->toBeTrue()
        ->and(Route::has('business.affiliation-documents.send-email'))->toBeTrue();
});

it('renderiza la vista modal de documentos de afiliación', function (): void {
    $affiliation = new Affiliation([
        'code' => 'TEST-1',
    ]);
    $affiliation->id = 1;

    $html = view('filament.business.affiliations.affiliation-documents-preview-modal', [
        'affiliation' => $affiliation,
    ])->render();

    expect($html)
        ->toContain('affiliationDocumentsPanel')
        ->toContain('regenerate()')
        ->toContain('regenerate-async');
});
