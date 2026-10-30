<?php

declare(strict_types=1);

use App\Http\Controllers\AffiliationController;
use App\Models\Affiliate;
use App\Models\AffiliateCorporate;
use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Services\AffiliationBusinessDocumentsService;
use App\Services\AffiliationCorporateBusinessDocumentsService;
use App\Support\Affiliations\Certificates\CertificatePrintableAffiliates;
use App\Support\Affiliations\Certificates\IndividualCertificateDocument;
use Illuminate\Database\Eloquent\Collection;

/**
 * @param  array<string, string|null>  $people  Cédula => estatus.
 */
function cpaIndividual(string $status, array $people, string $titularCi = 'V-1'): Affiliation
{
    $id = 0;
    $affiliates = [];

    foreach ($people as $ci => $affiliateStatus) {
        $affiliate = new Affiliate(['nro_identificacion' => $ci, 'full_name' => 'Persona '.$ci, 'status' => $affiliateStatus]);
        $affiliate->id = ++$id;
        $affiliates[] = $affiliate;
    }

    $record = new Affiliation(['code' => 'TDEC-IND-TEST', 'status' => $status, 'nro_identificacion_ti' => $titularCi]);
    $record->setRelation('affiliates', new Collection($affiliates));

    return $record;
}

/**
 * @param  list<string|null>  $statuses
 */
function cpaCorporate(string $status, array $statuses): AffiliationCorporate
{
    $affiliates = [];

    foreach ($statuses as $index => $affiliateStatus) {
        $affiliate = new AffiliateCorporate(['first_name' => 'P'.$index, 'status' => $affiliateStatus]);
        $affiliate->id = $index + 1;
        $affiliates[] = $affiliate;
    }

    $record = new AffiliationCorporate(['code' => 'TDEC-COR-TEST', 'status' => $status]);
    $record->setRelation('corporateAffiliates', new Collection($affiliates));

    return $record;
}

it('solo imprime afiliados activos o pre-aprobados, sin importar espacios o mayúsculas', function (mixed $status, bool $printable): void {
    expect(CertificatePrintableAffiliates::isPrintableStatus($status))->toBe($printable);
})->with([
    'activo' => ['ACTIVO', true],
    'pre-aprobada' => ['PRE-APROBADA', true],
    'minúsculas y espacios' => [' activo ', true],
    'excluido' => ['EXCLUIDO', false],
    'inactivo' => ['INACTIVO', false],
    'vacío' => ['', false],
    'nulo' => [null, false],
]);

it('saca a excluidos e inactivos de una afiliación individual', function (string $status): void {
    $record = cpaIndividual($status, ['V-1' => 'ACTIVO', 'V-2' => 'EXCLUIDO', 'V-3' => 'INACTIVO', 'V-4' => 'PRE-APROBADA']);

    expect(AffiliationBusinessDocumentsService::printableAffiliates($record)->pluck('nro_identificacion')->all())->toBe(['V-1', 'V-4']);
})->with(['ACTIVA', 'PRE-APROBADA']);

it('saca a excluidos e inactivos de una afiliación corporativa', function (): void {
    $record = cpaCorporate('ACTIVA', ['ACTIVO', 'EXCLUIDO', 'INACTIVO', 'PRE-APROBADA']);

    expect(AffiliationCorporateBusinessDocumentsService::printableAffiliates($record)->pluck('id')->all())->toBe([1, 4])
        ->and(AffiliationCorporateBusinessDocumentsService::tarjetaCandidateFilenames($record))->toBe(['TAR-TDEC-COR-TEST-1.pdf', 'TAR-TDEC-COR-TEST-4.pdf']);
});

it('la afiliación que ya no es certificable conserva su lista completa', function (string $status): void {
    expect(AffiliationBusinessDocumentsService::printableAffiliates(cpaIndividual($status, ['V-1' => 'EXCLUIDO', 'V-2' => 'EXCLUIDO'])))->toHaveCount(2)
        ->and(AffiliationCorporateBusinessDocumentsService::printableAffiliates(cpaCorporate($status, ['EXCLUIDO', 'INACTIVO'])))->toHaveCount(2);
})->with(['EXCLUIDO', 'ANULADA']);

it('una afiliación activa con todos excluidos no imprime a nadie', function (): void {
    expect(AffiliationBusinessDocumentsService::printableAffiliates(cpaIndividual('ACTIVA', ['V-1' => 'EXCLUIDO'])))->toHaveCount(0)
        ->and(AffiliationCorporateBusinessDocumentsService::printableAffiliates(cpaCorporate('ACTIVA', ['EXCLUIDO'])))->toHaveCount(0);
});

it('el arreglo del alta sin estatus sigue completo y el que trae estatus se filtra', function (): void {
    expect(CertificatePrintableAffiliates::filter([['full_name' => 'Nueva'], ['full_name' => 'Otra']], 'PRE-APROBADA'))->toHaveCount(2)
        ->and(CertificatePrintableAffiliates::filter([['full_name' => 'A', 'status' => 'ACTIVO'], ['full_name' => 'B', 'status' => 'EXCLUIDO']], 'ACTIVA'))
        ->toBe([['full_name' => 'A', 'status' => 'ACTIVO']]);
});

it('un titular excluido no recibe la tarjeta de respaldo ni el carnet al pagar', function (): void {
    $record = cpaIndividual('ACTIVA', ['V-1' => 'EXCLUIDO', 'V-2' => 'ACTIVO']);

    expect(AffiliationBusinessDocumentsService::shouldGenerateLegacyTitularTarjeta($record))->toBeFalse()
        ->and(AffiliationBusinessDocumentsService::titularReceivesCarnet($record))->toBeFalse()
        ->and(AffiliationBusinessDocumentsService::titularTarjetaCandidateFilenames($record)[0])->toBe('TAR-TDEC-IND-TEST-2.pdf');
});

it('el titular activo o sin fila en afiliados sí recibe carnet', function (): void {
    expect(AffiliationBusinessDocumentsService::titularReceivesCarnet(cpaIndividual('ACTIVA', ['V-1' => 'ACTIVO'])))->toBeTrue()
        ->and(AffiliationBusinessDocumentsService::titularReceivesCarnet(cpaIndividual('ACTIVA', ['V-2' => 'ACTIVO'])))->toBeTrue();
});

it('el Generador y el flujo antiguo usan la misma regla', function (): void {
    expect(IndividualCertificateDocument::PRINTABLE_STATUSES)->toBe(CertificatePrintableAffiliates::PRINTABLE_STATUSES)
        ->and((new ReflectionMethod(AffiliationController::class, 'generateCertificateIndividual'))->getFileName())->toBeFile()
        ->and(file_get_contents((new ReflectionClass(AffiliationController::class))->getFileName()))->toContain('CertificatePrintableAffiliates::filter($afiliates, $record->status)');
});

it('ningún bucle de documentos del flujo antiguo recorre la lista sin filtrar', function (string $class, string $relation, string $allowed): void {
    $source = (string) file_get_contents((new ReflectionClass($class))->getFileName());

    expect($source)->not->toContain('foreach ($record->'.$relation.' as')
        ->and(substr_count($source, '$record->'.$relation.'->count()'))->toBe(0)
        ->and($source)->toContain($allowed);
})->with([
    'individual' => [AffiliationBusinessDocumentsService::class, 'affiliates', 'CertificatePrintableAffiliates::filter($record->affiliates, $record->status)'],
    'corporativa' => [AffiliationCorporateBusinessDocumentsService::class, 'corporateAffiliates', 'CertificatePrintableAffiliates::filter($record->corporateAffiliates, $record->status)'],
]);
