<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotificationMasiveMailBirthday extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $name;
    public $content;
    public $file;

    /**
     * Create a new message instance.
     */
    public function __construct($name, $content, $file)
    {
        $this->name = $name;
        $this->content = $content;
        $this->file = $file;
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Feliz Cumpleaños Sr(a). ' . $this->name
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mails.cumpleanos-email',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}