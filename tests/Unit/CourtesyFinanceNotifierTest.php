<?php

declare(strict_types=1);

function courtesyNotifierBasePath(string $path): string
{
    return dirname(__DIR__, 2).'/'.ltrim($path, '/');
}

it('el notificador no despacha si is_courtesy es false', function (): void {
    $src = file_get_contents(courtesyNotifierBasePath('app/Support/Operations/CourtesyFinanceNotifier.php'));

    expect($src)
        ->toContain('dispatchForQuote')
        ->toContain('dispatchForReceivable')
        ->toContain('! (bool) $quote->is_courtesy')
        ->toContain('! (bool) $receivable->is_courtesy')
        ->toContain('SendCourtesyFinanceEmail::dispatch')
        ->toContain('SendNotificacionWhatsApp::dispatch')
        ->toContain("'04242875732'")
        ->toContain("'04143027250'")
        ->toContain('SERVICIO POR CORTESÍA');
});

it('el job de correo usa destinatarios de administración', function (): void {
    $src = file_get_contents(courtesyNotifierBasePath('app/Jobs/SendCourtesyFinanceEmail.php'));

    expect($src)
        ->toContain('implements ShouldQueue')
        ->toContain("config('parameters.EMAIL_ADMINISTRACION')")
        ->toContain('->cc(self::EMAIL_CC)')
        ->toContain("'solrodriguez@tudrencasa.com'")
        ->toContain('CourtesyFinanceMail')
        ->toContain('! (bool) $quote->is_courtesy')
        ->toContain('! (bool) $receivable->is_courtesy');
});

it('el Mailable usa la vista de cortesía', function (): void {
    $src = file_get_contents(courtesyNotifierBasePath('app/Mail/CourtesyFinanceMail.php'));

    expect($src)
        ->toContain("view: 'mails.courtesy-finance'")
        ->toContain('Servicio por CORTESÍA');
});

it('la vista del correo de cortesía existe', function (): void {
    expect(file_exists(courtesyNotifierBasePath('resources/views/mails/courtesy-finance.blade.php')))->toBeTrue();
});

it('se dispara al persistir quote y al sincronizar CxC', function (): void {
    $items = file_get_contents(courtesyNotifierBasePath('app/Support/Operations/CoordinationServiceItemsManager.php'));
    $ar = file_get_contents(courtesyNotifierBasePath('app/Support/Operations/AccountsReceivableManager.php'));

    expect($items)->toContain('CourtesyFinanceNotifier::dispatchForQuote')
        ->and($ar)->toContain('CourtesyFinanceNotifier::dispatchForReceivable');
});
