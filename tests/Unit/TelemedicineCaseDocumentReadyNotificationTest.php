<?php

declare(strict_types=1);

use App\Support\Telemedicine\TelemedicineCaseDocumentReadyNotification;

uses(Tests\TestCase::class);

it('explica que el documento se descarga o reenvia desde el expediente documental', function (): void {
    expect(TelemedicineCaseDocumentReadyNotification::body('informe-largo.pdf'))
        ->toContain('informe-largo.pdf')
        ->toContain('detalle del caso')
        ->toContain('Expediente documental')
        ->toContain('descargarlo o reenviarlo');
});

it('genera la url del detalle del caso en la pestana de expediente documental', function (): void {
    $url = TelemedicineCaseDocumentReadyNotification::caseExpedienteDocumentalUrl([
        'telemedicine_case_id' => 42,
    ]);

    expect($url)
        ->toContain('/telemedicina/telemedicine-cases/42')
        ->toContain('tab='.rawurlencode(TelemedicineCaseDocumentReadyNotification::EXPEDIENTE_DOCUMENTAL_TAB_QUERY));
});

it('resuelve el caso desde telemedicine_case_id del payload', function (): void {
    expect(TelemedicineCaseDocumentReadyNotification::resolveCaseId([
        'telemedicine_case_id' => 15,
        'telemedicine_consultation_id' => 99,
    ]))->toBe(15);
});

it('usa la constante de pestana alineada al slug de Expediente documental', function (): void {
    expect(TelemedicineCaseDocumentReadyNotification::EXPEDIENTE_DOCUMENTAL_TAB_QUERY)
        ->toBe('expediente-documental::tab');
});

it('el infolist de casos de telemedicina persiste la pestana en query string', function (): void {
    $contents = file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineCases/Schemas/TelemedicineCaseInfolist.php'
    );

    expect($contents)
        ->toContain('persistTabInQueryString(TelemedicineCaseDocumentReadyNotification::TAB_QUERY_PARAMETER)')
        ->toContain("Tab::make('Expediente documental')");
});

it('los jobs de generacion de PDF notifican con enlace al expediente documental', function (): void {
    $jobFiles = [
        'GeneratePdfInformeMedicoCorto.php',
        'GeneratePdfInformeMedicoLargo.php',
        'GeneratePdfMedicamentos.php',
        'GeneratePdfLaboratorio.php',
        'GeneratePdfImagenologia.php',
        'GeneratePdfEspecialista.php',
    ];

    foreach ($jobFiles as $jobFile) {
        $contents = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/'.$jobFile);

        expect($contents)
            ->toContain('TelemedicineCaseDocumentReadyNotification::send')
            ->not->toContain("->label('Descargar archivo')")
            ->not->toContain("->title('¡TAREA COMPLETADA!')");
    }
});
