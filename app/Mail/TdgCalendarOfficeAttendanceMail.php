<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TdgCalendarOfficeAttendanceMail extends Mailable
{
    use SerializesModels;

    /**
     * @param  array{
     *     colaborador_name: string,
     *     month_label: string,
     *     is_update: bool,
     *     assignments: list<array{
     *         date: string,
     *         date_label: string,
     *         weekday_label: string,
     *         office_label: string
     *     }>
     * }  $payload
     */
    public function __construct(
        public array $payload,
        public string $recipientEmail,
    ) {}

    public function envelope(): Envelope
    {
        $monthLabel = $this->payload['month_label'];
        $subject = ($this->payload['is_update'] ?? false)
            ? "Actualización de tu asistencia a oficina · {$monthLabel}"
            : "Tu asistencia a oficina · {$monthLabel}";

        return new Envelope(
            from: new Address((string) config('mail.from.address'), (string) config('mail.from.name')),
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mails.tdg-calendar-office-attendance',
            with: [
                'colaboradorName' => $this->payload['colaborador_name'],
                'monthLabel' => $this->payload['month_label'],
                'isUpdate' => (bool) ($this->payload['is_update'] ?? false),
                'assignments' => $this->payload['assignments'],
            ],
        );
    }

    /**
     * @return array<int, mixed>
     */
    public function attachments(): array
    {
        return [];
    }
}
