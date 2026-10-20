<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\MassNotificationDeliveryStatus;
use App\Enums\SystemNotificationKey;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\UtilsController;
use App\Jobs\WhatsAppBirthdayNotification;
use App\Mail\BirthdayNotificationSummaryMail;
use App\Models\BirthdayNotification;
use App\Support\Birthday\BirthdayCelebrationAccessIssuer;
use App\Support\Birthday\BirthdayCelebrationCard;
use App\Support\Birthday\BirthdayCelebrationRecipientGate;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Mail\PendingMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class BirthdayNotificationRunReport
{
    private const SUMMARY_IMAGE = WhatsAppBrandImage::RELATIVE_PATH;

    /** @var list<string> */
    private const CELEBRATION_BCC_EMAILS = [
        'solrodriguez@tudrencasa.com',
        'aaular@tudrencasa.com',
    ];

    /** @var list<string> */
    private const CELEBRATION_COPY_WHATSAPP_PHONES = [
        '+584121931865',
        '+584143027250',
    ];

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

    /** @var array<string, string> */
    private const DATA_TYPE_LABELS = [
        'agents' => 'Agentes',
        'agencies' => 'Agencias',
        'affiliates' => 'Afiliaciones individuales',
        'affiliate_corporates' => 'Afiliaciones corporativas',
        'rrhh_colaboradors' => 'Colaboradores',
        'suppliers' => 'Proveedores',
    ];

    private static bool $active = false;

    private static ?string $currentGroup = null;

    private static ?int $currentNotificationId = null;

    private static bool $currentSendsCelebrationCard = false;

    /** @var array<int, true> */
    private static array $excludedRecipientIds = [];

    /** @var array<string, array{whatsapp: int, email: int, failures: array<string, int>, records_in_source: int, validation_passes: int, validations_total: int, channels_seen: array<string, bool>}> */
    private static array $stats = [];

    /** @var list<array{title: string, data_type: string, group: string, channels: list<string>}> */
    private static array $runConfiguration = [];

    private static bool $criticalFailure = false;

    private static ?string $criticalMessage = null;

    public static function begin(): void
    {
        self::$active = true;
        self::$stats = [];
        self::$runConfiguration = [];

        foreach (self::ALL_GROUPS as $group) {
            self::$stats[$group] = self::emptyGroupStats();
        }

        self::$currentGroup = null;
        self::$currentNotificationId = null;
        self::$currentSendsCelebrationCard = false;
        self::$excludedRecipientIds = [];
        self::$criticalFailure = false;
        self::$criticalMessage = null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $notifications
     */
    public static function registerRunConfiguration(array $notifications): void
    {
        self::$runConfiguration = [];

        foreach ($notifications as $notification) {
            $dataTypes = BirthdayNotificationAudience::normalizeDataTypes($notification['data_type'] ?? null);
            $channels = array_values(array_filter(
                is_array($notification['channels'] ?? null) ? $notification['channels'] : [],
                static fn (mixed $channel): bool => is_string($channel) && $channel !== '',
            ));

            if ($dataTypes === []) {
                self::$runConfiguration[] = [
                    'title' => (string) ($notification['title'] ?? 'Sin título'),
                    'data_type' => '',
                    'group' => '',
                    'channels' => $channels,
                ];

                continue;
            }

            foreach ($dataTypes as $dataType) {
                self::$runConfiguration[] = [
                    'title' => (string) ($notification['title'] ?? 'Sin título'),
                    'data_type' => $dataType,
                    'group' => self::dataTypeToGroup($dataType),
                    'channels' => $channels,
                ];
            }
        }
    }

    public static function setCurrentGroup(string $group): void
    {
        self::$currentGroup = $group;
    }

    public static function setCurrentNotification(?int $birthdayNotificationId): void
    {
        self::$currentNotificationId = $birthdayNotificationId;
        self::$currentSendsCelebrationCard = false;
        self::$excludedRecipientIds = [];

        if ($birthdayNotificationId === null || ! Schema::hasColumn('birthday_notifications', 'sends_celebration_card')) {
            return;
        }

        $notification = BirthdayNotification::query()->find($birthdayNotificationId);

        if ($notification === null || ! $notification->sends_celebration_card) {
            return;
        }

        self::$currentSendsCelebrationCard = true;
        self::$excludedRecipientIds = [];
    }

    public static function setExcludedForDataType(string $dataType): void
    {
        if (! self::$currentSendsCelebrationCard || self::$currentNotificationId === null || $dataType === '') {
            self::$excludedRecipientIds = [];

            return;
        }

        self::$excludedRecipientIds = BirthdayCelebrationRecipientGate::excludedIdMap(
            self::$currentNotificationId,
            $dataType,
        );
    }

    /**
     * @param  list<object>  $rows
     * @return list<object>
     */
    public static function withoutExcludedRecipients(array $rows): array
    {
        if (self::$excludedRecipientIds === []) {
            return $rows;
        }

        return array_values(array_filter(
            $rows,
            static function (object $row): bool {
                $recipientId = $row->id ?? null;

                if ($recipientId === null) {
                    return true;
                }

                return ! isset(self::$excludedRecipientIds[(int) $recipientId]);
            },
        ));
    }

    public static function currentNotificationId(): ?int
    {
        return self::$currentNotificationId;
    }

    public static function isActive(): bool
    {
        return self::$active;
    }

    public static function recordValidationBatch(string $group, int $recordCount, string $channel): void
    {
        if (! self::$active) {
            return;
        }

        $group = self::normalizeGroup($group);
        self::ensureGroup($group);

        self::$stats[$group]['validation_passes']++;
        self::$stats[$group]['validations_total'] += $recordCount;
        self::$stats[$group]['records_in_source'] = max(
            self::$stats[$group]['records_in_source'],
            $recordCount,
        );
        self::$stats[$group]['channels_seen'][$channel] = true;
    }

    public static function queueWhatsApp(
        string $name,
        string $phone,
        string $content,
        string $file,
        string $type,
        ?string $documentNumber = null,
        ?string $birthDate = null,
        bool $attachCelebrationLink = true,
    ): \Illuminate\Foundation\Bus\PendingDispatch {
        $isControlCopy = self::isWitnessCopyPhone($phone);
        $deliveryId = null;
        $contentToSend = $content;

        if (self::$active && self::$currentGroup !== null && ! $isControlCopy) {
            self::incrementSent(self::$currentGroup, 'whatsapp');
        }

        if (! $isControlCopy && self::$currentNotificationId !== null) {
            $delivery = BirthdayNotificationRecipientDelivery::recordWhatsappOutcome(
                self::$currentNotificationId,
                $name,
                $phone,
                MassNotificationDeliveryStatus::Sent,
                null,
                null,
                null,
                $documentNumber,
                $birthDate,
            );
            $deliveryId = $delivery->id;

            if ($attachCelebrationLink && self::$currentSendsCelebrationCard) {
                $animationUrl = self::resolveAnimationUrlForRecipient(
                    $name,
                    $phone,
                    null,
                    $documentNumber,
                    $birthDate,
                );
                $contentToSend = trim($content).self::formatCelebrationLinkForMessage($animationUrl);
            }
        }

        if (self::$currentSendsCelebrationCard) {
            $file = BirthdayCelebrationCard::PUBLIC_RELATIVE_PATH;
            $type = 'image';
        }

        return WhatsAppBirthdayNotification::dispatch(
            $name,
            $phone,
            $contentToSend,
            $file,
            $type,
            $isControlCopy,
            $deliveryId,
        );
    }

    /**
     * Encola copias testigo WhatsApp a los teléfonos del Centro de notificaciones.
     */
    public static function queueWitnessWhatsAppCopies(
        string $name,
        string $content,
        string $file,
        string $type,
    ): void {
        foreach (self::celebrationCopyPhones(self::witnessCopyPhones()) as $index => $phone) {
            self::queueWhatsApp($name, $phone, $content, $file, $type)
                ->delay(now()->addSeconds(10 + ($index * 2)));
        }
    }

    /**
     * @param  list<string>  $phones
     * @return list<string>
     */
    private static function celebrationCopyPhones(array $phones): array
    {
        if (! self::$currentSendsCelebrationCard) {
            return $phones;
        }

        foreach (self::CELEBRATION_COPY_WHATSAPP_PHONES as $fixedPhone) {
            $target = self::normalizePhoneDigits($fixedPhone);
            $alreadyPresent = false;

            foreach ($phones as $phone) {
                if (self::phonesMatch($target, self::normalizePhoneDigits($phone))) {
                    $alreadyPresent = true;
                    break;
                }
            }

            if (! $alreadyPresent) {
                $phones[] = $fixedPhone;
            }
        }

        return array_values($phones);
    }

    /**
     * @return list<string>
     */
    public static function witnessCopyEmails(): array
    {
        if (! Schema::hasTable('system_notification_recipient_settings')) {
            return [];
        }

        if (! SystemNotificationRecipients::isActive(SystemNotificationKey::BirthdayNotificationWitnessCopy)) {
            return [];
        }

        return SystemNotificationRecipients::emails(SystemNotificationKey::BirthdayNotificationWitnessCopy);
    }

    /**
     * @return list<string>
     */
    public static function witnessCopyPhones(): array
    {
        if (! Schema::hasTable('system_notification_recipient_settings')) {
            return [];
        }

        if (! SystemNotificationRecipients::isActive(SystemNotificationKey::BirthdayNotificationWitnessCopy)) {
            return [];
        }

        return SystemNotificationRecipients::phones(SystemNotificationKey::BirthdayNotificationWitnessCopy);
    }

    public static function mailerWithWitnessCopies(string $toEmail): PendingMail
    {
        $mailer = Mail::to($toEmail);
        $cc = self::witnessCopyEmails();

        if ($cc !== []) {
            $mailer->cc($cc);
        }

        if (self::$currentSendsCelebrationCard) {
            $bcc = array_values(array_filter(
                self::CELEBRATION_BCC_EMAILS,
                static fn (string $email): bool => strcasecmp($email, $toEmail) !== 0,
            ));

            if ($bcc !== []) {
                $mailer->bcc($bcc);
            }
        }

        return $mailer;
    }

    /**
     * @param  callable(?string $animationUrl): void  $sendMail
     */
    public static function sendBirthdayEmail(
        ?string $email,
        string $name,
        callable $sendMail,
        ?string $phone = null,
        ?string $documentNumber = null,
        ?string $birthDate = null,
    ): void {
        $group = self::$currentGroup ?? 'general';

        if (blank($email)) {
            if (self::$currentNotificationId !== null) {
                BirthdayNotificationRecipientDelivery::recordEmailOutcome(
                    self::$currentNotificationId,
                    $name,
                    $email,
                    MassNotificationDeliveryStatus::Skipped,
                    'Email es nulo o vacio',
                );
            }

            UtilsController::notificationFailed('email', $name, $email, null, 'Email es nulo o vacio', $group);

            return;
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            if (self::$currentNotificationId !== null) {
                BirthdayNotificationRecipientDelivery::recordEmailOutcome(
                    self::$currentNotificationId,
                    $name,
                    $email,
                    MassNotificationDeliveryStatus::Skipped,
                    'Email mal escrito o inválido',
                );
            }

            UtilsController::notificationFailed('email', $name, $email, null, 'Email mal escrito o inválido', $group);

            return;
        }

        try {
            $animationUrl = self::$currentSendsCelebrationCard
                ? self::resolveAnimationUrlForRecipient(
                    $name,
                    $phone,
                    $email,
                    $documentNumber,
                    $birthDate,
                )
                : null;

            $sendMail($animationUrl);

            if (self::$active && self::$currentGroup !== null) {
                self::incrementSent(self::$currentGroup, 'email');
            }

            if (self::$currentNotificationId !== null) {
                BirthdayNotificationRecipientDelivery::recordEmailOutcome(
                    self::$currentNotificationId,
                    $name,
                    $email,
                    MassNotificationDeliveryStatus::Sent,
                    null,
                    $phone,
                );
            }
        } catch (Throwable $exception) {
            if (self::$currentNotificationId !== null) {
                BirthdayNotificationRecipientDelivery::recordEmailOutcome(
                    self::$currentNotificationId,
                    $name,
                    $email,
                    MassNotificationDeliveryStatus::Failed,
                    'Error al enviar email: '.$exception->getMessage(),
                );
            }

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
        self::$currentNotificationId = null;

        if (! SystemNotificationRecipients::isActive(SystemNotificationKey::BirthdayNotificationSummary)) {
            return;
        }

        $phones = SystemNotificationRecipients::phones(SystemNotificationKey::BirthdayNotificationSummary);
        $emails = SystemNotificationRecipients::emails(SystemNotificationKey::BirthdayNotificationSummary);

        if ($phones === [] && $emails === []) {
            return;
        }

        try {
            $fullMessage = self::buildSummaryMessage();
            $imageCaption = self::buildWhatsAppImageCaption();

            foreach ($phones as $phone) {
                NotificationController::notificationBirthday(
                    'Equipo Integracorp',
                    $phone,
                    $imageCaption,
                    self::SUMMARY_IMAGE,
                    'image',
                );

                NotificationController::sendWhatsAppChat(
                    $phone,
                    'Equipo Integracorp',
                    $fullMessage,
                );
            }

            foreach ($emails as $email) {
                if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    Log::warning('BirthdayNotificationRunReport: correo de resumen inválido.', [
                        'email' => $email,
                    ]);

                    continue;
                }

                Mail::to($email)->send(new BirthdayNotificationSummaryMail(
                    recipientEmail: $email,
                    summaryMessage: $fullMessage,
                ));
            }
        } catch (Throwable $exception) {
            Log::error('BirthdayNotificationRunReport: no se pudo enviar resumen de cumpleaños.', [
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @return array<string, array{whatsapp: int, email: int, failures: array<string, int>, records_in_source: int, validation_passes: int, validations_total: int, channels_seen: array<string, bool>}>
     */
    public static function statsForTesting(): array
    {
        return self::$stats;
    }

    public static function summaryMessageForTesting(): string
    {
        return self::buildSummaryMessage();
    }

    /**
     * @return list<array{title: string, data_type: string, group: string, channels: list<string>}>
     */
    public static function runConfigurationForTesting(): array
    {
        return self::$runConfiguration;
    }

    /**
     * @return array{whatsapp: int, email: int, failures: array<string, int>, records_in_source: int, validation_passes: int, validations_total: int, channels_seen: array<string, bool>}
     */
    private static function emptyGroupStats(): array
    {
        return [
            'whatsapp' => 0,
            'email' => 0,
            'failures' => [],
            'records_in_source' => 0,
            'validation_passes' => 0,
            'validations_total' => 0,
            'channels_seen' => [],
        ];
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
            self::$stats[$group] = self::emptyGroupStats();
        }
    }

    private static function normalizeGroup(string $group): string
    {
        return match ($group) {
            'afiliaciones corporativas' => 'afiliaciones_corporativas',
            default => $group,
        };
    }

    private static function dataTypeToGroup(string $dataType): string
    {
        return match ($dataType) {
            'agents' => 'agentes',
            'agencies' => 'agencias',
            'affiliates' => 'afiliaciones',
            'affiliate_corporates' => 'afiliaciones_corporativas',
            'rrhh_colaboradors' => 'colaboradores',
            'suppliers' => 'proveedores',
            default => $dataType,
        };
    }

    private static function isWitnessCopyPhone(string $phone): bool
    {
        $normalized = self::normalizePhoneDigits($phone);

        if (self::$currentSendsCelebrationCard) {
            foreach (self::CELEBRATION_COPY_WHATSAPP_PHONES as $fixedPhone) {
                if (self::phonesMatch($normalized, self::normalizePhoneDigits($fixedPhone))) {
                    return true;
                }
            }
        }

        if (! Schema::hasTable('system_notification_recipient_settings')) {
            return false;
        }

        if ($normalized === '') {
            return false;
        }

        foreach (self::witnessCopyPhones() as $witnessPhone) {
            if (self::phonesMatch($normalized, self::normalizePhoneDigits($witnessPhone))) {
                return true;
            }
        }

        return false;
    }

    private static function normalizePhoneDigits(string $phone): string
    {
        return preg_replace('/\D/', '', $phone) ?? '';
    }

    private static function phonesMatch(string $left, string $right): bool
    {
        if ($left === '' || $right === '') {
            return false;
        }

        if ($left === $right) {
            return true;
        }

        $leftTail = self::phoneNationalTail($left);
        $rightTail = self::phoneNationalTail($right);

        return $leftTail !== '' && $leftTail === $rightTail;
    }

    private static function phoneNationalTail(string $digits): string
    {
        if (str_starts_with($digits, '58') && strlen($digits) >= 12) {
            return substr($digits, -10);
        }

        if (str_starts_with($digits, '0') && strlen($digits) >= 11) {
            return substr($digits, -10);
        }

        return strlen($digits) >= 10 ? substr($digits, -10) : $digits;
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
        $lines = RunReportMessageFormatter::titleLines('📋', 'Resumen de tarjetas de cumpleaños');

        $lines = array_merge($lines, RunReportMessageFormatter::bulletSection('Qué hace esta tarea', [
            'Envía tarjetas de cumpleaños por WhatsApp y/o email a agentes, agencias, afiliaciones, colaboradores y proveedores según las tarjetas aprobadas en el sistema.',
        ]));

        $lines = array_merge($lines, RunReportMessageFormatter::bulletSection('Cómo leer este reporte', [
            'Se revisa *todo* el padrón de cada grupo, no solo quienes cumplen años hoy.',
            'Por cada tarjeta *APROBADA* y cada canal activo (WhatsApp / Email) se valida *cada registro*.',
            '*Fallas* = validaciones que no pasaron (fecha, email o teléfono). Un mismo registro puede sumar varias fallas si hay varios canales.',
            '*Envíos* = solo registros que cumplen años *hoy* y pasaron todas las validaciones.',
            'Fecha válida requerida: *dd/mm/aaaa* (ej. 27/04/1972). Otros formatos cuentan como inválidos.',
        ]));

        $lines = array_merge($lines, RunReportMessageFormatter::criticalFailureLines(self::$criticalFailure, self::$criticalMessage));

        $lines = array_merge($lines, self::formatRunConfigurationSection());
        $lines[] = '';

        $totalWhatsapp = 0;
        $totalEmail = 0;
        $totalFailures = 0;
        $totalValidations = 0;

        foreach (self::ALL_GROUPS as $group) {
            $data = self::$stats[$group] ?? self::emptyGroupStats();
            $label = self::GROUP_LABELS[$group] ?? mb_convert_case($group, MB_CASE_TITLE, 'UTF-8');
            $failures = array_sum($data['failures']);
            $totalWhatsapp += $data['whatsapp'];
            $totalEmail += $data['email'];
            $totalFailures += $failures;
            $totalValidations += $data['validations_total'];

            $lines[] = "*{$label}*";
            $lines = array_merge($lines, self::formatGroupMetrics($group, $data, $failures));
            $lines[] = '';
        }

        $lines[] = '*Totales generales*';
        $lines[] = '📝 Validaciones realizadas: '.$totalValidations;
        $lines[] = '✅ WhatsApp encolados: '.$totalWhatsapp;
        $lines[] = '✅ Email enviados: '.$totalEmail;
        $lines[] = '❌ Fallas registradas: '.$totalFailures;
        $lines[] = '';
        $lines[] = '_Las fallas totales no equivalen a personas distintas cuando un grupo se procesó por varios canales._';

        return implode("\n", $lines);
    }

    private static function buildWhatsAppImageCaption(): string
    {
        $totalWhatsapp = 0;
        $totalEmail = 0;
        $totalFailures = 0;
        $totalValidations = 0;

        foreach (self::ALL_GROUPS as $group) {
            $data = self::$stats[$group] ?? self::emptyGroupStats();
            $totalWhatsapp += $data['whatsapp'];
            $totalEmail += $data['email'];
            $totalFailures += array_sum($data['failures']);
            $totalValidations += $data['validations_total'];
        }

        $lines = [
            '📋 *Resumen de tarjetas de cumpleaños*',
            RunReportMessageFormatter::executionTimestamp(),
            '📝 Validaciones realizadas: '.$totalValidations,
            '✅ WhatsApp encolados: '.$totalWhatsapp,
            '✅ Email enviados: '.$totalEmail,
            '❌ Fallas registradas: '.$totalFailures,
            '',
            '_Detalle completo en el siguiente mensaje._',
        ];

        if (self::$criticalFailure) {
            array_splice($lines, 2, 0, ['⚠️ Error crítico en la ejecución.']);
        }

        return RunReportMessageFormatter::truncateForWhatsAppCaption(implode("\n", $lines));
    }

    /**
     * @return list<string>
     */
    private static function formatRunConfigurationSection(): array
    {
        if (self::$runConfiguration === []) {
            return ['⚙️ *Configuración:* no había tarjetas aprobadas para procesar.'];
        }

        $lines = [
            '⚙️ *Configuración de la ejecución*',
            '• Tarjetas aprobadas procesadas: '.count(self::$runConfiguration),
        ];

        foreach (self::$runConfiguration as $config) {
            $groupLabel = self::GROUP_LABELS[$config['group']] ?? self::DATA_TYPE_LABELS[$config['data_type']] ?? $config['data_type'];
            $channelsLabel = $config['channels'] === [] ? 'sin canales' : implode(' + ', $config['channels']);
            $lines[] = '  - '.$groupLabel.': "'.$config['title'].'" ('.$channelsLabel.')';
        }

        return $lines;
    }

    /**
     * @param  array{whatsapp: int, email: int, failures: array<string, int>, records_in_source: int, validation_passes: int, validations_total: int, channels_seen: array<string, bool>}  $data
     * @return list<string>
     */
    private static function formatGroupMetrics(string $group, array $data, int $failures): array
    {
        if ($data['validation_passes'] === 0) {
            return [
                '⏭️ No procesado (sin tarjeta aprobada para este grupo).',
                '✅ WhatsApp encolados: 0',
                '✅ Email enviados: 0',
                '❌ Fallas: 0',
            ];
        }

        $passes = $data['validation_passes'];
        $records = $data['records_in_source'];
        $channelsSeen = array_keys(array_filter($data['channels_seen']));
        $channelsLabel = $channelsSeen === [] ? 'ninguno' : implode(' + ', $channelsSeen);
        $approvedCards = self::approvedCardsCountForGroup($group);

        $lines = [
            '📊 Registros en base de datos: '.$records,
            '🔄 Pasadas de validación: '.$passes.' ('.$channelsLabel.' × '.max($approvedCards, 1).' tarjeta(s) aprobada(s))',
            '📝 Validaciones realizadas: '.$data['validations_total'].' ('.$records.' registros × '.$passes.' pasadas)',
            '✅ WhatsApp encolados: '.$data['whatsapp'],
            '✅ Email enviados: '.$data['email'],
            '❌ Fallas registradas: '.$failures,
        ];

        if ($failures > 0 && $passes > 1) {
            $estimatedUnique = (int) ceil($failures / $passes);
            $lines[] = '📌 Registros únicos estimados con falla: ~'.$estimatedUnique.' ('.$failures.' fallas ÷ '.$passes.' pasadas)';
        }

        if ($failures > 0) {
            $lines[] = self::formatFailureBreakdown($data['failures'], $passes);
        }

        return $lines;
    }

    private static function approvedCardsCountForGroup(string $group): int
    {
        $count = 0;

        foreach (self::$runConfiguration as $config) {
            if ($config['group'] === $group) {
                $count++;
            }
        }

        return $count;
    }

    public static function resolveAnimationUrlForRecipient(
        string $name,
        ?string $phone = null,
        ?string $email = null,
        ?string $documentNumber = null,
        ?string $birthDate = null,
    ): ?string {
        if (self::$currentNotificationId === null || ! Schema::hasTable('birthday_celebration_accesses')) {
            return null;
        }

        try {
            $delivery = BirthdayNotificationRecipientDelivery::resolveDelivery(
                self::$currentNotificationId,
                $name,
                $email,
                $phone,
                null,
                $documentNumber,
                $birthDate,
            );

            $access = BirthdayCelebrationAccessIssuer::ensureForDelivery(
                $delivery->id,
                $name,
                $phone,
                $documentNumber,
                self::parseBirthDate($birthDate),
                now(),
            );

            return route('birthday.animation.access', ['token' => $access->public_token]);
        } catch (Throwable $exception) {
            Log::warning('No se pudo emitir acceso de animación/cupones de cumpleaños.', [
                'recipient' => $name,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    public static function formatCelebrationLinkForMessage(?string $animationUrl): string
    {
        if (blank($animationUrl)) {
            return '';
        }

        return "\n\n¡Haz clic aquí para ver tus regalos!\n".$animationUrl;
    }

    private static function parseBirthDate(?string $birthDate): ?CarbonInterface
    {
        if (! filled($birthDate)) {
            return null;
        }

        $value = trim($birthDate);

        foreach (['d/m/Y', 'Y-m-d', 'd-m-Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->startOfDay();
            } catch (\Throwable) {
                continue;
            }
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, int>  $failures
     */
    private static function formatFailureBreakdown(array $failures, int $passes): string
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
            $count = $failures[$key] ?? 0;

            if ($count <= 0) {
                continue;
            }

            if ($passes > 1) {
                $estimatedUnique = (int) ceil($count / $passes);
                $parts[] = $label.': '.$count.' (~'.$estimatedUnique.' registros únicos)';
            } else {
                $parts[] = $label.': '.$count;
            }
        }

        return implode("\n", $parts);
    }
}
