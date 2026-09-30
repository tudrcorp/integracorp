<?php

declare(strict_types=1);

use App\Enums\ClinicalServiceChannel;
use App\Support\ClinicalEntitlements\ClinicalConsultationConsumption;
use App\Support\Telemedicine\ConsultationClinicalSelections;

/*
 * ---------------------------------------------------------------------------
 * La cobertura sale del campo donde el médico eligió el ítem
 * ---------------------------------------------------------------------------
 */

it('marca como no cubierto lo elegido en «Otros Especialistas» aunque el catálogo lo tenga como cubierto', function (): void {
    $selecciones = ConsultationClinicalSelections::fromFormData([
        'other_specialist' => ['ALERGOLOGO', 'CARDIOLOGO', 'CARDIOLOGO + ELECTROCARDIOGRAMA'],
    ]);

    expect($selecciones->typedSpecialists())->toBe([
        ['name' => 'ALERGOLOGO', 'type' => 'NO CUBIERTO'],
        ['name' => 'CARDIOLOGO', 'type' => 'NO CUBIERTO'],
        ['name' => 'CARDIOLOGO + ELECTROCARDIOGRAMA', 'type' => 'NO CUBIERTO'],
    ]);
});

it('conserva cubiertos primero y no cubiertos después, como se registraban', function (): void {
    $selecciones = ConsultationClinicalSelections::fromFormData([
        'labs' => ['GLICEMIA'],
        'other_labs' => ['BUN'],
        'studies' => ['RX TORAX'],
        'other_studies' => ['ECO ABDOMINAL'],
        'consult_specialist' => ['CARDIOLOGO'],
        'other_specialist' => ['ALERGOLOGO'],
    ]);

    expect($selecciones->typedLabs())->toBe([
        ['name' => 'GLICEMIA', 'type' => 'CUBIERTO'],
        ['name' => 'BUN', 'type' => 'NO CUBIERTO'],
    ])->and($selecciones->typedStudies())->toBe([
        ['name' => 'RX TORAX', 'type' => 'CUBIERTO'],
        ['name' => 'ECO ABDOMINAL', 'type' => 'NO CUBIERTO'],
    ])->and($selecciones->typedSpecialists())->toBe([
        ['name' => 'CARDIOLOGO', 'type' => 'CUBIERTO'],
        ['name' => 'ALERGOLOGO', 'type' => 'NO CUBIERTO'],
    ]);
});

it('un nombre repetido en el catálogo con los dos tipos respeta el campo elegido', function (): void {
    // CREATININA existe como CUBIERTO y como NO CUBIERTO; antes siempre ganaba el primero.
    $selecciones = ConsultationClinicalSelections::fromFormData([
        'labs' => ['CREATININA'],
        'other_labs' => ['CREATININA'],
    ]);

    expect($selecciones->typedLabs())->toBe([
        ['name' => 'CREATININA', 'type' => 'CUBIERTO'],
        ['name' => 'CREATININA', 'type' => 'NO CUBIERTO'],
    ]);
});

it('no depende del catálogo: un nombre que ya no existe se registra igual', function (): void {
    $selecciones = ConsultationClinicalSelections::fromFormData([
        'other_specialist' => ['ESPECIALIDAD RETIRADA DEL CATALOGO'],
    ]);

    expect($selecciones->typedSpecialists())->toBe([
        ['name' => 'ESPECIALIDAD RETIRADA DEL CATALOGO', 'type' => 'NO CUBIERTO'],
    ]);
});

it('descarta valores vacíos o que no son nombres', function (): void {
    $selecciones = ConsultationClinicalSelections::fromFormData([
        'consult_specialist' => ['', '  ', null, ['anidado'], 'PEDIATRA'],
        'other_specialist' => null,
    ]);

    expect($selecciones->typedSpecialists())->toBe([
        ['name' => 'PEDIATRA', 'type' => 'CUBIERTO'],
    ]);
});

it('sin selecciones no hay ítems que registrar', function (): void {
    $selecciones = ConsultationClinicalSelections::empty();

    expect($selecciones->typedLabs())->toBe([])
        ->and($selecciones->typedStudies())->toBe([])
        ->and($selecciones->typedSpecialists())->toBe([]);
});

it('el guardado de la consulta ya no busca la cobertura por nombre en el catálogo', function (): void {
    $pagina = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Pages/CreateTelemedicineConsultationPatient.php');

    expect($pagina)
        ->toContain('$selections->typedLabs()')
        ->toContain('$selections->typedStudies()')
        ->toContain('$selections->typedSpecialists()')
        ->toContain("\$specialist->type = \$finalArrSpecialist[\$i]['type'];")
        ->not->toContain("TelemedicineListSpecialist::where('name'")
        ->not->toContain("TelemedicineListLaboratory::where('name'")
        ->not->toContain("TelemedicineListStudy::where('name'");
});

/*
 * ---------------------------------------------------------------------------
 * Lo no cubierto no consume cupo
 * ---------------------------------------------------------------------------
 */

it('lo no cubierto no pide cupo de laboratorio, imagen ni especialista', function (): void {
    $canales = ClinicalConsultationConsumption::requestedChannels([
        'complements' => [2, 3],
        'other_labs' => ['BUN'],
        'other_studies' => ['ECO ABDOMINAL'],
        'other_specialist' => ['ALERGOLOGO'],
    ]);

    expect($canales)
        ->not->toHaveKey(ClinicalServiceChannel::Laboratory->value)
        ->not->toHaveKey(ClinicalServiceChannel::Imaging->value)
        ->not->toHaveKey(ClinicalServiceChannel::Specialist->value);
});

it('lo cubierto sigue pidiendo su cupo aunque venga junto a lo no cubierto', function (): void {
    $canales = ClinicalConsultationConsumption::requestedChannels([
        'complements' => [2, 3],
        'labs' => ['GLICEMIA'],
        'other_labs' => ['BUN'],
        'studies' => ['RX TORAX'],
        'other_studies' => ['ECO ABDOMINAL'],
        'consult_specialist' => ['CARDIOLOGO'],
        'other_specialist' => ['ALERGOLOGO'],
    ]);

    expect($canales)
        ->toHaveKey(ClinicalServiceChannel::Laboratory->value)
        ->toHaveKey(ClinicalServiceChannel::Imaging->value)
        ->toHaveKey(ClinicalServiceChannel::Specialist->value);
});

it('los campos «otros» del formulario no tienen bloqueo por cupo', function (): void {
    $form = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Schemas/TelemedicineConsultationPatientForm.php');

    foreach (['other_labs', 'other_studies', 'other_specialist'] as $campo) {
        $inicio = strpos($form, "Select::make('{$campo}')");
        expect($inicio)->not->toBeFalse();

        $siguiente = strpos($form, 'Select::make(', $inicio + 1);
        $fin = $siguiente === false ? strpos($form, '])', $inicio) : min($siguiente, strpos($form, '])', $inicio));
        $bloque = substr($form, $inicio, $fin - $inicio);

        expect($bloque)
            ->not->toContain('ClinicalQuotaFormGuard')
            ->toContain('no consumen cupo');
    }
});
