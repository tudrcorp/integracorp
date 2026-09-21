<?php

declare(strict_types=1);

use App\Support\Telemedicine\TelemedicineCaseDocumentsCatalog;
use Illuminate\Support\Collection;

uses(Tests\TestCase::class);

/**
 * Regenerar el informe corto dejaba un duplicado más en `uploaded_documents`
 * (usaba array_merge en vez del helper que reemplaza). El catálogo deduplica
 * por `uid` y conservaba la aparición más antigua, así que el médico veía la
 * fecha vieja y parecía que ese documento no se había regenerado.
 */
function catalogFinalizeEntries(array $entries): array
{
    $method = new ReflectionMethod(TelemedicineCaseDocumentsCatalog::class, 'finalizeEntries');
    $method->setAccessible(true);

    return $method->invoke(null, new Collection($entries));
}

it('al deduplicar conserva la copia más reciente del mismo documento', function (): void {
    $viejo = [
        'uid' => 'mismo-documento',
        'file_path' => 'telemedicina-doc/informe-corto.pdf',
        'document_name' => 'informe-corto.pdf',
        'sort_timestamp' => 1000,
        'uploaded_at_label' => '30/08/2026 22:52',
    ];
    $nuevo = [
        'uid' => 'mismo-documento',
        'file_path' => 'telemedicina-doc/informe-corto.pdf',
        'document_name' => 'informe-corto.pdf',
        'sort_timestamp' => 2000,
        'uploaded_at_label' => '02/09/2026 09:12',
    ];

    // El histórico llega con la entrada vieja primero, que es como está en la base.
    $result = catalogFinalizeEntries([$viejo, $nuevo]);

    expect($result)->toHaveCount(1)
        ->and($result[0]['uploaded_at_label'])->toBe('02/09/2026 09:12')
        ->and($result[0]['sort_timestamp'])->toBe(2000);
});

it('mantiene documentos distintos y los ordena del más nuevo al más viejo', function (): void {
    $result = catalogFinalizeEntries([
        ['uid' => 'corto', 'file_path' => 'a.pdf', 'sort_timestamp' => 3000],
        ['uid' => 'largo', 'file_path' => 'b.pdf', 'sort_timestamp' => 5000],
        ['uid' => 'labs', 'file_path' => 'c.pdf', 'sort_timestamp' => 1000],
    ]);

    expect($result)->toHaveCount(3)
        ->and(array_column($result, 'uid'))->toBe(['largo', 'corto', 'labs']);
});

it('descarta entradas sin ruta de archivo', function (): void {
    $result = catalogFinalizeEntries([
        ['uid' => 'a', 'file_path' => '', 'sort_timestamp' => 1],
        ['uid' => 'b', 'file_path' => 'ok.pdf', 'sort_timestamp' => 2],
    ]);

    expect($result)->toHaveCount(1)
        ->and($result[0]['uid'])->toBe('b');
});

it('el informe corto reemplaza su entrada en vez de acumular duplicados', function (): void {
    $corto = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/GeneratePdfInformeMedicoCorto.php');
    $largo = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/GeneratePdfInformeMedicoLargo.php');

    // Ambos informes deben persistir igual; la asimetría era el origen del fallo.
    expect($corto)
        ->toContain('TelemedicineConsultationUploadedDocuments::sync')
        ->not->toContain('array_merge($existingDocuments')
        ->and($largo)->toContain('TelemedicineConsultationUploadedDocuments::sync');
});
