<?php

declare(strict_types=1);

use App\Filament\Administration\Resources\Collections\Tables\CollectionsTable;
use App\Models\Collection;
use App\Support\CsvExportStream;
use App\Support\Exports\CollectionCsvExportService;
use Filament\Actions\BulkAction;

uses(Tests\TestCase::class);

/**
 * Solo lectura: cuotas en memoria o lecturas de la base, sin escribir.
 */
it('arma una fila por cuota con el estado y los días de la tabla', function (): void {
    $record = new Collection([
        'collection_invoice_number' => '09-001015',
        'include_date' => '30/09/2026',
        'affiliate_full_name' => 'NOIRALIH SANCHEZ',
        'affiliate_ci_rif' => '10336797',
        'affiliation_code' => 'TDEC-IND-000430',
        'type' => 'AFILIACION INDIVIDUAL',
        'total_amount' => 114.75,
        'next_payment_date' => '2020-01-01',
        'status' => 'POR PAGAR',
    ]);
    $record->setRelation('plan', null);
    $record->setRelation('agent', null);
    $record->setRelation('coverage', null);

    $row = CollectionCsvExportService::row($record);
    $headers = CollectionCsvExportService::headers();

    expect($row)->toHaveCount(count($headers))
        ->and(array_combine($headers, $row))->toMatchArray([
            'Nro. de aviso' => '09-001015',
            'Afiliado o titular' => 'NOIRALIH SANCHEZ',
            'Tipo' => 'INDIVIDUAL',
            'Monto (USD)' => '114.75',
            'Próximo pago' => '01/01/2020',
            'Estado' => 'VENCIDO',
        ]);
});

it('descarga directo un CSV con BOM, encabezados y solo las cuotas pedidas', function (): void {
    $ids = Collection::query()->latest('id')->limit(2)->pluck('id')->all();

    $response = app(CollectionCsvExportService::class)->streamCsv([...$ids, 0, 'x']);

    ob_start();
    $response->sendContent();
    $csv = (string) ob_get_clean();

    $lines = array_values(array_filter(explode("\n", substr($csv, strlen(CsvExportStream::UTF8_BOM)))));

    expect($response->headers->get('Content-Type'))->toContain('text/csv')
        ->and($response->headers->get('Content-Disposition'))->toContain('cuotas_cobranza_')
        ->and(str_starts_with($csv, CsvExportStream::UTF8_BOM))->toBeTrue()
        ->and(str_getcsv($lines[0]))->toBe(CollectionCsvExportService::headers())
        ->and($lines)->toHaveCount(count($ids) + 1);
});

it('la acción masiva descarga el CSV sin modal y queda auditada', function (): void {
    $action = CollectionsTable::exportBulkAction();
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Collections/Tables/CollectionsTable.php');

    expect($action)->toBeInstanceOf(BulkAction::class)
        ->and($action->getName())->toBe('exportCollections')
        ->and($action->getLabel())->toBe('Exportar CSV');

    expect($source)
        ->toContain('->streamCsv($records->modelKeys())')
        ->toContain("SecurityAudit::log('AUDIT_ADMIN_COLLECTIONS_EXPORT_REQUESTED'")
        ->not->toContain('ExportBulkAction')
        ->not->toContain('requiresConfirmation');
});
