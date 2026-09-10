<?php

declare(strict_types=1);

it('OperationServiceOrdersTable abre vista previa PDF al clic en orden y cotización', function (): void {
    $table = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationServiceOrders/Tables/OperationServiceOrdersTable.php');
    $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/operations/operation-service-orders/pdf-preview.blade.php');

    expect($table)
        ->toContain("self::documentCodeColumn('order_number')")
        ->and($table)->toContain("self::documentCodeColumn('approvedOperationQuote.id')")
        ->and($table)->toContain('->action(self::previewOrderPdfAction())')
        ->and($table)->toContain('->action(self::previewQuotePdfAction())')
        ->and($table)->toContain("Action::make('preview_order_pdf')")
        ->and($table)->toContain("Action::make('preview_quote_pdf')")
        ->and($table)->toContain('->modalWidth(Width::SevenExtraLarge)')
        ->and($table)->toContain('operations.operation-service-orders.pdf.preview')
        ->and($table)->toContain('quotePdfPublicUrl')
        ->and($table)->toContain('filament.operations.operation-service-orders.pdf-preview')
        ->and($table)->toContain('private static function documentCodeColumn(string $name): TextColumn')
        ->and($table)->toContain('->badge()')
        ->and($table)->toContain("->color('warning')");

    expect($view)
        ->toContain('<iframe')
        ->and($view)->toContain('documentLabel')
        ->and($view)->toContain('Documento no disponible');
});
