<?php

declare(strict_types=1);

use App\Support\IndividualQuotes\IndividualQuotePdf;

uses(Tests\TestCase::class);

it('expone accion de vista previa en la tabla de cotizaciones individuales business', function (): void {
    $table = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/IndividualQuotes/Tables/IndividualQuotesTable.php');
    $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/business/individual-quotes/pdf-preview.blade.php');

    expect($table)->toContain("Action::make('preview')")
        ->toContain("->label('Vista Previa')")
        ->toContain('IndividualQuotePdf::previewUrl')
        ->toContain('filament.business.individual-quotes.pdf-preview')
        ->toContain('->modalSubmitAction(false)')
        ->toContain('IndividualQuotePdf::exists($record)');

    expect($view)->toContain('iframe')
        ->toContain('Abrir en pestaña')
        ->toContain('Descargar PDF');
});

it('resuelve la ruta publica del pdf de cotizacion individual cuando existe', function (): void {
    $code = 'IQ-TEST-PREVIEW';
    $directory = public_path('storage/quotes');
    $path = $directory.'/'.$code.'.pdf';

    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    file_put_contents($path, '%PDF-1.4 test');

    try {
        expect(IndividualQuotePdf::existsForCode($code))->toBeTrue()
            ->and(IndividualQuotePdf::previewUrlForCode($code))
            ->toContain('/storage/quotes/'.$code.'.pdf');
    } finally {
        if (file_exists($path)) {
            unlink($path);
        }
    }
});

it('indica cuando el pdf de cotizacion individual no existe', function (): void {
    expect(IndividualQuotePdf::existsForCode('IQ-MISSING-PDF-'.uniqid()))->toBeFalse()
        ->and(IndividualQuotePdf::previewUrlForCode('IQ-MISSING-PDF-'.uniqid()))->toBeNull();
});
