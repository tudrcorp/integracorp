<?php

declare(strict_types=1);

namespace App\Support;

use App\Http\Controllers\NotificationController;
use App\Http\Controllers\UtilsController;
use App\Jobs\WhatsAppBirthdayNotification;
use Illuminate\Support\Facades\Log;
use Throwable;

final class BirthdayNotificationRunReport
{
    public const SUMMARY_PHONE = '04127018390';

    private const SUMMARY_IMAGE = 'images-whatsapp/integracorp.png';

    private const CONTROL_PHONE = '04143027250';

    /** @var list<string> */
    private const ALL_GROUPS = [
        'agentes',
        'agencias',
        'afiliaciones',
        'afiliaciones_corporativas',
        'colaboradores',
        'proveedores',
    ];

    /** @var array<string, string> */
    private const GROUP_LABELS = [
        'agentes' => 'Agentes',
        'agencias' => 'Agencias',
        'afiliaciones' => 'Afiliaciones individuales',
        'afiliaciones corporativas' => 'Afiliaciones corporativas',
        'afiliaciones_corporativas' => 'Afiliaciones corporativas',
        'colaboradores' => 'Colaboradores',
        'proveedores' => 'Proveedores',
    ];

    private static bool $active = false;

    private static ?string $currentGroup = null;

    /** @var array<string, array{whatsapp: int, email: int, failures: array<string, int>}> */
    private static array $stats = [];

    private static bool $criticalFailure = false;

    private static ?string $criticalMessage = null;

    public static function begin(): void
    {
        self::$active = true;
        self::$stats = [];

        foreach (self::ALL_GROUPS as $group) {
            self::$stats[$group] = [
                'whatsapp' => 0,
                'email' => 0,
                'failures' => [],
            ];
        }

        self::$currentGroup = null;
        self::$criticalFailure = false;
        self::$criticalMessage = null;
    }

    public static function setCurrentGroup(string $group): void
    {
        self::$currentGroup = $group;
    }

    public static function isActive(): bool
    {
        return self::$active;
    }

    public static function queueWhatsApp(
        string $name,
        string $phone,
        string $content,
        string $file,
        string $type,
    ): \Illuminate\Foundation\Bus\PendingDispatch {
        if (self::$active && self::$currentGroup !== null && ! self::isControlPhone($phone)) {
            self::incrementSent(self::$currentGroup, 'whatsapp');
        }

        return WhatsAppBirthdayNotification::dispatch($name, $phone, $content, $file, $type);
    }

    /**
     * @param  callable(): void  $sendMail
     */
    public static function sendBirthdayEmail(
        ?string $email,
        string $name,
        callable $sendMail,
    ): void {
        $group = self::$currentGroup ?? 'general';

        if (blank($email)) {
            UtilsController::notificationFailed('email', $name, $email, null, 'Email es nulo o vacio', $group);

            return;
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            UtilsController::notificationFailed('email', $name, $email, null, 'Email mal escrito o inválido', $group);

            return;
        }

        try {
            $sendMail();

            if (self::$active && self::$currentGroup !== null) {
                self::incrementSent(self::$currentGroup, 'email');
            }
        } catch (Throwable $exception) {
            UtilsController::notificationFailed(
                'email',
                $name,
                $email,
                null,
                'Error al enviar email: '.$exception->getMessage(),
                $group,
            );
        }
    }

    public static function recordFailure(string $channel, string $group, string $message): void
    {
        if (! self::$active) {
            return;
        }

        self::ensureGroup(self::normalizeGroup($group));
        $category = self::failureCategory($channel, $message);
        $normalizedGroup = self::normalizeGroup($group);
        self::$stats[$normalizedGroup]['failures'][$category] = (self::$stats[$normalizedGroup]['failures'][$category] ?? 0) + 1;
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
            Log::error('BirthdayNotificationRunReport: no se pudo enviar resumen por WhatsApp.', [
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @return array<string, array{whatsapp: int, email: int, failures: array<string, int>}>
     */
    public static function statsForTesting(): array
    {
        return self::$stats;
    }

    private static function incrementSent(string $group, string $channel): void
    {
        self::ensureGroup($group);
        $group = self::normalizeGroup($group);
        self::$stats[$group][$channel]++;
    }

    private static function ensureGroup(string $group): void
    {
        $group = self::normalizeGroup($group);

        if (! array_key_exists($group, self::$stats)) {
            self::$stats[$group] = [
                'whatsapp' => 0,
                'email' => 0,
                'failures' => [],
            ];
        }
    }

    private static function normalizeGroup(string $group): string
    {
        return match ($group) {
            'afiliaciones corporativas' => 'afiliaciones_corporativas',
            default => $group,
        };
    }

    private static function isControlPhone(string $phone): bool
    {
        $normalized = preg_replace('/\D/', '', $phone) ?? '';

        return in_array($normalized, [self::CONTROL_PHONE, '584143027250'], true);
    }

    private static function failureCategory(string $channel, string $message): string
    {
        $normalized = mb_strtolower($message);

        if (str_contains($normalized, 'email es nulo') || str_contains($normalized, 'email nulo')) {
            return 'email_nulo';
        }

        if (str_contains($normalized, 'email mal escrito') || str_contains($normalized, 'email inválido') || str_contains($normalized, 'email invalido')) {
            return 'email_invalido';
        }

        if (str_contains($normalized, 'telefono es nulo') || str_contains($normalized, 'teléfono es nulo')) {
            return 'telefono_nulo';
        }

        if (str_contains($normalized, 'fecha de cumpleaños es nula')) {
            return 'fecha_nula';
        }

        if (str_contains($normalized, 'formato de fecha')) {
            return 'fecha_invalida';
        }

        if ($channel === 'email') {
            return 'email_otros';
        }

        if ($channel === 'whatsapp') {
            return 'whatsapp_otros';
        }

        return 'otros';
    }

    private static function buildSummaryMessage(): string
    {
        $lines = [
            '📋 *Resumen de tarjetas de cumpleaños*',
            '📅 '.now()->timezone(config('app.timezone'))->format('d/m/Y H:i'),
            '',
        ];

        if (self::$criticalFailure) {
            $lines[] = '⚠️ *La ejecución terminó con error crítico.*';
            $lines[] = self::$criticalMessage ?? 'Error no especificado.';
            $lines[] = '';
        }

        $totalWhatsapp = 0;
        $totalEmail = 0;
        $totalFailures = 0;

        foreach (self::ALL_GROUPS as $group) {
            $data = self::$stats[$group] ?? [
                'whatsapp' => 0,
                'email' => 0,
                'failures' => [],
            ];
            $label = self::GROUP_LABELS[$group] ?? mb_convert_case($group, MB_CASE_TITLE, 'UTF-8');
            $failures = array_sum($data['failures']);
            $totalWhatsapp += $data['whatsapp'];
            $totalEmail += $data['email'];
            $totalFailures += $failures;

            $lines[] = "*{$label}*";
            $lines[] = '✅ WhatsApp encolados: '.$data['whatsapp'];
            $lines[] = '✅ Email enviados: '.$data['email'];
            $lines[] = '❌ Fallas: '.$failures;

            if ($failures > 0) {
                $lines[] = self::formatFailureBreakdown($data['failures']);
            }

            $lines[] = '';
        }

        $lines[] = '*Totales*';
        $lines[] = 'WhatsApp encolados: '.$totalWhatsapp;
        $lines[] = 'Email enviados: '.$totalEmail;
        $lines[] = 'Fallas: '.$totalFailures;

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, int>  $failures
     */
    private static function formatFailureBreakdown(array $failures): string
    {
        $labels = [
            'email_nulo' => '• Email nulo o vacío',
            'email_invalido' => '• Email mal escrito o inválido',
            'email_otros' => '• Otros fallos de email',
            'telefono_nulo' => '• Teléfono nulo o vacío',
            'fecha_nula' => '• Fecha de cumpleaños nula',
            'fecha_invalida' => '• Fecha de cumpleaños inválida',
            'whatsapp_otros' => '• Otros fallos de WhatsApp',
            'otros' => '• Otros',
        ];

        $parts = [];

        foreach ($labels as $key => $label) {
            if (($failures[$key] ?? 0) > 0) {
                $parts[] = $label.': '.$failures[$key];
            }
        }

        return implode("\n", $parts);
    }
}
