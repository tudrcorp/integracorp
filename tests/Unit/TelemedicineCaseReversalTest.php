<?php

declare(strict_types=1);

use App\Enums\SystemNotificationKey;
use App\Filament\Telemedicina\Resources\TelemedicineCases\Actions\ReverseTelemedicineCaseAction;
use App\Jobs\NotifyTelemedicineCaseReversalJob;
use App\Mail\TelemedicineCaseReversedMail;
use App\Models\SystemNotificationRecipientSetting;
use App\Models\TelemedicineCase;
use App\Support\SystemNotificationRecipients;
use App\Support\Telemedicine\TelemedicineCaseReversalNotificationMessage;
use App\Support\Telemedicine\TelemedicineCaseReversalNotifier;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

it('expone la notificacion de reverso en el centro de notificaciones', function (): void {
    expect(SystemNotificationKey::TelemedicineCaseReversal->label())
        ->toBe('Reverso de casos de telemedicina')
        ->and(SystemNotificationKey::TelemedicineCaseReversal->value)
        ->toBe('telemedicine_case_reversal')
        ->and(SystemNotificationKey::managed())
        ->toContain(SystemNotificationKey::TelemedicineCaseReversal)
        ->and(SystemNotificationKey::TelemedicineCaseReversal->pausesScheduledTask())
        ->toBeFalse();

    $enum = file_get_contents(dirname(__DIR__, 2).'/app/Enums/SystemNotificationKey.php');

    expect($enum)
        ->toContain("case TelemedicineCaseReversal = 'telemedicine_case_reversal'")
        ->toContain('Reverso de casos de telemedicina');
});

it('persiste el setting de destinatarios del reverso cuando existe la tabla', function (): void {
    if (! Schema::hasTable('system_notification_recipient_settings')) {
        $this->markTestSkipped('Tabla system_notification_recipient_settings no disponible.');
    }

    $setting = SystemNotificationRecipientSetting::for(SystemNotificationKey::TelemedicineCaseReversal);

    expect($setting->notification_key)->toBe(SystemNotificationKey::TelemedicineCaseReversal)
        ->and(SystemNotificationRecipients::isActive(SystemNotificationKey::TelemedicineCaseReversal))->toBeBool();
});

it('construye mensaje whatsapp y email resaltando la nota del medico', function (): void {
    $payload = [
        'case_id' => 10,
        'case_code' => 'TM-2026-001',
        'patient_name' => 'Paciente Demo',
        'patient_phone' => '04141234567',
        'patient_address' => 'Av. Principal',
        'patient_age' => '35',
        'patient_sex' => 'MASCULINO',
        'reason' => 'Dolor abdominal',
        'status' => 'ASIGNADO',
        'managed_by' => 'TDG',
        'assigned_by' => 'Analista Demo',
        'doctor_name' => 'Dra. Pérez',
        'priority' => 'URGENCIA',
        'reversed_by' => 'Dra. Pérez',
        'reversal_note' => 'No corresponde especialidad, solicitar reasignación a pediatría.',
        'reversed_at' => '22/07/2026 12:00:00',
        'telemedicine_patient_id' => 5,
    ];

    $whatsapp = TelemedicineCaseReversalNotificationMessage::whatsappBody($payload);

    expect($whatsapp)
        ->toContain('REVERSO DE CASO')
        ->toContain('TM-2026-001')
        ->toContain('Paciente Demo')
        ->toContain('Dra. Pérez')
        ->toContain('NOTA / OBSERVACIÓN DEL MÉDICO')
        ->toContain('No corresponde especialidad, solicitar reasignación a pediatría.')
        ->toContain('ACCIÓN REQUERIDA');

    expect(TelemedicineCaseReversalNotificationMessage::emailSubject($payload))
        ->toContain('TM-2026-001')
        ->toContain('Reverso de caso');

    $mail = new TelemedicineCaseReversedMail(
        emailPayload: TelemedicineCaseReversalNotificationMessage::emailPayload($payload),
        recipientEmail: 'analista@example.com',
        subjectLine: TelemedicineCaseReversalNotificationMessage::emailSubject($payload),
    );

    expect($mail->envelope()->subject)->toContain('TM-2026-001')
        ->and($mail->content()->view)->toBe('mails.telemedicine-case-reversed');
});

it('el servicio elimina el caso y el notificador despacha el job asincrono', function (): void {
    $service = file_get_contents(dirname(__DIR__, 2).'/app/Services/TelemedicineCaseReversalService.php');
    $notifier = file_get_contents(dirname(__DIR__, 2).'/app/Support/Telemedicine/TelemedicineCaseReversalNotifier.php');
    $job = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/NotifyTelemedicineCaseReversalJob.php');
    $action = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineCases/Actions/ReverseTelemedicineCaseAction.php');
    $table = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineCases/Tables/TelemedicineCasesTable.php');
    $view = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineCases/Pages/ViewTelemedicineCase.php');
    $dash = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Widgets/TelemedicineCaseTableDash.php');
    $template = file_get_contents(dirname(__DIR__, 2).'/resources/views/mails/telemedicine-case-reversed.blade.php');

    expect($service)
        ->toContain('TelemedicineCaseReversalNotifier::notify')
        ->toContain('$case->delete()')
        ->toContain('reversal_note')
        ->toContain('EN SEGUIMIENTO')
        ->toContain('ALTA MEDICA');

    expect($notifier)
        ->toContain('afterResponse()')
        ->toContain("onConnection('sync')")
        ->toContain('NotifyTelemedicineCaseReversalJob::dispatch');

    expect($job)
        ->toContain('SystemNotificationKey::TelemedicineCaseReversal')
        ->toContain('SystemNotificationRecipients::emails')
        ->toContain('SystemNotificationRecipients::phones')
        ->toContain('SendNotificacionWhatsApp::dispatchSync')
        ->toContain('TelemedicineCaseReversedMail')
        ->toContain('ShouldQueue');

    expect($action)
        ->toContain('reversal_note')
        ->toContain('TelemedicineCaseReversalService')
        ->toContain('Reversar caso')
        ->toContain('EN SEGUIMIENTO')
        ->toContain('canReverse');

    expect($table)->toContain('ReverseTelemedicineCaseAction::make()');
    expect($view)->toContain('ReverseTelemedicineCaseAction::make');
    expect($dash)->toContain('ReverseTelemedicineCaseAction::make');

    expect($template)
        ->toContain('Nota / observación del médico')
        ->toContain('$reversal_note')
        ->toContain('eliminado');
});

it('no permite reversar casos en seguimiento o con alta medica', function (): void {
    $followUp = new TelemedicineCase(['status' => 'EN SEGUIMIENTO']);
    $discharged = new TelemedicineCase(['status' => 'ALTA MEDICA']);
    $assigned = new TelemedicineCase(['status' => 'ASIGNADO']);
    $initial = new TelemedicineCase(['status' => 'CONSULTA INICIAL']);

    expect(ReverseTelemedicineCaseAction::canReverse($followUp))->toBeFalse()
        ->and(ReverseTelemedicineCaseAction::canReverse($discharged))->toBeFalse()
        ->and(ReverseTelemedicineCaseAction::canReverse($assigned))->toBeTrue()
        ->and(ReverseTelemedicineCaseAction::canReverse($initial))->toBeTrue()
        ->and(ReverseTelemedicineCaseAction::canReverse(null))->toBeFalse();
});

it('el notificador despacha el job con el payload del reverso', function (): void {
    Bus::fake();

    $payload = [
        'case_code' => 'TM-999',
        'reversal_note' => 'Motivo de prueba del reverso.',
    ];

    TelemedicineCaseReversalNotifier::notify($payload);

    Bus::assertDispatched(NotifyTelemedicineCaseReversalJob::class, function (NotifyTelemedicineCaseReversalJob $job) use ($payload): bool {
        return ($job->payload['case_code'] ?? null) === $payload['case_code']
            && ($job->payload['reversal_note'] ?? null) === $payload['reversal_note'];
    });
});
