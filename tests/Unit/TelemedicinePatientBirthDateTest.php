<?php

declare(strict_types=1);

use App\Support\Telemedicine\TelemedicinePatientBirthDate;
use Carbon\Carbon;

it('entiende fecha de nacimiento en d/m/Y sin romper', function (): void {
    $parsed = TelemedicinePatientBirthDate::parse('30/08/2026');

    expect($parsed)->not->toBeNull()
        ->and($parsed?->toDateString())->toBe('2026-08-30');
});

it('entiende Y-m-d y calcula la edad', function (): void {
    $parsed = TelemedicinePatientBirthDate::parse('1990-01-15');

    expect($parsed)->not->toBeNull()
        ->and(TelemedicinePatientBirthDate::age('1990-01-15'))->toBe($parsed?->age);
});

it('devuelve nulo si la fecha está vacía o es inválida', function (): void {
    expect(TelemedicinePatientBirthDate::parse(null))->toBeNull()
        ->and(TelemedicinePatientBirthDate::parse(''))->toBeNull()
        ->and(TelemedicinePatientBirthDate::parse('no-es-fecha'))->toBeNull()
        ->and(TelemedicinePatientBirthDate::age(null))->toBeNull();
});

it('acepta instancias de Carbon', function (): void {
    $source = Carbon::create(1985, 6, 12);

    expect(TelemedicinePatientBirthDate::parse($source)?->toDateString())->toBe('1985-06-12');
});

it('el formulario de Telemedicina usa el campo birth_date y el parser seguro', function (): void {
    $form = file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicinePatients/Schemas/TelemedicinePatientForm.php'
    );

    expect($form)
        ->toContain("DatePicker::make('birth_date')")
        ->toContain('TelemedicinePatientBirthDate::age($state)')
        ->not->toContain("DatePicker::make('date_birth')")
        ->not->toContain('Carbon::parse($state)');
});
