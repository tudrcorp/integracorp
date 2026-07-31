<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\MassNotification;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;

class MassNotificationEmailFailureLogger
{
    /**
     * @param  array<string, mixed>  $context
     */
    public static function log(
        Throwable $exception,
        string $stage,
        ?MassNotification $record = null,
        ?string $email = null,
        ?int $dataNotificationId = null,
        array $context = [],
    ): void {
        $payload = array_merge(
            self::baseContext($stage, $record, $email, $dataNotificationId),
            self::exceptionContext($exception),
            self::mailerContext(),
            $context,
        );

        Log::error('Mass notification email failed: '.$exception->getMessage(), $payload);
    }

    /**
     * @return array<string, mixed>
     */
    public static function baseContext(
        string $stage,
        ?MassNotification $record,
        ?string $email,
        ?int $dataNotificationId,
    ): array {
        return [
            'stage' => $stage,
            'channel' => 'email',
            'data_notification_id' => $dataNotificationId,
            'recipient_email' => $email,
            'recipient_email_valid' => filled($email) ? filter_var($email, FILTER_VALIDATE_EMAIL) !== false : false,
            'mass_notification_id' => $record?->id,
            'mass_notification_title' => $record?->title,
            'mass_notification_status' => $record?->status,
            'mass_notification_is_sent' => $record?->is_sent,
            'mass_notification_channels' => $record?->channels,
            'email_subject' => $record ? MassNotificationEmailSubject::resolve($record) : null,
            'date_programed' => $record?->date_programed?->toDateTimeString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function exceptionContext(Throwable $exception): array
    {
        $previous = $exception->getPrevious();
        $chain = [];
        $current = $previous;
        $depth = 0;

        while ($current instanceof Throwable && $depth < 5) {
            $chain[] = [
                'class' => $current::class,
                'message' => $current->getMessage(),
                'code' => $current->getCode(),
                'file' => $current->getFile(),
                'line' => $current->getLine(),
            ];
            $current = $current->getPrevious();
            $depth++;
        }

        $context = [
            'exception_class' => $exception::class,
            'exception_message' => $exception->getMessage(),
            'exception_code' => $exception->getCode(),
            'exception_file' => $exception->getFile(),
            'exception_line' => $exception->getLine(),
            'exception_trace' => $exception->getTraceAsString(),
            'previous_exceptions' => $chain,
        ];

        if ($exception instanceof TransportExceptionInterface) {
            $context['mail_transport_debug'] = $exception->getDebug();
        }

        return $context;
    }

    /**
     * @return array<string, mixed>
     */
    public static function mailerContext(): array
    {
        $defaultMailer = (string) config('mail.default');
        $mailerConfig = (array) config("mail.mailers.{$defaultMailer}", []);

        return [
            'mail_default' => $defaultMailer,
            'mail_from_address' => config('mail.from.address'),
            'mail_from_name' => config('mail.from.name'),
            'mailer_transport' => $mailerConfig['transport'] ?? null,
            'mailer_host' => $mailerConfig['host'] ?? null,
            'mailer_port' => $mailerConfig['port'] ?? null,
            'mailer_scheme' => $mailerConfig['scheme'] ?? null,
            'mailer_username' => filled($mailerConfig['username'] ?? null) ? '***configured***' : null,
            'mailer_password_configured' => filled($mailerConfig['password'] ?? null),
            'mailer_timeout' => $mailerConfig['timeout'] ?? null,
        ];
    }
}
