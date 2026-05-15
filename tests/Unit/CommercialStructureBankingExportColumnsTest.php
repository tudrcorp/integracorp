<?php

declare(strict_types=1);

use App\Models\Agency;
use App\Models\Agent;
use App\Support\CommercialStructureBankingExportColumns;

it('alinea cabeceras y valores bancarios para modelos de agencia y agente', function (): void {
    $headers = CommercialStructureBankingExportColumns::csvHeaders();
    $agencyValues = CommercialStructureBankingExportColumns::valuesFromModel(new Agency);
    $agentValues = CommercialStructureBankingExportColumns::valuesFromModel(new Agent);

    expect(count($headers))->toBe(20)
        ->and(count($agencyValues))->toBe(20)
        ->and(count($agentValues))->toBe(20)
        ->and($headers[0])->toBe('Nat. nombre beneficiario');
});
