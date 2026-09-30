<?php

declare(strict_types=1);

use App\Support\UrlPathEncoder;

$basePath = dirname(__DIR__, 2);

it('codifica la tilde del informe médico conservando las barras', function (): void {
    expect(UrlPathEncoder::encode('telemedicina-doc/16007868-REF-60294-Informe-Médico.pdf'))
        ->toBe('telemedicina-doc/16007868-REF-60294-Informe-M%C3%A9dico.pdf');
});

it('deja intactos los nombres ASCII que ya se usaban', function (string $path): void {
    expect(UrlPathEncoder::encode($path))->toBe($path);
})->with([
    'informe viejo' => 'telemedicina-doc/V-12345678-REF-100-informe-largo.pdf',
    'medicamentos' => 'telemedicina-doc/16007868-REF-60294-medicamentos.pdf',
    'bitacora' => 'telemedicina-doc/bitacoras/bitacora_caso_1.pdf',
    'nombre suelto' => 'archivo.pdf',
]);

it('codifica espacios y caracteres reservados de un segmento', function (): void {
    expect(UrlPathEncoder::encode('telemedicina-doc/resultado lab #2?.pdf'))
        ->toBe('telemedicina-doc/resultado%20lab%20%232%3F.pdf');
});

it('devuelve vacío si la ruta es vacía', function (): void {
    expect(UrlPathEncoder::encode(''))->toBe('');
});

it('el envío de documentos por WhatsApp y las descargas de telemedicina codifican la ruta', function (string $file, string $needle) use ($basePath): void {
    expect(file_get_contents($basePath.'/'.$file))->toContain($needle);
})->with([
    'whatsapp (UltraMsg)' => ['app/Http/Controllers/NotificationController.php', "'/'.UrlPathEncoder::encode(\$relativePath)"],
    'entrega de documentos del caso' => ['app/Services/Telemedicine/TelemedicineCaseDocumentDeliveryService.php', 'UrlPathEncoder::encode($relativePath)'],
    'notificación de la consulta' => ['app/Services/TelemedicineConsultationDocumentsNotificationService.php', 'UrlPathEncoder::encode($filename)'],
    'expediente documental' => ['app/Support/Telemedicine/TelemedicineCaseDocumentsCatalog.php', 'UrlPathEncoder::encode($path)'],
    'bitácora AMD' => ['app/Support/Telemedicine/TelemedicineAmdBitacoraCatalog.php', 'UrlPathEncoder::encode($filePath)'],
    'documentos del caso (telemedicina)' => ['app/Filament/Telemedicina/Resources/TelemedicineCases/RelationManagers/TelemedicineDocumentsRelationManager.php', 'UrlPathEncoder::encode((string) $record->name)'],
    'documentos del caso (operaciones)' => ['app/Filament/Operations/Resources/TelemedicineCases/RelationManagers/TelemedicineDocumentsRelationManager.php', 'UrlPathEncoder::encode((string) $record->name)'],
]);
