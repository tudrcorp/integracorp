<?php

declare(strict_types=1);

use App\Jobs\GenerateCorporateAffiliateTarjetasChunkJob;
use App\Jobs\GenerateCorporateCertificateJob;
use App\Jobs\GenerateCorporateCombinedCardsJob;
use App\Models\AffiliateCorporate;
use App\Models\AffiliationCorporate;
use App\Models\Plan;
use App\Services\AffiliationCorporateBusinessDocumentsService;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;

uses(Tests\TestCase::class);

function corporateWithAffiliates(string $code, int $count): AffiliationCorporate
{
    $record = new AffiliationCorporate([
        'code' => $code,
        'effective_date' => '01/01/2026',
        'payment_frequency' => 'ANUAL',
    ]);
    $record->setRelation('plan', new Plan(['id' => 2, 'description' => 'PLAN IDEAL']));

    $affiliates = collect(range(1, $count))->map(function (int $index): AffiliateCorporate {
        $affiliate = new AffiliateCorporate([
            'first_name' => 'Nombre'.$index,
            'last_name' => 'Apellido'.$index,
            'nro_identificacion' => 'V-'.$index,
        ]);
        $affiliate->id = $index;

        return $affiliate;
    });

    $record->setRelation('corporateAffiliates', $affiliates);

    return $record;
}

beforeEach(function (): void {
    config(['queue.default' => 'database']);
    Cache::flush();
});

it('encola el lote en la cola de documentos cuando la poblacion supera el umbral', function (): void {
    Bus::fake();

    $record = corporateWithAffiliates('TDEC-COR-TEST01', AffiliationCorporateBusinessDocumentsService::INLINE_AFFILIATE_THRESHOLD + 5);

    $result = AffiliationCorporateBusinessDocumentsService::regenerateCertificateAndTarjetas($record, null);

    expect($result['queued'])->toBeTrue()
        ->and($result['task_id'])->toBeString()
        ->and($result['affiliates_count'])->toBe(15);

    Bus::assertBatched(function (PendingBatch $batch): bool {
        return $batch->queue() === config('affiliate-card.documents_queue')
            && $batch->name === 'corporate-documents-TDEC-COR-TEST01';
    });
});

it('genera primero el certificado y el PDF combinado para que la vista previa no espere al lote completo', function (): void {
    Bus::fake();

    $record = corporateWithAffiliates('TDEC-COR-TEST02', 60);

    AffiliationCorporateBusinessDocumentsService::regenerateCertificateAndTarjetas($record, null);

    Bus::assertBatched(function (PendingBatch $batch): bool {
        $jobs = $batch->jobs->all();

        return $jobs[0] instanceof GenerateCorporateCertificateJob
            && $jobs[1] instanceof GenerateCorporateCombinedCardsJob
            && $jobs[2] instanceof GenerateCorporateAffiliateTarjetasChunkJob;
    });
});

it('deja el estado en cache antes de despachar para que el polling nunca quede sin proceso', function (): void {
    Bus::fake();

    $record = corporateWithAffiliates('TDEC-COR-TEST03', 40);

    $result = AffiliationCorporateBusinessDocumentsService::regenerateCertificateAndTarjetas($record, null);
    $status = AffiliationCorporateBusinessDocumentsService::status($result['task_id']);

    expect($status['status'])->toBe('processing')
        ->and($status['affiliation_code'])->toBe('TDEC-COR-TEST03')
        ->and($status['total_jobs'])->toBe(6)
        ->and($status['preview_ready'])->toBeFalse();
});

it('no lanza una segunda generacion mientras hay una tarea viva para la misma afiliacion', function (): void {
    Bus::fake();

    $record = corporateWithAffiliates('TDEC-COR-TEST04', 30);

    $first = AffiliationCorporateBusinessDocumentsService::regenerateCertificateAndTarjetas($record, null);
    $second = AffiliationCorporateBusinessDocumentsService::regenerateCertificateAndTarjetas($record, null);

    expect($second['task_id'])->toBe($first['task_id'])
        ->and($second['reused'])->toBeTrue();

    Bus::assertBatchCount(1);
});

it('genera dentro del request cuando la poblacion es pequena', function (): void {
    Bus::fake();

    $record = corporateWithAffiliates('TDEC-COR-TEST05', 2);

    /** Sin plantillas ni disco de test la generación falla, pero nunca debe encolar. */
    try {
        AffiliationCorporateBusinessDocumentsService::regenerateCertificateAndTarjetas($record, null);
    } catch (Throwable) {
        // El objetivo del test es la ruta tomada, no el PDF resultante.
    }

    Bus::assertNothingBatched();
});

it('avisa por la campana del panel al terminar o fallar el lote', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Services/AffiliationCorporateBusinessDocumentsService.php');

    expect($source)
        ->toContain('private static function notifyUser(?int $userId, string $title, string $body, string $status): void')
        ->toContain('->sendToDatabase($user)')
        ->toContain("'Documentos corporativos listos'")
        ->toContain("'Falló la generación de documentos'");
});
