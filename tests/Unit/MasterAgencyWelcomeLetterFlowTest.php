<?php

declare(strict_types=1);

it('usa el nombre corporativo para enviar carta de bienvenida al crear agencia master', function (): void {
    $createPagePath = dirname(__DIR__, 2).'/app/Filament/Master/Resources/Agencies/Pages/CreateAgency.php';
    $contents = file_get_contents($createPagePath);

    expect($contents)
        ->toContain('$record->sendCartaBienvenida($record->code, $record->name_corporative, $record->email);');
});

it('incluye accion para reenviar carta de bienvenida en tabla de agencias de corretaje del panel business', function (): void {
    $businessTablePath = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Agencies/Tables/AgenciesTable.php';
    $masterTablePath = dirname(__DIR__, 2).'/app/Filament/Master/Resources/Agencies/Tables/AgenciesTable.php';
    $businessContents = file_get_contents($businessTablePath);
    $masterContents = file_get_contents($masterTablePath);

    expect($businessContents)
        ->toContain("Action::make('resend_welcome_letter')")
        ->toContain("->label('Reenviar carta de bienvenida')")
        ->toContain('$record->sendCartaBienvenida($record->code, $record->name_corporative, $record->email);')
        ->toContain('AUDIT_BUSINESS_AGENCY_WELCOME_LETTER_RESENT')
        ->toContain('AUDIT_BUSINESS_AGENCY_WELCOME_LETTER_RESEND_FAILED');

    expect($masterContents)
        ->not->toContain("Action::make('resend_welcome_letter')");
});

it('adjunta pdf de carta de bienvenida de agencia con attachment API actual', function (): void {
    $mailPath = dirname(__DIR__, 2).'/app/Mail/MailCartaBienvenidaAgenteAgenciaTwo.php';
    $contents = file_get_contents($mailPath);

    expect($contents)
        ->toContain('use Illuminate\Mail\Mailables\Attachment;')
        ->toContain("Attachment::fromPath(public_path('storage/'.\$this->name_pdf))");
});
