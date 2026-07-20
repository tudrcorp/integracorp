<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\SystemNotificationKey;
use App\Http\Controllers\NotificationController;
use App\Mail\ScheduledTaskRunReportMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class ScheduledTaskRunReport
{
    public const SUMMARY_PHONE = '04127018390';

    private const SUMMARY_IMAGE = WhatsAppBrandImage::RELATIVE_PATH;

    private static bool $active = false;

    private static string $taskTitle = '';

    private static ?string $taskDescription = null;

    /** @var list<string> */
    private static array $readingNotes = [];

    /** @var array<string, int|float|string> */
    private static array $executionDetails = [];

    /** @var array<string, int|float|string> */
    private static array $metrics = [];

    /** @var array<string, int> */
    private static array $failures = [];

    private static bool $criticalFailure = false;

    private static ?string $criticalMessage = null;

    private static ?string $failureFootnote = null;

    /** @var array{relative_path: string, filename: string}|null */
    private static ?array $documentAttachment = null;

    private static ?SystemNotificationKey $notificationKey = null;

    /**
     * @param  list<string>  $readingNotes
     */
    public static function begin(
        string $taskTitle,
        ?string $taskDescription = null,
        array $readingNotes = [],
        ?SystemNotificationKey $notificationKey = null,
    ): void {
        self::$active = true;
        self::$taskTitle = $taskTitle;
        self::$taskDescription = $taskDescription;
        self::$readingNotes = $readingNotes;
        self::$executionDetails = [];
        self::$metrics = [];
        self::$failures = [];
        self::$criticalFailure = false;
        self::$criticalMessage = null;
        self::$failureFootnote = null;
        self::$documentAttachment = null;
        self::$notificationKey = $notificationKey;
    }

    public static function isActive(): bool
    {
        return self::$active;
    }

    public static function addExecutionDetail(string $label, int|float|string $value): void
    {
        if (! self::$active) {
            return;
        }

        self::$executionDetails[$label] = $value;
    }

    public static function setFailureFootnote(string $footnote): void
    {
        if (! self::$active) {
            return;
        }

        self::$failureFootnote = $footnote;
    }

    public static function setDocumentAttachment(string $publicRelativePath, string $filename): void
    {
        if (! self::$active) {
            return;
        }

        self::$documentAttachment = [
            'relative_path' => ltrim($publicRelativePath, '/'),
            'filename' => $filename,
        ];
    }

    public static function addMetric(string $label, int|float|string $value): void
    {
        if (! self::$active) {
            return;
        }

        self::$metrics[$label] = $value;
    }

    public static function incrementMetric(string $label, int $amount = 1): void
    {
        if (! self::$active) {
            return;
        }

        $current = self::$metrics[$label] ?? 0;
        self::$metrics[$label] = is_numeric($current)
            ? ((int) $current) + $amount
            : $amount;
    }

    public static function recordFailure(string $category): void
    {
        if (! self::$active) {
            return;
        }

        self::$failures[$category] = (self::$failures[$category] ?? 0) + 1;
    }

    public static function recordCriticalFailure(Throwable $exception): void
    {
        if (! self::$active) {
            return;
        }

        self::$criticalFailure = true;
        self::$criticalMessage = $exception->getMessage();
    }

    public static function finishAndNotify(): void
    {
        if (! self::$active) {
            return;
        }

        self::$active = false;

        try {
            $fullMessage = self::buildSummaryMessage();
            $imageCaption = self::buildWhatsAppImageCaption();
            $phones = self::recipientPhones();
            $emails = self::recipientEmails();

            if ($phones === [] && $emails === []) {
                Log::warning('ScheduledTaskRunReport: sin destinatarios para el resumen.', [
                    'task' => self::$taskTitle,
                    'notification_key' => self::$notificationKey?->value,
                ]);

                return;
            }

            foreach ($phones as $phone) {
                self::notifySummaryToPhone($phone, $fullMessage, $imageCaption);
            }

            foreach ($emails as $email) {
                self::notifySummaryToEmail($email, $fullMessage);
            }
        } catch (Throwable $exception) {
            Log::error('ScheduledTaskRunReport: no se pudo enviar resumen.', [
                'task' => self::$taskTitle,
                'message' => $exception->getMessage(),
            ]);
        } finally {
            self::$notificationKey = null;
        }
    }

    /**
     * @return list<string>
     */
    private static function recipientPhones(): array
    {
        if (self::$notificationKey !== null) {
            return SystemNotificationRecipients::phones(self::$notificationKey);
        }

        return ScheduledNotificationPhones::all();
    }

    /**
     * @return list<string>
     */
    private static function recipientEmails(): array
    {
        if (self::$notificationKey === null) {
            return [];
        }

        return SystemNotificationRecipients::emails(self::$notificationKey);
    }

    private static function notifySummaryToEmail(string $email, string $fullMessage): void
    {
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Log::warning('ScheduledTaskRunReport: correo inválido para resumen.', [
                'task' => self::$taskTitle,
                'email' => $email,
            ]);

            return;
        }

        try {
            Mail::to($email)->send(new ScheduledTaskRunReportMail(
                recipientEmail: $email,
                taskTitle: self::$taskTitle,
                summaryBody: $fullMessage,
                attachmentFilename: self::$documentAttachment['filename'] ?? null,
                attachmentRelativePath: self::$documentAttachment['relative_path'] ?? null,
            ));
        } catch (Throwable $exception) {
            Log::error('ScheduledTaskRunReport: no se pudo enviar resumen por email.', [
                'task' => self::$taskTitle,
                'email' => $email,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private static function notifySummaryToPhone(string $phone, string $fullMessage, string $imageCaption): void
    {
        self::sendSummaryWithBrandImage($phone, $imageCaption);
        NotificationController::sendWhatsAppChat($phone, 'Equipo Integracorp', $fullMessage);

        if (self::$documentAttachment === null || self::$criticalFailure) {
            return;
        }

        $documentSent = NotificationController::sendWhatsAppDocument(
            $phone,
            '📎 *Archivo adjunto:* '.self::$documentAttachment['filename'],
            self::$documentAttachment['relative_path'],
            self::$documentAttachment['filename'],
        );

        if ($documentSent) {
            return;
        }

        Log::error('ScheduledTaskRunReport: no se pudo adjuntar el archivo por WhatsApp.', [
            'task' => self::$taskTitle,
            'file' => self::$documentAttachment['filename'],
            'phone' => $phone,
        ]);
    }

    private static function sendSummaryWithBrandImage(string $phone, string $message): void
    {
        NotificationController::notificationBirthday(
            'Equipo Integracorp',
            $phone,
            $message,
            self::SUMMARY_IMAGE,
            'image',
        );
    }

    /**
     * @return array{
     *     taskTitle: string,
     *     taskDescription: string|null,
     *     readingNotes: list<string>,
     *     executionDetails: array<string, int|float|string>,
     *     metrics: array<string, int|float|string>,
     *     failures: array<string, int>,
     *     criticalFailure: bool,
     *     criticalMessage: string|null,
     *     failureFootnote: string|null
     * }
     */
    public static function snapshotForTesting(): array
    {
        return [
            'taskTitle' => self::$taskTitle,
            'taskDescription' => self::$taskDescription,
            'readingNotes' => self::$readingNotes,
            'executionDetails' => self::$executionDetails,
            'metrics' => self::$metrics,
            'failures' => self::$failures,
            'criticalFailure' => self::$criticalFailure,
            'criticalMessage' => self::$criticalMessage,
            'failureFootnote' => self::$failureFootnote,
            'documentAttachment' => self::$documentAttachment,
        ];
    }

    public static function summaryPreviewForTesting(): string
    {
        return self::buildSummaryMessage();
    }

    private static function buildSummaryMessage(): string
    {
        $lines = RunReportMessageFormatter::titleLines('📋', 'Resumen: '.self::$taskTitle);

        if (filled(self::$taskDescription)) {
            $lines = array_merge($lines, RunReportMessageFormatter::bulletSection('Qué hace esta tarea', [
                self::$taskDescription ?? '',
            ]));
        }

        $lines = array_merge($lines, RunReportMessageFormatter::bulletSection('Cómo leer este reporte', self::$readingNotes));
        $lines = array_merge($lines, RunReportMessageFormatter::criticalFailureLines(self::$criticalFailure, self::$criticalMessage));
        $lines = array_merge($lines, RunReportMessageFormatter::configurationSection('Detalle de la ejecución', self::$executionDetails));

        $lines[] = '📊 *Resultados*';

        if (self::$metrics === []) {
            $lines[] = '• Sin métricas registradas.';
        } else {
            foreach (self::$metrics as $label => $value) {
                $lines[] = '✅ '.$label.': '.$value;
            }
        }

        $totalFailures = array_sum(self::$failures);
        $lines[] = '';
        $lines[] = '❌ Fallas registradas: '.$totalFailures;

        if ($totalFailures > 0) {
            foreach (self::$failures as $category => $count) {
                $lines[] = '• '.$category.': '.$count;
            }
        } else {
            $lines[] = '• Sin fallas registradas.';
        }

        if (filled(self::$failureFootnote)) {
            $lines[] = '';
            $lines[] = '_'.self::$failureFootnote.'_';
        }

        return implode("\n", $lines);
    }

    private static function buildWhatsAppImageCaption(): string
    {
        $lines = [
            '📋 *Resumen: '.self::$taskTitle.'*',
            RunReportMessageFormatter::executionTimestamp(),
        ];

        if (self::$criticalFailure) {
            $lines[] = '⚠️ Error crítico en la ejecución.';
        }

        foreach (self::$metrics as $label => $value) {
            $lines[] = '✅ '.$label.': '.$value;
        }

        $totalFailures = array_sum(self::$failures);
        $lines[] = '❌ Fallas registradas: '.$totalFailures;

        if (self::$documentAttachment !== null) {
            $lines[] = '📎 Archivo: '.self::$documentAttachment['filename'];
        }

        $lines[] = '';
        $lines[] = '_Detalle completo en el siguiente mensaje._';

        return RunReportMessageFormatter::truncateForWhatsAppCaption(implode("\n", $lines));
    }
}
