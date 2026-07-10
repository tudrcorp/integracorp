<?php

declare(strict_types=1);

use App\Models\Affiliate;
use App\Models\Affiliation;
use App\Services\AffiliationBusinessDocumentsService;

uses(Tests\TestCase::class);

describe('AffiliationBusinessDocumentsService', function () {
    it('maps plan id to condicionado basename', function () {
        expect(AffiliationBusinessDocumentsService::condicionadoBasenameForPlanId(1))->toBe('CondicionesINICIAL.pdf');
        expect(AffiliationBusinessDocumentsService::condicionadoBasenameForPlanId(2))->toBe('CondicionesIDEAL.pdf');
        expect(AffiliationBusinessDocumentsService::condicionadoBasenameForPlanId(3))->toBe('CondicionesESPECIAL.pdf');
        expect(AffiliationBusinessDocumentsService::condicionadoBasenameForPlanId(99))->toBeNull();
        expect(AffiliationBusinessDocumentsService::condicionadoBasenameForPlanId(null))->toBeNull();
    });

    it('maps plan id to condicionado public url', function () {
        expect(AffiliationBusinessDocumentsService::condicionadoPublicUrlForPlanId(1))
            ->toEndWith('/storage/condicionados/CondicionesINICIAL.pdf');
    });

    it('does not need legacy tarjeta when titular CI matches an affiliate', function () {
        $affiliation = new Affiliation([
            'nro_identificacion_ti' => 'V-12345678',
        ]);
        $affiliation->setRelation('affiliates', collect([
            new Affiliate(['nro_identificacion' => 'v-12345678']),
        ]));

        expect(AffiliationBusinessDocumentsService::shouldGenerateLegacyTitularTarjeta($affiliation))->toBeFalse();
    });

    it('needs legacy tarjeta when affiliates list is empty', function () {
        $affiliation = new Affiliation(['nro_identificacion_ti' => 'V-1']);
        $affiliation->setRelation('affiliates', collect());

        expect(AffiliationBusinessDocumentsService::shouldGenerateLegacyTitularTarjeta($affiliation))->toBeTrue();
    });

    it('needs legacy tarjeta when titular is not among affiliates', function () {
        $affiliation = new Affiliation(['nro_identificacion_ti' => 'V-1']);
        $affiliation->setRelation('affiliates', collect([
            new Affiliate(['nro_identificacion' => 'V-2']),
        ]));

        expect(AffiliationBusinessDocumentsService::shouldGenerateLegacyTitularTarjeta($affiliation))->toBeTrue();
    });

    it('marca layout individual en payload de tarjeta cuando se solicita', function (): void {
        $service = file_get_contents(dirname(__DIR__, 2).'/app/Services/AffiliationBusinessDocumentsService.php');

        expect($service)
            ->toContain('useIndividualAffiliateCardLayout')
            ->toContain("\$payload['card_layout'] = 'individual-affiliation'")
            ->toContain("\$payload['template_key'] = 'individual-affiliation'")
            ->toContain('generateTarjetaAfiliacionBatch')
            ->toContain('-carnets.pdf');
    });
});
