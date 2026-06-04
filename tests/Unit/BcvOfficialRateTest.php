<?php

declare(strict_types=1);

use App\Support\BcvOfficialRate;
use Illuminate\Support\Facades\Http;

uses(Tests\TestCase::class);

it('obtiene tasa BCV oficial desde la API con una sola peticion por request', function (): void {
    Http::fake([
        've.dolarapi.com/v1/dolares/oficial' => Http::response([
            'promedio' => 482.7586,
        ], 200),
    ]);

    expect(BcvOfficialRate::resolve())->toBe(482.76)
        ->and(BcvOfficialRate::resolve())->toBe(482.76);

    Http::assertSentCount(1);
});

it('usa tasa BCV oficial en formulario de pago de afiliaciones administration', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Affiliations/Tables/AffiliationsTable.php');

    expect($source)
        ->toContain('BcvOfficialRate::resolve()')
        ->toContain('applyOfficialBcvRate');
});
