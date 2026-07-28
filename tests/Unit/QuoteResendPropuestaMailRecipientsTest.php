<?php

declare(strict_types=1);

it('el reenvío manual de propuesta usa CC a cotizaciones tdg y BCC a solrodriguez', function (string $relativePath): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/'.$relativePath);

    expect($source)
        ->toContain("->cc('cotizacionestdg.ve@gmail.com')")
        ->toContain("->bcc('solrodriguez@tudrencasa.com')")
        ->not->toContain("->cc('solrodriguez@tudrencasa.com')");
})->with([
    'agents individual' => 'app/Filament/Agents/Resources/IndividualQuotes/Tables/IndividualQuotesTable.php',
    'general individual' => 'app/Filament/General/Resources/IndividualQuotes/Tables/IndividualQuotesTable.php',
    'master individual' => 'app/Filament/Master/Resources/IndividualQuotes/Tables/IndividualQuotesTable.php',
    'agents corporate' => 'app/Filament/Agents/Resources/CorporateQuotes/Tables/CorporateQuotesTable.php',
    'general corporate' => 'app/Filament/General/Resources/CorporateQuotes/Tables/CorporateQuotesTable.php',
    'master corporate' => 'app/Filament/Master/Resources/CorporateQuotes/Tables/CorporateQuotesTable.php',
    'business corporate interactive link' => 'app/Filament/Business/Resources/CorporateQuotes/Tables/CorporateQuotesTable.php',
    'business resend propuesta job' => 'app/Jobs/ResendEmailPropuestaEconomica.php',
    'send propuesta inicial job' => 'app/Jobs/SendEmailPropuestaEconomica.php',
    'send propuesta especial job' => 'app/Jobs/SendEmailPropuestaEconomicaPlanEspecial.php',
    'send propuesta especial cor job' => 'app/Jobs/SendEmailPropuestaEconomicaEspecialCor.php',
    'send propuesta inicial cor job' => 'app/Jobs/SendEmailPropuestaEconomicaInicialCor.php',
]);

it('cartas de bienvenida y links de registro de agentes/agencias usan CC comercial y BCC solrodriguez', function (string $relativePath): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/'.$relativePath);

    expect($source)
        ->toContain("->cc('dptocomercialtdg@gmail.com')")
        ->toContain("->bcc('solrodriguez@tudrencasa.com')");
})->with([
    'carta bienvenida agente' => 'app/Jobs/SendCartaBienvenidaAgenteAgencia.php',
    'carta bienvenida agencia' => 'app/Jobs/SendCartaBienvenidaAgenteAgenciaTwo.php',
    'carta bienvenida ejecutivo' => 'app/Jobs/SendCartaBienvenidaEjecutivo.php',
    'ficha pdf agente' => 'app/Jobs/SendBusinessAgentFichaPdfMailJob.php',
    'ficha pdf agencia' => 'app/Jobs/SendBusinessAgencyFichaPdfMailJob.php',
]);

it('NotificationController envía links de registro con CC comercial y BCC solrodriguez', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/NotificationController.php');

    expect($source)
        ->toContain("->cc('dptocomercialtdg@gmail.com')")
        ->toContain("->bcc('solrodriguez@tudrencasa.com')")
        ->not->toContain("->cc('solrodriguez@tudrencasa.com')")
        ->not->toContain("->cc('tudrgroup.info@gmail.com')");
});
