<?php

declare(strict_types=1);

use App\Models\AffiliateCorporate;
use App\Models\AffiliationCorporate;
use App\Models\Coverage;
use App\Models\Plan;
use App\Services\AffiliationCorporateBusinessDocumentsService;

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

it('ejecuta lotes de tarjetas en conexion sync para no depender del worker', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Services/AffiliationCorporateBusinessDocumentsService.php');

    expect($source)->toContain("->onConnection('sync')")
        ->and($source)->toContain('normalizeTarjetaPayloads');
});
