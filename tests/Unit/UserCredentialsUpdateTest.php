<?php

declare(strict_types=1);

use App\Support\Filament\UserCredentialSynchronizer;

it('formulario de usuario incluye pestaña de credenciales en edición', function (): void {
    $php = file_get_contents(__DIR__.'/../../app/Filament/Business/Resources/Users/Schemas/UserForm.php');

    expect($php)->not->toBeFalse()
        ->toContain("Tab::make('Correo y contraseña')")
        ->toContain('UserCredentialSynchronizer::resolveLinkedAgent')
        ->toContain('UserCredentialSynchronizer::resolveLinkedAgency')
        ->toContain('->visibleOn(\'edit\')')
        ->toContain('Nueva contraseña');
});

it('pagina editar usuario muestra encabezado principal y sincroniza credenciales', function (): void {
    $php = file_get_contents(__DIR__.'/../../app/Filament/Business/Resources/Users/Pages/EditUser.php');

    expect($php)->not->toBeFalse()
        ->toContain('UserPageHeader::make($user, context: \'edit\')')
        ->toContain('UserCredentialSynchronizer::syncRelatedRecordsAndAudit')
        ->toContain('Hash::make')
        ->toContain('savedNotificationBody')
        ->toContain('Heroicon::OutlinedCheckCircle')
        ->not->toContain('->send()');
});

it('sincronizador de credenciales registra trazas de seguridad', function (): void {
    $php = file_get_contents(__DIR__.'/../../app/Support/Filament/UserCredentialSynchronizer.php');

    expect($php)->not->toBeFalse()
        ->toContain('SecurityAudit::log')
        ->toContain('AUDIT_BUSINESS_USER_CREDENTIALS_UPDATED')
        ->toContain('AUDIT_BUSINESS_USER_AGENT_EMAIL_SYNCED')
        ->toContain('AUDIT_BUSINESS_USER_AGENCY_EMAIL_SYNCED')
        ->toContain('resolveLinkedAgent')
        ->toContain('resolveLinkedAgency');
});

it('encabezado de usuario reutilizable incluye perfil comercial', function (): void {
    $php = file_get_contents(__DIR__.'/../../app/Support/Filament/UserPageHeader.php');

    expect($php)->not->toBeFalse()
        ->toContain('Agencia master')
        ->toContain('Agente comercial')
        ->toContain('Editar usuario INTEGRACORP');
});

it('pagina ver usuario usa encabezado compartido', function (): void {
    $php = file_get_contents(__DIR__.'/../../app/Filament/Business/Resources/Users/Pages/ViewUser.php');

    expect($php)->toContain('UserPageHeader::make($user)');
});

it('sincronizador resuelve agencia solo para master y general', function (): void {
    $php = file_get_contents(__DIR__.'/../../app/Support/Filament/UserCredentialSynchronizer.php');

    expect($php)->toContain("in_array(\$user->agency_type, ['MASTER', 'GENERAL'], true)");
});

it('sincronizador resuelve agente para perfiles agente y subagente', function (): void {
    expect(UserCredentialSynchronizer::class)->toBeString();
});
