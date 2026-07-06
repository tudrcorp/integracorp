<?php

namespace App\Support;

class MassNotificationEmailSubject
{
    public static function resolve(mixed $record, string $default = 'Notificación'): string
    {
        $subject = data_get($record, 'email_subject');

        return filled($subject) ? (string) $subject : $default;
    }
}
