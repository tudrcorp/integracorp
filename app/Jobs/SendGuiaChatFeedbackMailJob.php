<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\GuiaChatFeedbackMail;
use App\Models\GuiaChatFeedback;
use App\Support\GuiaChat\GuiaChatFeedbackNotifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendGuiaChatFeedbackMailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60, 120, 300];
    }

    public function __construct(public int $feedbackId) {}

    public function handle(): void
    {
        $feedback = GuiaChatFeedback::query()->find($this->feedbackId);

        if (! $feedback instanceof GuiaChatFeedback) {
            Log::warning('GUIA-CHAT: feedback no encontrado para enviar correo.', ['feedback_id' => $this->feedbackId]);

            return;
        }

        $presentation = GuiaChatFeedbackMail::presentationForType((string) $feedback->type);

        try {
            Mail::to(GuiaChatFeedbackNotifier::emailTo())
                ->cc(GuiaChatFeedbackNotifier::emailCc())
                ->send(new GuiaChatFeedbackMail(
                    subjectLine: $presentation['subject'],
                    headline: $presentation['headline'],
                    intro: $presentation['intro'],
                    details: GuiaChatFeedbackNotifier::buildDetails($feedback),
                    accentColor: $presentation['accent'],
                ));
        } catch (Throwable $exception) {
            Log::error('GUIA-CHAT: fallo al enviar correo de feedback.', [
                'feedback_id' => $this->feedbackId,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
