<?php

declare(strict_types=1);

namespace App\Mail;

use App\Support\GuiaChat\GuiaChatFeedbackType;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GuiaChatFeedbackMail extends Mailable
{
    use SerializesModels;

    /**
     * @param  array<string, string>  $details
     */
    public function __construct(
        public string $subjectLine,
        public string $headline,
        public string $intro,
        public array $details,
        public string $accentColor,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mails.guia-chat-feedback',
            with: [
                'headline' => $this->headline,
                'intro' => $this->intro,
                'details' => $this->details,
                'accentColor' => $this->accentColor,
            ],
        );
    }

    public static function presentationForType(string $type): array
    {
        $enum = GuiaChatFeedbackType::tryFromString($type);

        return match ($enum) {
            GuiaChatFeedbackType::ServiceSuggestion => [
                'subject' => 'GUIA-CHAT — Nueva sugerencia de mejora',
                'headline' => 'Nueva sugerencia de mejora',
                'intro' => 'Se recibió una sugerencia desde el chat público GUIA-CHAT.',
                'accent' => '#6366f1',
            ],
            GuiaChatFeedbackType::GuiaChatBug => [
                'subject' => 'GUIA-CHAT — Reporte de falla del asistente',
                'headline' => 'Reporte de falla GUIA-CHAT',
                'intro' => 'Un usuario reportó una incidencia en el asistente GUIA-CHAT.',
                'accent' => '#f59e0b',
            ],
            GuiaChatFeedbackType::IntegracorpBug => [
                'subject' => 'GUIA-CHAT — Reporte de falla del sistema INTEGRACORP',
                'headline' => 'Reporte de falla INTEGRACORP',
                'intro' => 'Un usuario reportó una incidencia en el sistema INTEGRACORP.',
                'accent' => '#ef4444',
            ],
            default => [
                'subject' => 'GUIA-CHAT — Nuevo registro',
                'headline' => 'Nuevo registro GUIA-CHAT',
                'intro' => 'Se recibió un nuevo mensaje desde el chat público GUIA-CHAT.',
                'accent' => '#0d9488',
            ],
        };
    }
}
