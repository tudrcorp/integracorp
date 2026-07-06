<?php

declare(strict_types=1);

function contadoBasePath(string $path): string
{
    return dirname(__DIR__, 2).'/'.ltrim($path, '/');
}

it('declara is_cash en fillable y casts del modelo OperationQuoteGenerator', function (): void {
    $src = file_get_contents(contadoBasePath('app/Models/OperationQuoteGenerator.php'));

    expect($src)
        ->toContain("'is_cash'")
        ->toContain("'is_cash' => 'boolean'");
});

it('existe la migración add_is_cash_to_operation_quote_generators con boolean default false', function (): void {
    $files = glob(contadoBasePath('database/migrations/*add_is_cash_to_operation_quote_generators_table.php'));

    expect($files)->not->toBeEmpty();

    $src = file_get_contents($files[0]);

    expect($src)
        ->toContain("boolean('is_cash')")
        ->toContain('->default(false)')
        ->toContain('Schema::hasColumn');
});

it('el formulario de gestión incluye el checkbox is_cash visible al aprobar', function (): void {
    $src = file_get_contents(contadoBasePath('app/Filament/Operations/Resources/OperationCoordinationServices/Schemas/ManageCoordinationServiceQuotesForm.php'));

    expect($src)
        ->toContain("Checkbox::make('is_cash')")
        ->toContain("->label('Pago de contado')")
        ->toContain('STATUS_APPROVED');
});

it('formDefaults mapea is_cash y save persiste/dispara el notificador', function (): void {
    $src = file_get_contents(contadoBasePath('app/Support/Operations/CoordinationServiceQuoteManager.php'));

    expect($src)
        ->toContain("'is_cash' => (bool) \$quote->is_cash")
        ->toContain('$quote->is_cash = $isCash')
        ->toContain('ContadoQuotePaymentNotifier::dispatchForQuote');
});

it('el notificador envía correo y WhatsApp a los teléfonos indicados', function (): void {
    $src = file_get_contents(contadoBasePath('app/Support/Operations/ContadoQuotePaymentNotifier.php'));

    expect($src)
        ->toContain('SendContadoQuotePaymentEmail::dispatch')
        ->toContain('SendNotificacionWhatsApp::dispatch')
        ->toContain("'04242875732'")
        ->toContain("'04143027250'")
        ->toContain('normalizePhoneForWhatsApp');
});

it('el job de correo adjunta PDF y usa destinatarios correctos', function (): void {
    $src = file_get_contents(contadoBasePath('app/Jobs/SendContadoQuotePaymentEmail.php'));

    expect($src)
        ->toContain('implements ShouldQueue')
        ->toContain("config('parameters.EMAIL_ADMINISTRACION')")
        ->toContain('->cc(self::EMAIL_CC)')
        ->toContain("'solrodriguez@tudrencasa.com'")
        ->toContain('ContadoQuotePaymentMail');
});

it('el Mailable adjunta el PDF de la cotización', function (): void {
    $src = file_get_contents(contadoBasePath('app/Mail/ContadoQuotePaymentMail.php'));

    expect($src)
        ->toContain('Attachment::fromPath')
        ->toContain("view: 'mails.contado-quote-payment'");
});

it('la vista del correo de contado existe', function (): void {
    expect(file_exists(contadoBasePath('resources/views/mails/contado-quote-payment.blade.php')))->toBeTrue();
});

it('la tabla de cuentas por pagar resalta filas de contado y muestra columna CONTADO', function (): void {
    $src = file_get_contents(contadoBasePath('app/Filament/Operations/Resources/AccountsPayables/Tables/AccountsPayablesTable.php'));

    expect($src)
        ->toContain('->recordClasses(')
        ->toContain('$record->is_cash')
        ->toContain("TextColumn::make('is_cash')")
        ->toContain("'CONTADO'");
});
