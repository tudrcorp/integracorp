<?php

declare(strict_types=1);

use App\Enums\SystemNotificationKey;
use App\Jobs\NotifyCompanyAssociateIlsCoverageConfirmedJob;
use App\Mail\CompanyAssociateIlsCoverageConfirmedMail;
use App\Models\CompanyAssociate;
use App\Models\SystemNotificationRecipientSetting;
use App\Support\Companies\CompanyAssociateIlsCoverageNotificationMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

uses(Tests\TestCase::class);

/**
 * Los tests que escriben corren dentro de una transacción que siempre se revierte:
 * la conexión de `tests/Unit` es la base de desarrollo real.
 */
beforeEach(function (): void {
    DB::beginTransaction();
});

afterEach(function (): void {
    DB::rollBack();
});

function ilsCoverageTestAssociate(array $overrides = []): ?CompanyAssociate
{
    $companyId = DB::table('companies')->value('id');
    $responsibleId = DB::table('company_responsibles')->value('id');

    if ($companyId === null || $responsibleId === null) {
        return null;
    }

    return CompanyAssociate::query()->create([
        'company_id' => $companyId,
        'company_responsible_id' => $responsibleId,
        'full_name' => 'Asociado Cobertura Test',
        'identity_card' => 'V-00000001',
        'birth_date' => '1990-05-14',
        'age' => 36,
        'sex' => 'MASCULINO',
        'contact_full_name' => 'Contacto Test',
        'identity_document' => 'company-associates/identity/test.pdf',
        'registered_at' => now(),
        'vaucher_ils' => 'ILS-TEST-9911',
        'date_init' => '01/08/2026',
        'date_end' => '01/08/2027',
        'document_ils' => 'company-associates/voucher-ils/voucher-test.pdf',
        ...$overrides,
    ]);
}

it('registra la clave de cobertura ILS en el centro de notificaciones', function (): void {
    $key = SystemNotificationKey::CompanyAssociateIlsCoverage;

    expect($key->value)->toBe('company_associate_ils_coverage')
        ->and(SystemNotificationKey::managed())->toContain($key)
        ->and($key->pausesScheduledTask())->toBeFalse();
});

it('tiene todos los textos del centro de notificaciones poblados y en español', function (): void {
    $key = SystemNotificationKey::CompanyAssociateIlsCoverage;

    expect($key->label())->toBe('Cobertura ILS confirmada')
        ->and($key->description())->not->toBeEmpty()
        ->and($key->heroTitle())->not->toBeEmpty()
        ->and($key->heroBody())->toContain('voucher')
        ->and($key->flowSteps())->toHaveCount(4)
        ->and($key->calloutTitle())->not->toBeEmpty()
        ->and($key->calloutBody())->not->toBeEmpty()
        ->and($key->calloutIcon())->toStartWith('heroicon-')
        ->and($key->emptyRecipientsHint())->not->toBeEmpty()
        ->and($key->activationHelp())->not->toBeEmpty()
        ->and($key->savedRecipientsMessage([], []))->not->toBeEmpty()
        ->and($key->savedRecipientsMessage(['a@b.com'], []))->not->toBeEmpty();
});

it('la acción de la tabla ya no guarda directo: remonta la confirmación de cobertura', function (): void {
    $source = file_get_contents(
        base_path('app/Filament/Business/Resources/CompanyAssociates/Actions/CompanyAssociatesTableActions.php'),
    );

    expect($source)->toContain('replaceMountedAction')
        ->and($source)->toContain('ListCompanyAssociates::CONFIRM_ILS_COVERAGE_ACTION')
        ->and($source)->toContain('argumentsForConfirmation')
        ->and($source)->toContain("->modalSubmitActionLabel('Guardar voucher')")
        ->and($source)->not->toContain('CompanyAssociateVoucherIlsUpdater::save($record, $data);');
});

it('la página declara la confirmación como modal de confirmación en español', function (): void {
    $source = file_get_contents(
        base_path('app/Filament/Business/Resources/CompanyAssociates/Pages/ListCompanyAssociates.php'),
    );

    expect($source)->toContain('protected function confirmCompanyAssociateIlsCoverageAction(): Action')
        ->and($source)->toContain('->requiresConfirmation()')
        ->and($source)->toContain('¿Está seguro de haber realizado toda la gestión')
        ->and($source)->toContain("->modalSubmitActionLabel('Sí, el cliente está cubierto')")
        ->and($source)->toContain('NotifyCompanyAssociateIlsCoverageConfirmedJob::dispatch')
        ->and($source)->toContain('->afterCommit()')
        ->and($source)->toContain('AUDIT_BUSINESS_COMPANY_ASSOCIATE_ILS_COVERAGE_CONFIRMED');
});

it('el mensaje de WhatsApp lleva el voucher, la vigencia y los datos del asociado', function (): void {
    $associate = ilsCoverageTestAssociate();

    if ($associate === null) {
        $this->markTestSkipped('No hay empresas ni responsables en la base para armar el asociado de prueba.');
    }

    $body = CompanyAssociateIlsCoverageNotificationMessage::whatsappBody($associate);

    expect($body)->toContain('COBERTURA CONFIRMADA')
        ->and($body)->toContain('está cubierto en su totalidad')
        ->and($body)->toContain('ILS-TEST-9911')
        ->and($body)->toContain('01/08/2026')
        ->and($body)->toContain('01/08/2027')
        ->and($body)->toContain('Asociado Cobertura Test');

    expect(CompanyAssociateIlsCoverageNotificationMessage::emailSubject($associate))
        ->toContain('Cobertura confirmada')
        ->toContain('ILS-TEST-9911');
});

it('distingue el voucher imagen del voucher PDF para elegir el envío de WhatsApp', function (string $path, bool $isImage): void {
    $associate = new CompanyAssociate(['document_ils' => $path]);

    expect(CompanyAssociateIlsCoverageNotificationMessage::voucherIsImage($associate))->toBe($isImage)
        ->and(CompanyAssociateIlsCoverageNotificationMessage::voucherFilename($associate))->toBe(basename($path));
})->with([
    'jpg' => ['company-associates/voucher-ils/v.jpg', true],
    'jpeg mayúsculas' => ['company-associates/voucher-ils/v.JPEG', true],
    'png' => ['company-associates/voucher-ils/v.png', true],
    'pdf' => ['company-associates/voucher-ils/v.pdf', false],
]);

it('sin documento cargado no arma adjunto ni URL de WhatsApp', function (): void {
    $associate = new CompanyAssociate(['document_ils' => null]);

    expect(CompanyAssociateIlsCoverageNotificationMessage::voucherStorageRelativePath($associate))->toBeNull()
        ->and(CompanyAssociateIlsCoverageNotificationMessage::voucherPublicUrl($associate))->toBeNull()
        ->and(CompanyAssociateIlsCoverageNotificationMessage::voucherFilename($associate))->toBeNull()
        ->and(CompanyAssociateIlsCoverageNotificationMessage::voucherAbsolutePath($associate))->toBeNull()
        ->and(CompanyAssociateIlsCoverageNotificationMessage::voucherIsImage($associate))->toBeFalse();
});

it('no envía nada si el asociado ya no existe', function (): void {
    Mail::fake();

    (new NotifyCompanyAssociateIlsCoverageConfirmedJob(999999999))->handle();

    Mail::assertNothingSent();
});

it('no envía nada si la notificación está inactiva en el centro de notificaciones', function (): void {
    $associate = ilsCoverageTestAssociate();

    if ($associate === null) {
        $this->markTestSkipped('No hay empresas ni responsables en la base para armar el asociado de prueba.');
    }

    Mail::fake();

    $setting = SystemNotificationRecipientSetting::for(SystemNotificationKey::CompanyAssociateIlsCoverage);
    $setting->update([
        'is_active' => false,
        'notification_emails' => ['destino@tudrencasa.com'],
        'notification_phones' => [],
    ]);

    (new NotifyCompanyAssociateIlsCoverageConfirmedJob((int) $associate->getKey()))->handle();

    Mail::assertNothingSent();
});

it('no envía nada si la notificación está activa pero sin destinatarios', function (): void {
    $associate = ilsCoverageTestAssociate();

    if ($associate === null) {
        $this->markTestSkipped('No hay empresas ni responsables en la base para armar el asociado de prueba.');
    }

    Mail::fake();

    $setting = SystemNotificationRecipientSetting::for(SystemNotificationKey::CompanyAssociateIlsCoverage);
    $setting->update([
        'is_active' => true,
        'notification_emails' => [],
        'notification_phones' => [],
    ]);

    (new NotifyCompanyAssociateIlsCoverageConfirmedJob((int) $associate->getKey()))->handle();

    Mail::assertNothingSent();
});

it('envía el correo de cobertura a los destinatarios configurados', function (): void {
    $associate = ilsCoverageTestAssociate();

    if ($associate === null) {
        $this->markTestSkipped('No hay empresas ni responsables en la base para armar el asociado de prueba.');
    }

    Mail::fake();

    $setting = SystemNotificationRecipientSetting::for(SystemNotificationKey::CompanyAssociateIlsCoverage);
    $setting->update([
        'is_active' => true,
        'notification_emails' => ['destino@tudrencasa.com'],
        'notification_phones' => [],
    ]);

    (new NotifyCompanyAssociateIlsCoverageConfirmedJob((int) $associate->getKey()))->handle();

    Mail::assertSent(CompanyAssociateIlsCoverageConfirmedMail::class, 1);
});
