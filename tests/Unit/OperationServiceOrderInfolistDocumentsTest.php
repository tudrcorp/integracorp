<?php

declare(strict_types=1);

it('OperationServiceOrderInfolist muestra document_types desde la fila del repeatable', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationServiceOrders/Schemas/OperationServiceOrderInfolist.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("RepeatableEntry::make('uploaded_documents')")
        ->toContain("TextEntry::make('document_types')")
        ->toContain('->badge()')
        ->toContain('Sin tipo asociado');
});
