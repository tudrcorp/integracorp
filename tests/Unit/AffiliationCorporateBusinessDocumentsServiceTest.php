<?php

declare(strict_types=1);

use App\Models\AffiliateCorporate;
use App\Models\AffiliationCorporate;
use App\Models\Coverage;
use App\Models\Plan;
use App\Services\AffiliationCorporateBusinessDocumentsService;

describe('AffiliationCorporateBusinessDocumentsService', function () {
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
});
