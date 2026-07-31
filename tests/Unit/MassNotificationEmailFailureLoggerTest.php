<?php

declare(strict_types=1);

use App\Jobs\SendNotificationMasiveEmail;
use App\Models\MassNotification;
use App\Support\MassNotificationEmailFailureLogger;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Exception\TransportException;

uses(Tests\TestCase::class);

it('registra un log de error detallado con contexto de correo y excepción', function (): void {
    Log::spy();

    $record = new MassNotification([
        'title' => 'Campaña demo',
        'status' => 'APROBADA',
        'is_sent' => false,
        'channels' => ['email'],
        'email_subject' => 'Asunto de prueba',
    ]);
    $record->id = 99;

    $exception = new TransportException('Connection could not be established with host mail.example.com');
    $exception->appendDebug("<< 421 Service not available\n");

    MassNotificationEmailFailureLogger::log(
        exception: $exception,
        stage: 'mail_send',
        record: $record,
        email: 'destino@example.com',
        dataNotificationId: 15,
        context: [
            'attempt' => 2,
            'max_tries' => 5,
        ],
    );

    Log::shouldHaveReceived('error')
        ->once()
        ->withArgs(function (string $message, array $context): bool {
            expect($message)->toContain('Mass notification email failed')
                ->and($message)->toContain('Connection could not be established')
                ->and($context['stage'])->toBe('mail_send')
                ->and($context['channel'])->toBe('email')
                ->and($context['mass_notification_id'])->toBe(99)
                ->and($context['mass_notification_title'])->toBe('Campaña demo')
                ->and($context['email_subject'])->toBe('Asunto de prueba')
                ->and($context['recipient_email'])->toBe('destino@example.com')
                ->and($context['recipient_email_valid'])->toBeTrue()
                ->and($context['data_notification_id'])->toBe(15)
                ->and($context['exception_class'])->toBe(TransportException::class)
                ->and($context['exception_message'])->toContain('Connection could not be established')
                ->and($context['exception_trace'])->not->toBeEmpty()
                ->and($context['mail_transport_debug'])->toContain('421 Service not available')
                ->and($context['mail_default'])->not->toBeEmpty()
                ->and($context)->toHaveKeys([
                    'mail_from_address',
                    'mailer_transport',
                    'mailer_host',
                    'mailer_port',
                    'mailer_password_configured',
                    'attempt',
                    'max_tries',
                ]);

            return true;
        });
});

it('el job de correo masivo registra fallos de intento y permanentes con el logger', function (): void {
    $jobSrc = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/SendNotificationMasiveEmail.php');
    $viewSrc = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Marketing/Resources/MassNotifications/Pages/ViewMassNotification.php');
    $controllerSrc = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/NotificationController.php');
    $serviceSrc = file_get_contents(dirname(__DIR__, 2).'/app/Services/NotificationMasiveService.php');

    expect($jobSrc)->toContain('MassNotificationEmailFailureLogger::log')
        ->and($jobSrc)->toContain("stage: 'job_attempt'")
        ->and($jobSrc)->toContain("stage: 'job_failed_permanently'")
        ->and($viewSrc)->toContain("stage: 'dispatch_enqueue'")
        ->and($controllerSrc)->toContain("stage: 'test_email'")
        ->and($serviceSrc)->toContain('Mass notification email: aceptado por SMTP')
        ->and($serviceSrc)->not->toContain('sleep(5)')
        ->and(class_exists(SendNotificationMasiveEmail::class))->toBeTrue()
        ->and(class_exists(MassNotificationEmailFailureLogger::class))->toBeTrue();
});
