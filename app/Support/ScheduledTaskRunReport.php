<?php

declare(strict_types=1);

namespace App\Support;

use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ScheduledTaskRunReport
{
    public const SUMMARY_PHONE = '04127018390';

    private const SUMMARY_IMAGE = 'images-whatsapp/integracorp.png';

    private static bool $active = false;

    private static string $taskTitle = '';

    /** @var array<string, int|float|string> */
    private static array $metrics = [];

    /** @var array<string, int> */
    private static array $failures = [];

    private static bool $criticalFailure = false;

    private static ?string $criticalMessage = null;

    public static function begin(string $taskTitle): void
    {
        self::$active = true;
        self::$taskTitle = $taskTitle;
        self::$metrics = [];
        self::$failures = [];
        self::$criticalFailure = false;
        self::$criticalMessage = null;
    }

    public static function isActive(): bool
    {
        return self::$active;
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
            NotificationController::notificationBirthday(
                'Equipo Integracorp',
                self::SUMMARY_PHONE,
                self::buildSummaryMessage(),
                self::SUMMARY_IMAGE,
                'image',
            );
        } catch (Throwable $exception) {
            Log::error('ScheduledTaskRunReport: no se pudo enviar resumen por WhatsApp.', [
                'task' => self::$taskTitle,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @return array{
     *     taskTitle: string,
     *     metrics: array<string, int|float|string>,
     *     failures: array<string, int>,
     *     criticalFailure: bool,
     *     criticalMessage: string|null
     * }
     */
    public static function snapshotForTesting(): array
    {
        return [
            'taskTitle' => self::$taskTitle,
            'metrics' => self::$metrics,
            'failures' => self::$failures,
            'criticalFailure' => self::$criticalFailure,
            'criticalMessage' => self::$criticalMessage,
        ];
    }

    public static function summaryPreviewForTesting(): string
    {
        return self::buildSummaryMessage();
    }

    private static function buildSummaryMessage(): string
    {
        $lines = [
            '📋 *Resumen: '.self::$taskTitle.'*',
            '📅 '.now()->timezone(config('app.timezone'))->format('d/m/Y H:i'),
            '',
        ];

        if (self::$criticalFailure) {
            $lines[] = '⚠️ *La ejecución terminó con error crítico.*';
            $lines[] = self::$criticalMessage ?? 'Error no especificado.';
            $lines[] = '';
        }

        $lines[] = '*Resultados*';

        if (self::$metrics === []) {
            $lines[] = '• Sin métricas registradas.';
        } else {
            foreach (self::$metrics as $label => $value) {
                $lines[] = '✅ '.$label.': '.$value;
            }
        }

        $totalFailures = array_sum(self::$failures);
        $lines[] = '';
        $lines[] = '❌ *Fallas:* '.$totalFailures;

        if ($totalFailures > 0) {
            foreach (self::$failures as $category => $count) {
                $lines[] = '• '.$category.': '.$count;
            }
        } else {
            $lines[] = '• Sin fallas registradas.';
        }

        return implode("\n", $lines);
    }
}
