<?php

declare(strict_types=1);

use App\Services\ProspectAgentCsvImporter;

uses(Tests\TestCase::class);

it('lanza excepción si el encabezado del csv no coincide con el esperado', function (): void {
    $path = sys_get_temp_dir().'/prospect_agent_bad_header_'.uniqid('', true).'.csv';
    file_put_contents($path, "wrong,phone_1\nX,1\n");

    try {
        expect(fn () => (new ProspectAgentCsvImporter)->importFromPath($path))
            ->toThrow(\InvalidArgumentException::class);
    } finally {
        if (is_file($path)) {
            unlink($path);
        }
    }
});
