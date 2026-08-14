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
            ->toContain('TEMPLATE_INDIVIDUAL_AFFILIATION_ALLIED')
            ->toContain('generateTarjetaAfiliacionBatch')
            ->toContain('planDisplayName')
            ->toContain('plan_tarjeta_etiqueta')
            ->toContain('-carnets.pdf')
            ->toContain('WhiteCompanyDocumentBrand')
            ->toContain('template_path')
            ->toContain('ViveplusDocumentWebhookDispatcher::dispatchForIndividual')
            ->toContain('writeIndividualTarjetas')
            ->toContain('resolveAffiliateCarnetDocuments');
    });

    it('resuelve un carnet por afiliado con la cédula exacta', function (): void {
        $affiliation = new Affiliation([
            'code' => 'TDEC-IND-000226',
            'nro_identificacion_ti' => '13991020',
        ]);
        $titular = new Affiliate(['nro_identificacion' => '13991020']);
        $titular->id = 10;
        $dependiente = new Affiliate(['nro_identificacion' => '22111000']);
        $dependiente->id = 11;
        $affiliation->setRelation('affiliates', collect([$titular, $dependiente]));

        $directory = public_path('storage/tarjeta-afiliacion');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $titularPath = $directory.'/TAR-TDEC-IND-000226-10.pdf';
        $dependientePath = $directory.'/TAR-TDEC-IND-000226-11.pdf';
        file_put_contents($titularPath, '%PDF-1.4 titular');
        file_put_contents($dependientePath, '%PDF-1.4 dependiente');

        try {
            expect(AffiliationBusinessDocumentsService::resolveAffiliateCarnetDocuments($affiliation))
                ->toBe([
                    ['path' => $titularPath, 'identification' => '13991020'],
                    ['path' => $dependientePath, 'identification' => '22111000'],
                ]);
        } finally {
            @unlink($titularPath);
            @unlink($dependientePath);
        }
    });
});
