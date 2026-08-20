<?php

declare(strict_types=1);

use App\Support\WhiteCompanies\WhiteCompanySalesReportKey;

uses(Tests\TestCase::class);

function reportRows(): array
{
    return [
        ['code' => 'TDEC-IND-000396', 'sale_price' => 297.0, 'neta_tdg' => 193.0, 'neta_partner' => 104.0],
        ['code' => 'TDEC-IND-000399', 'sale_price' => 486.0, 'neta_tdg' => 309.0, 'neta_partner' => 177.0],
    ];
}

function reportTotals(): array
{
    return ['sale_price' => 783.0, 'neta_tdg' => 502.0, 'neta_partner' => 281.0];
}

it('da el formato legible de la llave', function (): void {
    $key = WhiteCompanySalesReportKey::make(21, '01/08/2026', '31/08/2026', reportRows(), reportTotals());

    expect($key)->toStartWith('TDG-')
        ->and($key)->toMatch('/^TDG-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{4}$/');
});

it('produce la misma llave para el mismo contenido', function (): void {
    $first = WhiteCompanySalesReportKey::make(21, '01/08/2026', '31/08/2026', reportRows(), reportTotals());
    $second = WhiteCompanySalesReportKey::make(21, '01/08/2026', '31/08/2026', reportRows(), reportTotals());

    expect($first)->toBe($second);
});

it('no depende del orden de las filas', function (): void {
    $normal = WhiteCompanySalesReportKey::make(21, '01/08/2026', '31/08/2026', reportRows(), reportTotals());
    $invertido = WhiteCompanySalesReportKey::make(21, '01/08/2026', '31/08/2026', array_reverse(reportRows()), reportTotals());

    expect($normal)->toBe($invertido);
});

it('cambia la llave si se altera un importe', function (): void {
    $original = WhiteCompanySalesReportKey::make(21, '01/08/2026', '31/08/2026', reportRows(), reportTotals());

    $alterado = reportRows();
    $alterado[0]['neta_tdg'] = 100.0;

    expect(WhiteCompanySalesReportKey::make(21, '01/08/2026', '31/08/2026', $alterado, reportTotals()))
        ->not->toBe($original);
});

it('cambia la llave si cambian la aliada, el rango o los totales', function (array $args): void {
    $original = WhiteCompanySalesReportKey::make(21, '01/08/2026', '31/08/2026', reportRows(), reportTotals());

    $key = WhiteCompanySalesReportKey::make(
        $args[0],
        $args[1],
        $args[2],
        reportRows(),
        $args['totals'] ?? reportTotals(),
    );

    expect($key)->not->toBe($original);
})->with([
    'otra aliada' => [[22, '01/08/2026', '31/08/2026']],
    'otro inicio' => [[21, '02/08/2026', '31/08/2026']],
    'otro fin' => [[21, '01/08/2026', '30/08/2026']],
    'otros totales' => [[21, '01/08/2026', '31/08/2026', 'totals' => ['sale_price' => 999.0, 'neta_tdg' => 502.0, 'neta_partner' => 281.0]]],
]);

it('acepta la llave con o sin guiones y rechaza una falsa', function (): void {
    $key = WhiteCompanySalesReportKey::make(21, '01/08/2026', '31/08/2026', reportRows(), reportTotals());

    expect(WhiteCompanySalesReportKey::matches($key, $key))->toBeTrue()
        ->and(WhiteCompanySalesReportKey::matches(str_replace('-', '', $key), $key))->toBeTrue()
        ->and(WhiteCompanySalesReportKey::matches(strtolower($key), $key))->toBeTrue()
        ->and(WhiteCompanySalesReportKey::matches('TDG-0000-0000-0000-0000', $key))->toBeFalse();
});

it('filtra por activated_at con STR_TO_DATE y solo afiliaciones individuales', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Services/WhiteCompanySalesReportService.php');

    expect($source)
        ->toContain("STR_TO_DATE(activated_at, '%d/%m/%Y') BETWEEN ? AND ?")
        ->toContain('Affiliation::query()')
        ->not->toContain('AffiliationCorporate');
});

it('usa la neta congelada en la afiliacion y no la matriz vigente', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Services/WhiteCompanySalesReportService.php');

    expect($source)
        ->toContain('$affiliation->white_company_sale_price')
        ->toContain('$affiliation->white_company_neta')
        ->toContain('round($salePrice - $netaTdg, 2)')
        ->not->toContain('WhiteCompanyFee');
});

it('lleva los dos logos y la llave al pie del pdf', function (): void {
    $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/documents/white-company-sales-report.blade.php');

    expect($view)
        ->toContain('$tdgLogo')
        ->toContain('$partnerLogo')
        ->toContain("\$report['security_key']")
        ->toContain('$verificationUrl')
        ->toContain('Neta TDG');
});

it('envia el reporte en cola y no en el request', function (): void {
    $job = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/SendWhiteCompanySalesReportJob.php');

    expect($job)
        ->toContain('implements ShouldQueue')
        ->toContain("config('affiliate-card.documents_queue', 'documents')")
        ->toContain('WhiteCompanySalesReportService::build');
});

it('protege la accion con el permiso propio de administracion', function (): void {
    $slug = App\Support\Filament\BusinessFilamentActionPermissionRegistry::WHITE_COMPANY_SALES_REPORT;

    expect($slug)->toBe('reporte-ventas-empresas-aliadas')
        ->and(App\Support\Filament\BusinessFilamentActionPermissionRegistry::modulesForSlug($slug))
        ->toBe(['ADMINISTRACION']);

    $table = file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Business/Resources/WhiteCompanies/Tables/WhiteCompaniesTable.php'
    );

    expect($table)
        ->toContain("Action::make('salesReport')")
        ->toContain('BusinessFilamentActionPermissionRegistry::WHITE_COMPANY_SALES_REPORT')
        ->toContain('->authorize(');
});

it('abre un modal de vista previa en lugar de enviar al confirmar', function (): void {
    $table = file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Business/Resources/WhiteCompanies/Tables/WhiteCompaniesTable.php'
    );

    /** La acción no debe disparar el envío: solo muestra el panel. */
    expect($table)
        ->toContain("'filament.administration.white-companies.sales-report-modal'")
        ->toContain('->modalSubmitAction(false)')
        ->toContain('->action(fn () => null)')
        ->not->toContain('SendWhiteCompanySalesReportJob');
});

it('separa generar de enviar en dos endpoints y ambos exigen el permiso', function (): void {
    $controller = file_get_contents(
        dirname(__DIR__, 2).'/app/Http/Controllers/WhiteCompanySalesReportController.php'
    );

    expect($controller)
        ->toContain('public function preview(')
        ->toContain('public function send(')
        ->toContain('private function userCanIssueReport(): bool')
        ->toContain('BusinessFilamentActionPermissionRegistry::WHITE_COMPANY_SALES_REPORT');

    /** La vista previa nunca despacha el envío. */
    $previewBody = substr(
        $controller,
        strpos($controller, 'public function preview('),
        strpos($controller, 'public function send(') - strpos($controller, 'public function preview('),
    );

    expect($previewBody)->not->toContain('SendWhiteCompanySalesReportJob::dispatch');
});

it('valida destinatarios de correo y de whatsapp', function (): void {
    $controller = file_get_contents(
        dirname(__DIR__, 2).'/app/Http/Controllers/WhiteCompanySalesReportController.php'
    );

    expect($controller)
        ->toContain("'recipients.*' => ['required', 'email:rfc']")
        ->toContain("'phones.*' => ['required', 'string', 'min:7', 'max:20']")
        ->toContain('Indique al menos un correo o un número de WhatsApp.')
        ->toContain('normalizeEmails')
        ->toContain('normalizePhones')
        ->toContain("SecurityAudit::log('AUDIT_WHITE_COMPANY_SALES_REPORT_SENT'")
        ->toContain("'sent_by' => Auth::user()?->name")
        ->toContain('SendWhiteCompanySalesReportWhatsAppJob::dispatch');
});

it('el correo es formal, lleva el logo de tu dr group y habla de estado de cuenta', function (): void {
    $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/mails/whiteCompanySalesReport.blade.php');

    expect($view)
        ->toContain('image/logoNewTDG.png')
        ->toContain('Estado de cuenta y conciliación de afiliaciones')
        ->toContain('afiliaciones')
        ->toContain('Estimados señores de')
        ->toContain('Sin otro particular, quedamos a su entera disposición.')
        ->toContain('Departamento de Administración')
        ->toContain('$securityKey')
        ->toContain('confidencial');

    $mailable = file_get_contents(dirname(__DIR__, 2).'/app/Mail/WhiteCompanySalesReportMail.php');

    expect($mailable)
        ->toContain("subject: 'Estado de cuenta y conciliación de afiliaciones — '")
        ->toContain('Attachment::fromPath($this->attachmentPath)');
});

it('todo el envio vive en una sola cola', function (): void {
    $root = dirname(__DIR__, 2);

    /**
     * El mailable no debe ser ShouldQueue: se envía desde un job que ya está en
     * cola y, de serlo, Laravel lo re-encolaría en `default`.
     */
    expect(file_get_contents($root.'/app/Mail/WhiteCompanySalesReportMail.php'))
        ->not->toContain('implements ShouldQueue');

    foreach (['SendWhiteCompanySalesReportJob', 'SendWhiteCompanySalesReportWhatsAppJob'] as $job) {
        expect(file_get_contents($root.'/app/Jobs/'.$job.'.php'))
            ->toContain("config('affiliate-card.documents_queue', 'documents')");
    }
});

it('el mensaje de whatsapp mantiene el tono formal y adjunta el pdf', function (): void {
    $service = file_get_contents(dirname(__DIR__, 2).'/app/Services/WhiteCompanySalesReportService.php');

    expect($service)
        ->toContain('public static function whatsAppCaption(array $report): string')
        ->toContain('Estimados señores de')
        ->toContain('estado de cuenta correspondiente a la conciliación');

    $job = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/SendWhiteCompanySalesReportWhatsAppJob.php');

    expect($job)
        ->toContain('implements ShouldQueue')
        ->toContain('NotificationController::sendWhatsAppDocument')
        ->toContain("'reportes-aliadas/'.\$filename")
        ->toContain('AUDIT_WHITE_COMPANY_SALES_REPORT_WHATSAPP_SENT')
        ->toContain('AUDIT_WHITE_COMPANY_SALES_REPORT_WHATSAPP_FAILED');
});

it('el panel ofrece los dos canales y exige al menos un destino', function (): void {
    $script = file_get_contents(
        dirname(__DIR__, 2).'/resources/views/filament/administration/partials/white-company-sales-report-panel-script.blade.php'
    );

    expect($script)
        ->toContain('addPhone()')
        ->toContain('removePhone(')
        ->toContain('get totalDestinations()')
        ->toContain('phones: this.phones');

    $modal = file_get_contents(
        dirname(__DIR__, 2).'/resources/views/filament/administration/white-companies/sales-report-modal.blade.php'
    );

    expect($modal)
        ->toContain('WhatsApp (opcional)')
        ->toContain('Enviar estado de cuenta')
        ->toContain('totalDestinations === 0')
        ->toContain("'defaultPhone' =>");
});

it('registra las rutas de vista previa y envio', function (): void {
    $routes = file_get_contents(dirname(__DIR__, 2).'/routes/web.php');

    expect($routes)
        ->toContain('administration.white-companies.sales-report.preview')
        ->toContain('administration.white-companies.sales-report.send')
        ->toContain('white-company-sales-report.verify');
});

it('el panel invalida la vista previa si cambia el rango', function (): void {
    $script = file_get_contents(
        dirname(__DIR__, 2).'/resources/views/filament/administration/partials/white-company-sales-report-panel-script.blade.php'
    );

    expect($script)
        ->toContain('resetPreview()')
        ->toContain('addRecipient()')
        ->toContain('removeRecipient(')
        ->toContain('async send()')
        ->toContain('async generate()');

    $modal = file_get_contents(
        dirname(__DIR__, 2).'/resources/views/filament/administration/white-companies/sales-report-modal.blade.php'
    );

    expect($modal)
        ->toContain('@input="resetPreview()"')
        ->toContain('Enviar estado de cuenta')
        ->toContain('x-bind:src="documentUrl"');
});
