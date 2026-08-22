<?php

declare(strict_types=1);

use App\Models\AffiliateCorporate;
use App\Models\AffiliationCorporate;
use App\Models\Coverage;
use App\Models\Plan;
use App\Services\AffiliationCorporateBusinessDocumentsService;
use App\Support\AffiliateCard\AffiliateCardPageLayout;

it('incluye plan_id y plan de cada afiliado corporativo en el payload de tarjeta', function (): void {
    $affiliationCorporate = new AffiliationCorporate([
        'code' => 'TDEC-COR-00053',
        'effective_date' => '01/01/2026',
        'payment_frequency' => 'ANUAL',
    ]);

    $affiliateAp1k = new AffiliateCorporate([
        'first_name' => 'Juan',
        'last_name' => 'Perez',
        'nro_identificacion' => 'V-1',
        'plan_id' => 16,
        'payment_frequency' => 'ANUAL',
    ]);
    $affiliateAp1k->id = 1;
    $affiliateAp1k->setRelation('plan', new Plan(['id' => 16, 'description' => 'PLAN ESCOLAR AP 1K']));
    $affiliateAp1k->setRelation('coverage', new Coverage(['price' => 1000]));

    $affiliateAp3k = new AffiliateCorporate([
        'first_name' => 'Maria',
        'last_name' => 'Lopez',
        'nro_identificacion' => 'V-2',
        'plan_id' => 17,
        'payment_frequency' => 'ANUAL',
    ]);
    $affiliateAp3k->id = 2;
    $affiliateAp3k->setRelation('plan', new Plan(['id' => 17, 'description' => 'PLAN ESCOLAR AP 3K']));
    $affiliateAp3k->setRelation('coverage', new Coverage(['price' => 3000]));

    $chunks = AffiliationCorporateBusinessDocumentsService::toTarjetaPayloadChunk(
        $affiliationCorporate,
        collect([$affiliateAp1k, $affiliateAp3k]),
    );

    expect($chunks[0][0])
        ->toMatchArray([
            'plan_id' => 16,
            'plan' => 'PLAN ESCOLAR AP 1K',
            'cobertura' => '1000',
        ])
        ->and($chunks[0][1])
        ->toMatchArray([
            'plan_id' => 17,
            'plan' => 'PLAN ESCOLAR AP 3K',
            'cobertura' => '3000',
        ]);
});

it('divide afiliados corporativos en lotes de 5 para tarjetas', function (): void {
    $affiliationCorporate = new AffiliationCorporate([
        'code' => 'CORP-001',
        'effective_date' => '01/01/2026',
        'payment_frequency' => 'ANUAL',
    ]);
    $affiliationCorporate->setRelation('plan', new Plan(['description' => 'PLAN IDEAL']));
    $affiliationCorporate->setRelation('coverage', new Coverage(['price' => 10000]));

    $affiliates = collect(range(1, 12))->map(function (int $index): AffiliateCorporate {
        $affiliate = new AffiliateCorporate([
            'first_name' => 'Nombre'.$index,
            'last_name' => 'Apellido'.$index,
            'nro_identificacion' => 'V-'.$index,
        ]);
        $affiliate->id = $index;

        return $affiliate;
    });

    $chunks = AffiliationCorporateBusinessDocumentsService::toTarjetaPayloadChunk(
        $affiliationCorporate,
        $affiliates,
        5,
    );

    expect($chunks)->toHaveCount(3)
        ->and($chunks[0])->toHaveCount(5)
        ->and($chunks[1])->toHaveCount(5)
        ->and($chunks[2])->toHaveCount(2)
        ->and($chunks[0][0]['output_filename'])->toBe('TAR-CORP-001-1.pdf');
});

it('genera nombres de tarjeta por afiliado corporativo', function (): void {
    $affiliationCorporate = new AffiliationCorporate(['code' => 'CORP-010']);
    $affiliationCorporate->setRelation('corporateAffiliates', collect([
        tap(new AffiliateCorporate(['nro_identificacion' => 'V-1']), fn ($a) => $a->id = 3),
        tap(new AffiliateCorporate(['nro_identificacion' => 'V-2']), fn ($a) => $a->id = 8),
    ]));

    expect(AffiliationCorporateBusinessDocumentsService::tarjetaCandidateFilenames($affiliationCorporate))
        ->toBe([
            'TAR-CORP-010-3.pdf',
            'TAR-CORP-010-8.pdf',
        ]);
});

it('aplaniza el lote anidado devuelto por toTarjetaPayloadChunk sin tamano', function (): void {
    $nested = [
        [
            [
                'name' => 'Juan Perez',
                'ci' => 'V-1',
                'code' => 'CORP-099',
                'plan' => 'PLAN IDEAL',
                'frecuencia' => 'ANUAL',
                'cobertura' => '10000',
                'desde' => '01/01/2026',
                'hasta' => '01/01/2027',
                'output_filename' => 'TAR-CORP-099-1.pdf',
            ],
            [
                'name' => 'Maria Lopez',
                'ci' => 'V-2',
                'code' => 'CORP-099',
                'plan' => 'PLAN IDEAL',
                'frecuencia' => 'ANUAL',
                'cobertura' => '10000',
                'desde' => '01/01/2026',
                'hasta' => '01/01/2027',
                'output_filename' => 'TAR-CORP-099-2.pdf',
            ],
        ],
    ];

    $flat = AffiliationCorporateBusinessDocumentsService::normalizeTarjetaPayloads($nested);

    expect($flat)->toHaveCount(2)
        ->and($flat[0]['output_filename'])->toBe('TAR-CORP-099-1.pdf')
        ->and($flat[1]['output_filename'])->toBe('TAR-CORP-099-2.pdf');
});

it('despacha los lotes de carnets a la cola de documentos y no en el request', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Services/AffiliationCorporateBusinessDocumentsService.php');

    expect($source)->toContain('->onQueue(self::documentsQueue())')
        ->and($source)->not->toContain("->onConnection('sync')")
        ->and($source)->toContain('normalizeTarjetaPayloads')
        ->and($source)->toContain('queueViveplusDocuments')
        ->and($source)->toContain('ViveplusDocumentWebhookDispatcher::dispatchForCorporate')
        ->and($source)->toContain('resolveAffiliateCarnetDocuments');
});

it('cachea el estado antes de despachar el lote para no perder el resultado del worker', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Services/AffiliationCorporateBusinessDocumentsService.php');

    $cachePosition = strpos($source, 'Cache::put($activeTaskCacheKey, $taskId');
    $dispatchPosition = strpos($source, '->dispatch();');

    expect($cachePosition)->not->toBeFalse()
        ->and($dispatchPosition)->not->toBeFalse()
        ->and($cachePosition)->toBeLessThan($dispatchPosition);
});

it('define affiliationCode y usa affiliateCount al regenerar documentos corporativos', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Services/AffiliationCorporateBusinessDocumentsService.php');

    expect($source)
        ->toContain('$affiliationCode = (string) $record->code;')
        ->toContain('self::recommendedChunkSize($affiliateCount)')
        ->toContain('self::generateCorporateCertificate($record)')
        ->toContain('use ($taskId, $userId, $activeTaskCacheKey, $affiliationCode)')
        ->toContain('public static function generateTarjetasChunk(array $chunk): void')
        ->not->toContain('recommendedChunkSize($affiliatesCount)');
});

it('usa el layout de carnet individual y la marca de empresa aliada en las tarjetas corporativas', function (): void {
    $affiliationCorporate = new AffiliationCorporate([
        'code' => 'TDEC-COR-00099',
        'effective_date' => '01/01/2026',
        'payment_frequency' => 'ANUAL',
    ]);

    $affiliate = new AffiliateCorporate([
        'first_name' => 'Ana',
        'last_name' => 'Suarez',
        'nro_identificacion' => 'V-9',
        'plan_id' => 2,
    ]);
    $affiliate->id = 7;
    $affiliate->setRelation('plan', new Plan(['id' => 2, 'description' => 'PLAN IDEAL']));
    $affiliate->setRelation('coverage', new Coverage(['price' => 5000]));

    $chunks = AffiliationCorporateBusinessDocumentsService::toTarjetaPayloadChunk(
        $affiliationCorporate,
        collect([$affiliate]),
    );

    expect($chunks[0][0])
        ->toMatchArray([
            'card_layout' => AffiliateCardPageLayout::TEMPLATE_INDIVIDUAL_AFFILIATION,
            'template_key' => AffiliateCardPageLayout::TEMPLATE_INDIVIDUAL_AFFILIATION,
            'output_filename' => 'TAR-TDEC-COR-00099-7.pdf',
        ])
        ->and($chunks[0][0]['plan_tarjeta_etiqueta'])->toBeString();
});

it('nombra el PDF combinado de carnets igual que en afiliaciones individuales', function (): void {
    $affiliationCorporate = new AffiliationCorporate(['code' => 'TDEC-COR-00054']);

    expect(AffiliationCorporateBusinessDocumentsService::combinedCardsFilename($affiliationCorporate))
        ->toBe('TAR-TDEC-COR-00054-carnets.pdf');
});

it('el PDF combinado corporativo usa la misma columna de 4 carnets que individuales', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Services/AffiliationCorporateBusinessDocumentsService.php');
    $job = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/GenerateCorporateCombinedCardsJob.php');
    $layout = AffiliateCardPageLayout::class;

    expect($source)
        ->toContain('TarjetaAfiliacionController::generateTarjetaAfiliacionBatch')
        ->toContain('self::generateCombinedCards($record')
        ->and($job)->toContain('AffiliationCorporateBusinessDocumentsService::generateCombinedCards')
        ->and($layout::CARDS_PER_SHEET)->toBe(4)
        ->and($layout::SHEET_COLUMNS)->toBe(1)
        ->and($layout::SHEET_ROWS)->toBe(4)
        ->and($layout::individualAffiliationUnitDimensions(false)['width_mm'])->toBe(150.0);
});

it('genera dentro del request solo poblaciones pequenas', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Services/AffiliationCorporateBusinessDocumentsService.php');

    expect(AffiliationCorporateBusinessDocumentsService::INLINE_AFFILIATE_THRESHOLD)->toBe(10)
        ->and($source)->toContain('self::shouldRunInline($affiliateCount)')
        ->and($source)->toContain("config('queue.default') === 'sync'");
});

it('expone la vista previa sin listar las tarjetas una por una', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Services/AffiliationCorporateBusinessDocumentsService.php');

    expect($source)
        ->toContain('public static function previewDocumentsForAffiliation(AffiliationCorporate $record): array')
        ->toContain('public static function paginatedTarjetaDocuments(')
        ->toContain("'kind' => 'condicionado'")
        ->and($source)->not->toContain('private static function documentsForAffiliation');
});

it('reutiliza la tarea activa en vez de regenerar dos veces la misma afiliacion', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Services/AffiliationCorporateBusinessDocumentsService.php');

    expect($source)
        ->toContain('private static function activeTaskFor(string $affiliationCode): ?array')
        ->toContain("'reused' => true");
});

it('adjunta el PDF combinado al correo en vez de miles de carnets sueltos', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Services/AffiliationCorporateBusinessDocumentsService.php');

    expect($source)
        ->toContain('$combinedPath = self::combinedCardsAbsolutePath($record);')
        ->toContain('self::condicionadoAbsolutePathForAffiliation($record)');
});

it('recomienda tamano de lote segun cantidad de afiliados', function (): void {
    expect(AffiliationCorporateBusinessDocumentsService::recommendedChunkSize(3))->toBe(5)
        ->and(AffiliationCorporateBusinessDocumentsService::recommendedChunkSize(20))->toBe(5)
        ->and(AffiliationCorporateBusinessDocumentsService::recommendedChunkSize(21))->toBe(10)
        ->and(AffiliationCorporateBusinessDocumentsService::recommendedChunkSize(80))->toBe(10)
        ->and(AffiliationCorporateBusinessDocumentsService::recommendedChunkSize(81))->toBe(25)
        ->and(AffiliationCorporateBusinessDocumentsService::recommendedChunkSize(251))->toBe(50)
        ->and(AffiliationCorporateBusinessDocumentsService::recommendedChunkSize(2681))->toBe(100);
});
