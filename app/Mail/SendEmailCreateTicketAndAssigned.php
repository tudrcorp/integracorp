<?php

declare(strict_types=1);

namespace App\Mail;

use App\Exceptions\HelpdeskTicketMailException;
use App\Models\HelpDesk;
use App\Models\RrhhColaborador;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class SendEmailCreateTicketAndAssigned extends Mailable implements ShouldQueue, ShouldQueueAfterCommit
{
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    /**
     * @param  HelpDesk  $helpDesk  Ticket persistido (con relaciones opcionales).
     * @param  RrhhColaborador|null  $emailRecipientColaborador  Destinatario del saludo (uno de los asignados).
     */
    public function __construct(
        public readonly HelpDesk $helpDesk,
        public readonly ?RrhhColaborador $emailRecipientColaborador = null,
    ) {
        if (! $this->helpDesk->exists) {
            throw HelpdeskTicketMailException::ticketNotPersisted();
        }

        $this->helpDesk->loadMissing('rrhhColaboradores');
    }

    /**
     * Crea el mailable a partir del modelo o del id (consulta con relaciones).
     *
     * @throws HelpdeskTicketMailException
     */
    public static function fromTicket(HelpDesk|int $ticket, ?RrhhColaborador $emailRecipientColaborador = null): self
    {
        if ($ticket instanceof HelpDesk) {
            if (! $ticket->exists) {
                throw HelpdeskTicketMailException::ticketNotPersisted();
            }

            return new self($ticket, $emailRecipientColaborador);
        }

        $model = HelpDesk::query()
            ->with(['rrhhColaboradores'])
            ->find($ticket);

        if ($model === null) {
            throw HelpdeskTicketMailException::ticketNotFound($ticket);
        }

        return new self($model, $emailRecipientColaborador);
    }

    public function envelope(): Envelope
    {
        $reference = $this->resolveTicketReference();

        return new Envelope(
            subject: sprintf('[%s] Nuevo ticket de soporte asignado — %s', config('app.name'), $reference),
            tags: [
                'helpdesk',
                'ticket:'.$this->helpDesk->getKey(),
            ],
            metadata: [
                'help_desk_id' => (string) $this->helpDesk->getKey(),
            ],
        );
    }

    public function content(): Content
    {
        try {
            return new Content(
                view: 'mails.helpdesk-ticket-assigned',
                with: $this->buildViewData(),
            );
        } catch (Throwable $e) {
            throw HelpdeskTicketMailException::contentBuildFailed($e);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function buildViewData(): array
    {
        $description = (string) ($this->helpDesk->description ?? '');
        $description = Str::of($description)->trim()->limit(8000)->toString();

        $recipient = $this->emailRecipientColaborador;
        $assigneeName = $recipient !== null
            ? (string) ($recipient->fullName ?? $recipient->emailCorporativo ?? '')
            : '';

        return [
            'ticketReference' => $this->resolveTicketReference(),
            'priority' => (string) ($this->helpDesk->priority ?? '—'),
            'status' => (string) ($this->helpDesk->status ?? '—'),
            'createdBy' => (string) ($this->helpDesk->created_by ?? ''),
            'description' => $description !== '' ? $description : '—',
            'assigneeName' => $assigneeName,
        ];
    }

    private function resolveTicketReference(): string
    {
        $code = $this->helpDesk->getAttribute('code');

        if (filled($code)) {
            return (string) $code;
        }

        return 'TKT-'.str_pad((string) $this->helpDesk->getKey(), 6, '0', STR_PAD_LEFT);
    }

    /**
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }

    public function failed(?Throwable $e): void
    {
        Log::error('Fallo definitivo al enviar correo de ticket helpdesk (cola agotada).', [
            'exception' => $e?->getMessage(),
            'help_desk_id' => $this->helpDesk->getKey(),
            'trace' => $e?->getTraceAsString(),
        ]);
    }
}
