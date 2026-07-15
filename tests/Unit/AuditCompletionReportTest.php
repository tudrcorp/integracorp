<?php

declare(strict_types=1);

use App\Filament\Business\Resources\AffiliationCorporates\Pages\ViewAffiliationCorporate;
use App\Filament\Business\Resources\Affiliations\Pages\ViewAffiliation;
use App\Filament\Business\Resources\Agencies\Pages\ViewAgency;
use App\Filament\Business\Resources\Agents\Pages\ViewAgent;
use App\Support\Audit\AuditCompletionReport;

uses(Tests\TestCase::class);

it('considera completa solo cuando estan todos los puntos del catalogo', function (): void {
    $keys = array_keys(ViewAgency::auditItemsCatalog());
    $partial = array_slice($keys, 0, 2);

    expect(AuditCompletionReport::isFullyAudited($keys, $keys))->toBeTrue()
        ->and(AuditCompletionReport::isFullyAudited($partial, $keys))->toBeFalse()
        ->and(AuditCompletionReport::isFullyAudited([], $keys))->toBeFalse()
        ->and(AuditCompletionReport::isFullyAudited(null, $keys))->toBeFalse();
});

it('considera completa aunque haya claves extra o desordenadas', function (): void {
    $keys = array_keys(ViewAgent::auditItemsCatalog());
    $shuffled = array_reverse($keys);
    $shuffled[] = 'clave_obsoleta';

    expect(AuditCompletionReport::isFullyAudited($shuffled, $keys))->toBeTrue();
});

it('arma un cuerpo de whatsapp con total, auditados y pendientes por categoria', function (): void {
    $counts = [
        'agencies' => ['label' => 'Agencias de corretaje', 'total' => 10, 'audited' => 0, 'pending' => 10],
        'agents' => ['label' => 'Agentes de corretaje', 'total' => 8, 'audited' => 3, 'pending' => 5],
        'individual_affiliations' => ['label' => 'Afiliaciones individuales', 'total' => 20, 'audited' => 12, 'pending' => 8],
        'corporate_affiliations' => ['label' => 'Afiliaciones corporativas', 'total' => 4, 'audited' => 1, 'pending' => 3],
        'totals' => ['total' => 42, 'audited' => 16, 'pending' => 26],
    ];

    $body = AuditCompletionReport::whatsappBody($counts);

    expect($body)
        ->toContain('Reporte diario de auditorías')
        ->toContain('*Agentes de corretaje*')
        ->toContain('Total: 8  |  Auditados: 3  |  Pendientes: 5')
        ->toContain('*TOTAL GENERAL*')
        ->toContain('Registros: 42  |  Auditados: 16  |  Pendientes: 26');
});

it('expone catalogos de auditoria no vacios para los cuatro recursos', function (): void {
    expect(ViewAgency::auditItemsCatalog())->not->toBeEmpty()
        ->and(ViewAgent::auditItemsCatalog())->not->toBeEmpty()
        ->and(ViewAffiliation::auditItemsCatalog())->not->toBeEmpty()
        ->and(ViewAffiliationCorporate::auditItemsCatalog())->not->toBeEmpty();
});

it('programa el job de auditoria diariamente a las 7:00am', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/routes/console.php');

    expect($source)
        ->toContain('SendDailyAuditSummary')
        ->toContain("Schedule::job(new SendDailyAuditSummary, 'system')")
        ->toContain("->dailyAt('7:00')")
        ->toContain('->when($dailyAuditSummaryIsActive)');
});

it('el job notifica a destinatarios del centro de notificaciones', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/SendDailyAuditSummary.php');

    expect($source)
        ->toContain('SystemNotificationKey::DailyAuditSummary')
        ->toContain('SystemNotificationRecipients::emails')
        ->toContain('SystemNotificationRecipients::phones')
        ->toContain('SendNotificacionWhatsApp::dispatch')
        ->toContain('AuditCompletionSummaryMail');

    expect(\App\Enums\SystemNotificationKey::DailyAuditSummary->defaultEmails())
        ->toBe(['solrodriguez@tudrencasa.com']);

    expect(\App\Enums\SystemNotificationKey::DailyAuditSummary->defaultPhones())
        ->toBe(['04127018390', '04143027250', '04245718777']);
});
