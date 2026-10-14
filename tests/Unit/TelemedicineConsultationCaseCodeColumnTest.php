<?php

declare(strict_types=1);

it('amplía el código de caso de la consulta para los id desde 1000', function (): void {
    $migration = (string) file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_10_13_114000_widen_telemedicine_case_code_on_telemedicine_consultation_patients_table.php');
    $docs = (string) file_get_contents(dirname(__DIR__, 2).'/app/Support/Telemedicine/TelemedicineSchemaDocumentation.php');
    $generator = (string) file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/UtilsController.php');

    expect($migration)
        ->toContain('string(self::COLUMN, self::LENGTH)')
        ->toContain('private const LENGTH = 255;')
        ->toContain('nullable(false)')
        ->toContain('Schema::hasColumn')
        ->toContain('CHAR_LENGTH');

    expect($docs)->toContain("self::col('telemedicine_case_code', 'varchar(255)', false, null, 'Código del caso (redundante para consultas).')");

    expect($generator)->toContain("random_int(11111, 99999).'-0'.\$parteEntera");

    expect(strlen('11111-0999'))->toBe(10)
        ->and(strlen('73126-01005'))->toBe(11)
        ->and(strlen('11111-01000000'))->toBeLessThanOrEqual(255);
});
