<?php

declare(strict_types=1);

namespace App\Support\Operations\PatientWhatsApp;

use App\Models\ServiceOrderPatientNotification as Notification;
use Illuminate\Support\Str;

/**
 * Interpreta la respuesta del paciente de forma estricta: solo SI o NO.
 *
 * Tolera mayúsculas, acentos, espacios, signos y emojis alrededor («Sí.», « no », «SI 👍»),
 * pero no frases («sí, gracias», «no sé»): eso no es una respuesta y se le pide de nuevo.
 */
final class PatientReplyParser
{
    /**
     * @return 'SI'|'NO'|null
     */
    public static function parse(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        $letters = preg_replace('/[^a-z]/', '', Str::lower(Str::ascii(trim($text)))) ?? '';

        return match ($letters) {
            'si' => Notification::ANSWER_YES,
            'no' => Notification::ANSWER_NO,
            default => null,
        };
    }
}
