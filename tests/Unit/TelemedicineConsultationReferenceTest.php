<?php

use App\Support\Telemedicine\TelemedicineConsultationReference;

uses(Tests\TestCase::class);

beforeEach(fn () => DB::beginTransaction());
afterEach(fn () => DB::rollBack());

it('genera una referencia con el formato REF-número', function (): void {
    expect(TelemedicineConsultationReference::generate())->toMatch('/^REF-\d{5,7}$/');
});

it('conserva la referencia libre y reemplaza la ya usada o vacía', function (): void {
    $used = (string) DB::table('telemedicine_consultation_patients')->whereNotNull('code_reference')->value('code_reference');

    if ($used === '') {
        $this->markTestSkipped('No hay consultas con referencia para comprobar el caso repetido.');
    }

    expect(TelemedicineConsultationReference::ensureUnique($used))->not->toBe($used)
        ->and(TelemedicineConsultationReference::ensureUnique(null))->toMatch('/^REF-\d+$/')
        ->and(TelemedicineConsultationReference::ensureUnique('REF-ZZ-LIBRE'))->toBe('REF-ZZ-LIBRE');
});

it('el formulario y el guardado usan el generador único y no rand()', function (): void {
    $base = dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/';
    $form = file_get_contents($base.'Schemas/TelemedicineConsultationPatientForm.php');
    $page = file_get_contents($base.'Pages/CreateTelemedicineConsultationPatient.php');

    expect($form)->not->toContain("'REF-'.rand")
        ->and($form)->toContain('TelemedicineConsultationReference::generate()')
        ->and($page)->toContain('TelemedicineConsultationReference::ensureUnique(');
});
