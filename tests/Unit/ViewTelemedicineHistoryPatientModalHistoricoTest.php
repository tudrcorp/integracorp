<?php

declare(strict_types=1);

it('la modal lateral de historia clinica ya no incluye la seccion HISTORICO', function (): void {
    $contents = file_get_contents(
        dirname(__DIR__, 2).'/app/Livewire/FilamentView/ViewTelemedicineHistoryPatient.php'
    );

    expect($contents)
        ->not->toContain("->heading('HISTORICO')")
        ->not->toContain('Registro histórico de las actualización de antecedentes')
        ->toContain("->heading('ANTECEDENTES PERSONALES Y FAMILIARES')");
});
