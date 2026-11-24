<?php

declare(strict_types=1);

use App\Jobs\SendTelemedicineConsultationDocumentsEmail;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Exception\UnexpectedResponseException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

uses(Tests\TestCase::class);

/**
 * Correo de los documentos de la consulta en su propio trabajo: un rechazo temporal de Gmail
 * se reintenta sin reportarse y sin repetir los WhatsApp. No escribe en la base; crea un PDF
 * temporal en public/storage/telemedicina-doc y lo borra al terminar.
 */
beforeEach(function (): void {
    $this->docsDir = public_path('storage/telemedicina-doc');
    $this->createdDir = ! is_dir($this->docsDir);

    if ($this->createdDir) {
        mkdir($this->docsDir, 0775, true);
    }

    $this->pdf = 'qa-correo-consulta-'.bin2hex(random_bytes(6)).'.pdf';
    file_put_contents($this->docsDir.'/'.$this->pdf, '%PDF-1.4 QA');
});

afterEach(function (): void {
    @unlink($this->docsDir.'/'.$this->pdf);

    if ($this->createdDir) {
        @rmdir($this->docsDir);
    }

    Mail::purge('qa_smtp_falso');
});

/**
 * Transporte de correo que responde como el servidor SMTP indicado.
 */
function useFakeSmtp(int $code, string $message): void
{
    Mail::extend('qa_smtp_falso', fn () => new class($code, $message) extends AbstractTransport
    {
        public function __construct(private int $code, private string $text)
        {
            parent::__construct();
        }

        protected function doSend(SentMessage $message): void
        {
            throw new UnexpectedResponseException($this->text, $this->code);
        }

        public function __toString(): string
        {
            return 'qa-smtp-falso';
        }
    });

    config([
        'mail.default' => 'qa_smtp_falso',
        'mail.mailers.qa_smtp_falso' => ['transport' => 'qa_smtp_falso'],
        'mail.from' => ['address' => 'qa@tudrencasa.com', 'name' => 'QA'],
    ]);
    Mail::purge('qa_smtp_falso');
}

it('distingue un rechazo temporal del correo de uno definitivo', function (): void {
    expect(SendTelemedicineConsultationDocumentsEmail::isTransient(new UnexpectedResponseException('421 Connection expired', 421)))->toBeTrue()
        ->and(SendTelemedicineConsultationDocumentsEmail::isTransient(new UnexpectedResponseException('450 mailbox busy', 450)))->toBeTrue()
        ->and(SendTelemedicineConsultationDocumentsEmail::isTransient(new TransportException('Connection to "smtp.gmail.com:587" has been closed unexpectedly.')))->toBeTrue()
        ->and(SendTelemedicineConsultationDocumentsEmail::isTransient(new UnexpectedResponseException('550 Daily user sending limit exceeded', 550)))->toBeFalse()
        ->and(SendTelemedicineConsultationDocumentsEmail::isTransient(new TransportException('Failed to authenticate', 535)))->toBeFalse();
});

it('un 421 de Gmail se reintenta en un minuto sin lanzar error', function (): void {
    useFakeSmtp(421, 'Expected response code "250" but got code "421", with message "421-4.7.0 Connection expired, try reconnecting."');

    $job = (new SendTelemedicineConsultationDocumentsEmail('paciente.qa@example.com', 'PACIENTE QA', [$this->pdf], 10, null))->withFakeQueueInteractions();
    $job->handle();

    expect($job->job->isReleased())->toBeTrue()
        ->and($job->job->releaseDelay)->toBe(60);
});

it('en el último intento o ante un rechazo definitivo el error sí se lanza', function (): void {
    useFakeSmtp(421, 'Connection expired');
    $last = (new SendTelemedicineConsultationDocumentsEmail('paciente.qa@example.com', 'PACIENTE QA', [$this->pdf]))->withFakeQueueInteractions();
    $last->job->attempts = 4;

    expect(fn () => $last->handle())->toThrow(UnexpectedResponseException::class);

    useFakeSmtp(550, 'Daily user sending limit exceeded');
    $permanent = (new SendTelemedicineConsultationDocumentsEmail('paciente.qa@example.com', 'PACIENTE QA', [$this->pdf]))->withFakeQueueInteractions();

    expect(fn () => $permanent->handle())->toThrow(UnexpectedResponseException::class);
});

it('el correo exitoso no se reintenta y sin PDFs no envía nada', function (): void {
    Mail::fake();

    $job = (new SendTelemedicineConsultationDocumentsEmail('paciente.qa@example.com', 'PACIENTE QA', [$this->pdf]))->withFakeQueueInteractions();
    $job->handle();
    expect($job->job->isReleased())->toBeFalse();
    expect(Mail::sent(App\Mail\TelemedicineConsultationDocumentsMail::class)->count())->toBe(1);

    $empty = (new SendTelemedicineConsultationDocumentsEmail('paciente.qa@example.com', 'PACIENTE QA', ['no-existe.pdf']))->withFakeQueueInteractions();
    $empty->handle();
    expect(Mail::sent(App\Mail\TelemedicineConsultationDocumentsMail::class)->count())->toBe(1);
});

it('el trabajo de los documentos envía el WhatsApp una vez y deja el correo en su propio trabajo', function (): void {
    $service = (string) file_get_contents(dirname(__DIR__, 2).'/app/Services/TelemedicineConsultationDocumentsNotificationService.php');
    $notify = substr($service, (int) strpos($service, 'public static function notify('), 1400);

    expect($notify)
        ->toContain('self::sendWhatsAppDocuments(')
        ->toContain('SendTelemedicineConsultationDocumentsEmail::dispatch(')
        ->not->toContain('self::sendEmailDocuments(');
});
