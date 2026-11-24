<?php

declare(strict_types=1);

namespace App\Support\Operations\PatientWhatsApp;

use App\Filament\Operations\Pages\ManageServiceOrderPatientWhatsApp;
use App\Filament\Operations\Resources\OperationServiceOrders\OperationServiceOrderResource;
use App\Jobs\SendServiceOrderPatientWhatsAppJob;
use App\Jobs\SendServiceOrderPatientWhatsAppTextJob;
use App\Models\OperationCoordinationService;
use App\Models\OperationServiceOrder;
use App\Models\ServiceOrderPatientNotification as Notification;
use App\Models\TelemedicineCase;
use App\Models\TelemedicinePatient;
use App\Models\User;
use App\Models\WhatsAppInboundMessage;
use App\Services\HelpdeskTicketAssigneeWhatsAppService;
use App\Support\NotificationCenter\OutboundWhatsApp;
use App\Support\SecurityAudit;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * WhatsApp al paciente cuando nace la orden de servicio de un servicio no cubierto, con la
 * pregunta SI/NO y su respuesta.
 *
 * Reglas:
 * - Una fila por orden (índice único): aprobar dos veces o reintentar no duplica el mensaje.
 * - **Una sola pregunta abierta por teléfono.** Como el paciente solo responde SI o NO, la
 *   respuesta no dice a qué orden se refiere. Por eso, si al mismo número ya se le preguntó
 *   por otra orden, la nueva espera su turno (EN_ESPERA) y sale cuando la anterior se
 *   responde o vence. Cada respuesta corresponde sin duda a una sola orden.
 * - Nada de esto puede tumbar la aprobación de la cotización: cualquier falla se registra
 *   y la tarea programada {@see self::sweep()} retoma lo pendiente.
 * - Menor de edad: se le escribe al representante.
 */
final class ServiceOrderPatientNotifier
{
    public const CATALOG_KEY = 'ext.service_order_patient';

    /** Tipos de orden de los servicios no cubiertos que avisan al paciente. */
    public const SERVICE_TYPES = ['MEDICAMENTOS', 'LABORATORIOS', 'IMAGENOLOGIA', 'ESPECIALISTA'];

    /** Minutos sin avanzar para dar por perdido un envío en cola y volver a intentarlo. */
    public const STUCK_SENDING_MINUTES = 30;

    /** Días en que todavía se acepta la respuesta de una orden ya marcada «Sin respuesta». */
    public const LATE_ANSWER_DAYS = 7;

    /**
     * Interruptor de Operaciones → «WhatsApp al paciente» y freno del servidor abiertos.
     */
    public static function enabled(): bool
    {
        return ServiceOrderPatientWhatsAppSettings::enabled();
    }

    public static function responseHours(): int
    {
        return ServiceOrderPatientWhatsAppSettings::responseHours();
    }

    public static function queueName(): string
    {
        return (string) config('services.service_order_patient_whatsapp.queue', 'default');
    }

    /**
     * Registra el aviso de una orden recién creada. Se llama dentro de la transacción de la
     * aprobación: si esta se revierte, el aviso también, y el envío sale solo tras el commit.
     */
    public static function queueForOrder(OperationServiceOrder $order, ?string $actorName = null): ?Notification
    {
        if (! self::enabled() || ! in_array((string) $order->service_type, self::SERVICE_TYPES, true)) {
            return null;
        }

        try {
            return DB::transaction(static function () use ($order, $actorName): Notification {
                $existing = Notification::query()->where('operation_service_order_id', $order->getKey())->first();

                if ($existing instanceof Notification) {
                    return $existing;
                }

                $coordination = OperationCoordinationService::query()->find($order->operation_coordination_service_id);
                $recipient = self::resolveRecipient($coordination);

                $notification = Notification::query()->create([
                    'operation_service_order_id' => $order->getKey(),
                    'operation_coordination_service_id' => $coordination?->getKey(),
                    'telemedicine_patient_id' => $coordination?->telemedicine_patient_id,
                    'telemedicine_case_id' => $coordination?->telemedicine_case_id,
                    'order_number' => $order->order_number,
                    'service_type' => $order->service_type,
                    'patient_name' => $recipient['patient_name'],
                    'recipient_name' => $recipient['name'],
                    'recipient_kind' => $recipient['kind'],
                    'phone' => $recipient['phone'],
                    'status' => $recipient['phone'] !== null ? Notification::STATUS_WAITING_TURN : Notification::STATUS_NO_PHONE,
                    'queued_at' => now(),
                    'error' => $recipient['phone'] !== null ? null : 'El paciente no tiene un teléfono válido para WhatsApp. Corrija el teléfono y use «Reenviar».',
                    'created_by' => $actorName ?? $order->created_by,
                    'updated_by' => $actorName ?? $order->created_by,
                ]);

                if ($notification->status === Notification::STATUS_NO_PHONE) {
                    self::notifyAnalyst($notification, 'WhatsApp al paciente sin enviar', 'La orden '.$notification->order_number.' no se pudo notificar: '.($recipient['kind'] === Notification::RECIPIENT_REPRESENTATIVE ? 'el representante' : 'el paciente').' no tiene un teléfono válido.', 'warning');
                } else {
                    self::safeDispatchNextFor((string) $notification->phone);
                }

                return $notification->refresh();
            });
        } catch (Throwable $exception) {
            report($exception);
            Log::error('WhatsApp al paciente: no se pudo registrar el aviso de la orden.', ['order_id' => $order->getKey(), 'error' => $exception->getMessage()]);

            return null;
        }
    }

    /**
     * Si el teléfono no tiene una pregunta abierta, envía la siguiente en espera (la más antigua).
     */
    public static function dispatchNextFor(string $phone): void
    {
        if ($phone === '') {
            return;
        }

        Cache::lock(self::phoneLockKey($phone), 15)->block(10, static function () use ($phone): void {
            DB::transaction(static function () use ($phone): void {
                $holding = Notification::query()
                    ->where('phone', $phone)
                    ->whereIn('status', Notification::TURN_HOLDING_STATUSES)
                    ->lockForUpdate()
                    ->exists();

                if ($holding) {
                    return;
                }

                $next = Notification::query()
                    ->where('phone', $phone)
                    ->where('status', Notification::STATUS_WAITING_TURN)
                    ->orderBy('queued_at')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->first();

                if (! $next instanceof Notification) {
                    return;
                }

                $next->forceFill([
                    'status' => Notification::STATUS_SENDING,
                    'attempts' => $next->attempts + 1,
                    'error' => null,
                    ...self::freshDelivery(),
                ])->save();

                SendServiceOrderPatientWhatsAppJob::dispatch((int) $next->getKey())
                    ->onQueue(self::queueName())
                    ->afterCommit();
            });
        });
    }

    /**
     * Envía el documento con el detalle y la pregunta. Lo llama el job; lanza una excepción
     * ante una falla de UltraMsg para que el job reintente.
     */
    public static function deliver(int $notificationId): void
    {
        $notification = Notification::query()->find($notificationId);

        if (! $notification instanceof Notification || $notification->status !== Notification::STATUS_SENDING) {
            return;
        }

        if (self::anotherHoldsTheTurn($notification)) {
            $notification->forceFill(['status' => Notification::STATUS_WAITING_TURN])->save();

            return;
        }

        $orderId = $notification->documentOrderId();
        $order = $orderId !== null ? OperationServiceOrder::query()->find($orderId) : null;

        if (! $order instanceof OperationServiceOrder) {
            self::finish($notification, Notification::STATUS_FAILED, 'La orden de servicio ya no existe.');

            return;
        }

        /** Una prueba sale con el interruptor apagado (es para probar antes de activar), nunca con el freno del servidor. */
        if (! ServiceOrderPatientWhatsAppSettings::serverAllows() || (! $notification->is_test && ! self::enabled())) {
            self::finish($notification, Notification::STATUS_BLOCKED, ServiceOrderPatientWhatsAppSettings::serverAllows()
                ? 'El WhatsApp al paciente está desactivado en Operaciones → «WhatsApp al paciente».'
                : 'El envío está frenado en este servidor (SERVICE_ORDER_PATIENT_WHATSAPP_ENABLED=false).');

            return;
        }

        $phone = (string) $notification->phone;
        $caption = ServiceOrderPatientMessage::caption(
            $order,
            (string) ($notification->recipient_name ?: $notification->patient_name),
            (string) $notification->patient_name,
            $notification->recipient_kind === Notification::RECIPIENT_REPRESENTATIVE,
            (bool) $notification->is_test,
        );
        $filename = 'Orden-de-servicio-'.(preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $order->order_number) ?: $order->getKey()).'.pdf';
        $context = ['catalog' => self::CATALOG_KEY, 'origin' => self::class.'::deliver', 'recipient_name' => $notification->recipient_name ?: $notification->patient_name];
        $decision = OutboundWhatsApp::decide($phone, $context);
        $logParams = ['to' => $phone, 'caption' => $caption, 'document' => $filename, 'filename' => $filename];

        if ($decision->blocked()) {
            OutboundWhatsApp::recordBlocked($decision, $logParams);
            self::finish($notification, Notification::STATUS_BLOCKED, 'El Centro de notificaciones tiene apagado este mensaje: '.($decision->blockedReason ?? 'sin detalle').'.');

            return;
        }

        $document = ServiceOrderPatientDocument::publish($order);

        try {
            $result = UltraMsgClient::sendDocument($phone, $caption, $document['url'], $filename, ServiceOrderPatientDeliveryTracker::referenceFor($notification));
        } catch (Throwable $exception) {
            ServiceOrderPatientDocument::delete($document['relative_path']);

            throw $exception;
        }

        OutboundWhatsApp::record($decision, $result['ok'], $logParams, $result['error']);

        if (! $result['ok']) {
            ServiceOrderPatientDocument::delete($document['relative_path']);
            $notification->forceFill(['error' => $result['error']])->save();

            throw new RuntimeException('UltraMsg no aceptó el WhatsApp de la orden '.$order->order_number.': '.$result['error']);
        }

        ServiceOrderPatientDocument::scheduleDeletion($document['relative_path']);

        $notification->forceFill([
            'status' => Notification::STATUS_SENT,
            'message' => $caption,
            'ultramsg_message_id' => $result['id'],
            'sent_at' => now(),
            'expires_at' => now()->addHours(self::responseHours()),
            'error' => null,
        ])->save();
    }

    /**
     * El job agotó sus intentos: la orden queda «Fallida» y pasa la siguiente del teléfono.
     */
    public static function markFailed(int $notificationId, string $error): void
    {
        $notification = Notification::query()->find($notificationId);

        if (! $notification instanceof Notification || $notification->status !== Notification::STATUS_SENDING) {
            return;
        }

        self::finish($notification, Notification::STATUS_FAILED, $error);

        if ($notification->is_test) {
            self::notifyAnalyst($notification, 'La prueba de WhatsApp falló', 'No se pudo enviar el mensaje de prueba a +'.$notification->phone.': '.mb_substr($error, 0, 300), 'danger');

            return;
        }

        self::notifyAnalyst($notification, 'WhatsApp al paciente fallido', 'No se pudo enviar el WhatsApp de la orden '.$notification->order_number.' a '.$notification->patient_name.'. Revise el teléfono y use «Reenviar».', 'danger');
    }

    /**
     * Respuesta del paciente que llegó por el webhook.
     *
     * Se aplica a la pregunta abierta de ese teléfono (solo hay una). Si no hay ninguna, se
     * acepta como respuesta tardía de la última que venció hace menos de
     * {@see self::LATE_ANSWER_DAYS} días. Un texto que no es SI ni NO se contesta una sola vez
     * con un recordatorio, para no entrar en una conversación sin fin.
     *
     * @return string Resultado guardado en el mensaje recibido ({@see WhatsAppInboundMessage}::RESULT_*)
     */
    public static function registerReply(string $phone, ?string $text, WhatsAppInboundMessage $inbound): string
    {
        $outcome = Cache::lock(self::phoneLockKey($phone), 15)->block(10, static fn (): array => DB::transaction(static function () use ($phone, $text): array {
            $notification = Notification::query()
                ->where('phone', $phone)
                ->where('status', Notification::STATUS_SENT)
                ->orderBy('sent_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            $notification ??= Notification::query()
                ->where('phone', $phone)
                ->where('status', Notification::STATUS_NO_ANSWER)
                ->where('sent_at', '>=', now()->subDays(self::LATE_ANSWER_DAYS))
                ->orderByDesc('sent_at')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if (! $notification instanceof Notification) {
                return ['result' => WhatsAppInboundMessage::RESULT_IGNORED, 'notification' => null, 'answer' => null, 'remind' => false];
            }

            $answer = PatientReplyParser::parse($text);

            if ($answer === null) {
                $notification->forceFill(['invalid_replies' => $notification->invalid_replies + 1])->save();

                return [
                    'result' => WhatsAppInboundMessage::RESULT_INVALID,
                    'notification' => $notification,
                    'answer' => null,
                    'remind' => $notification->status === Notification::STATUS_SENT && $notification->invalid_replies === 1,
                ];
            }

            $notification->forceFill([
                'status' => Notification::STATUS_ANSWERED,
                'answer' => $answer,
                'answer_raw' => mb_substr((string) $text, 0, 500),
                'answered_at' => now(),
                'updated_by' => 'Paciente (WhatsApp)',
            ])->save();

            return ['result' => WhatsAppInboundMessage::RESULT_ANSWER, 'notification' => $notification, 'answer' => $answer, 'remind' => false];
        }));

        /** @var Notification|null $notification */
        $notification = $outcome['notification'];
        $inbound->forceFill(['service_order_patient_notification_id' => $notification?->getKey()])->save();

        if (! $notification instanceof Notification) {
            return $outcome['result'];
        }

        if ($outcome['remind']) {
            self::sendText($phone, ServiceOrderPatientMessage::invalidReplyReminder((string) $notification->order_number), 'recordatorio');
        }

        if ($outcome['answer'] !== null) {
            self::sendText($phone, ServiceOrderPatientMessage::answerConfirmation($outcome['answer']), 'confirmacion');

            SecurityAudit::log('AUDIT_SERVICE_ORDER_PATIENT_ANSWER', 'operations.service-order.patient-whatsapp', [
                'notification_id' => $notification->getKey(),
                'order_id' => $notification->operation_service_order_id,
                'answer' => $outcome['answer'],
            ]);

            if ($notification->is_test) {
                self::notifyAnalyst($notification, 'Prueba de WhatsApp: respondieron '.$outcome['answer'], 'El número +'.$notification->phone.' respondió «'.$outcome['answer'].'» al mensaje de prueba. La respuesta llegó y quedó guardada: el circuito completo funciona.', 'success');
            } elseif ($outcome['answer'] === Notification::ANSWER_NO) {
                self::notifyAnalyst($notification, 'El paciente respondió NO', 'En la orden '.$notification->order_number.', '.$notification->patient_name.' respondió que NO recibió la orden o no puede ver el documento. Comuníquese con él.', 'danger');
            }

            self::safeDispatchNextFor($phone);
        }

        return $outcome['result'];
    }

    /**
     * Tarea programada: vence las preguntas sin respuesta, retoma envíos perdidos y despacha
     * los turnos libres. Idempotente.
     *
     * @return array{expired: int, recovered: int, dispatched: int, delivery: int}
     */
    public static function sweep(): array
    {
        $expired = 0;
        $recovered = 0;

        Notification::query()
            ->where('status', Notification::STATUS_SENT)
            ->where('expires_at', '<', now())
            ->orderBy('id')
            ->limit(500)
            ->get()
            ->each(static function (Notification $notification) use (&$expired): void {
                $updated = Notification::query()
                    ->whereKey($notification->getKey())
                    ->where('status', Notification::STATUS_SENT)
                    ->update(['status' => Notification::STATUS_NO_ANSWER, 'updated_by' => 'system', 'updated_at' => now()]);

                if ($updated === 1) {
                    $expired++;

                    if ($notification->is_test) {
                        return;
                    }

                    self::notifyAnalyst($notification, 'El paciente no respondió', 'Pasaron '.self::responseHours().' horas sin respuesta de '.$notification->patient_name.' sobre la orden '.$notification->order_number.'.', 'warning');
                }
            });

        $recovered = Notification::query()
            ->where('status', Notification::STATUS_SENDING)
            ->where('updated_at', '<', now()->subMinutes(self::STUCK_SENDING_MINUTES))
            ->update(['status' => Notification::STATUS_WAITING_TURN, 'updated_at' => now()]);

        $phones = Notification::query()
            ->where('status', Notification::STATUS_WAITING_TURN)
            ->whereNotNull('phone')
            ->distinct()
            ->limit(500)
            ->pluck('phone');

        foreach ($phones as $phone) {
            self::safeDispatchNextFor((string) $phone);
        }

        ServiceOrderPatientDocument::purgeOld();

        $delivery = 0;

        try {
            $delivery = ServiceOrderPatientDeliveryTracker::pollPending();
        } catch (Throwable $exception) {
            Log::warning('WhatsApp al paciente: falló la consulta de entregas.', ['error' => $exception->getMessage()]);
        }

        return ['expired' => $expired, 'recovered' => $recovered, 'dispatched' => $phones->count(), 'delivery' => $delivery];
    }

    /**
     * Reenvío manual: vuelve a resolver el teléfono (por si se corrigió) y pone la orden en turno.
     */
    public static function resend(Notification $notification, User $user): Notification
    {
        if (! $notification->isResendable()) {
            throw new RuntimeException('Solo se reenvía un WhatsApp fallido, bloqueado, sin teléfono o sin respuesta.');
        }

        if (! self::enabled()) {
            throw new RuntimeException('El envío de WhatsApp al paciente está apagado en este servidor.');
        }

        $fresh = DB::transaction(static function () use ($notification, $user): Notification {
            $locked = Notification::query()->whereKey($notification->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->isResendable()) {
                throw new RuntimeException('El WhatsApp de esta orden cambió mientras tanto. Recargue la página.');
            }

            $recipient = self::resolveRecipient(OperationCoordinationService::query()->find($locked->operation_coordination_service_id));

            $locked->forceFill([
                'phone' => $recipient['phone'],
                'recipient_name' => $recipient['name'],
                'recipient_kind' => $recipient['kind'],
                'status' => $recipient['phone'] !== null ? Notification::STATUS_WAITING_TURN : Notification::STATUS_NO_PHONE,
                'error' => $recipient['phone'] !== null ? null : 'El paciente sigue sin un teléfono válido para WhatsApp.',
                'queued_at' => now(),
                'sent_at' => null,
                'expires_at' => null,
                'answer' => null,
                'answer_raw' => null,
                'answered_at' => null,
                'invalid_replies' => 0,
                ...self::freshDelivery(),
                'updated_by' => $user->name,
            ])->save();

            SecurityAudit::log('AUDIT_SERVICE_ORDER_PATIENT_RESEND', 'operations.service-order.patient-whatsapp', [
                'notification_id' => $locked->getKey(),
                'order_id' => $locked->operation_service_order_id,
            ], $user);

            return $locked;
        });

        if ($fresh->status === Notification::STATUS_NO_PHONE) {
            throw new RuntimeException('El paciente sigue sin un teléfono válido para WhatsApp. Corríjalo en la ficha del paciente o del caso.');
        }

        self::safeDispatchNextFor((string) $fresh->phone);

        return $fresh->refresh();
    }

    /**
     * A quién se le escribe: al paciente o, si es menor de edad, a su representante.
     *
     * @return array{phone: string|null, name: string, kind: string, patient_name: string}
     */
    public static function resolveRecipient(?OperationCoordinationService $coordination): array
    {
        $patient = $coordination?->telemedicine_patient_id !== null ? TelemedicinePatient::query()->find($coordination->telemedicine_patient_id) : null;
        $case = $coordination?->telemedicine_case_id !== null ? TelemedicineCase::query()->find($coordination->telemedicine_case_id) : null;
        $patientName = trim((string) ($patient?->full_name ?? ''));
        $isMinor = $patient instanceof TelemedicinePatient && self::isMinor($patient);
        $isIndividual = strtoupper((string) ($patient?->type_affiliation ?? '')) === 'INDIVIDUAL';

        /** El teléfono de contacto es el del titular en una afiliación individual; en una corporativa es el de la empresa. */
        $contactPhone = $isIndividual ? $patient?->phone_contact : null;
        $representative = $isMinor ? self::representative($patient) : null;

        $candidates = $isMinor
            ? [$representative['phone'] ?? null, $case?->patient_phone, $case?->patient_phone_2, $contactPhone, $patient?->phone]
            : [$case?->patient_phone, $patient?->phone, $case?->patient_phone_2, $contactPhone];

        $phone = null;

        foreach ($candidates as $candidate) {
            $phone = self::normalizePhone(is_scalar($candidate) ? (string) $candidate : null);

            if ($phone !== null) {
                break;
            }
        }

        return [
            'phone' => $phone,
            'name' => $isMinor ? (string) ($representative['name'] ?? '') : $patientName,
            'kind' => $isMinor ? Notification::RECIPIENT_REPRESENTATIVE : Notification::RECIPIENT_PATIENT,
            'patient_name' => $patientName,
        ];
    }

    /**
     * Solo dígitos con código de país (584121234567), o null si no sirve para WhatsApp.
     */
    public static function normalizePhone(?string $phone): ?string
    {
        $normalized = HelpdeskTicketAssigneeWhatsAppService::normalizePhoneForWhatsApp($phone);
        $digits = $normalized !== null ? (preg_replace('/\D+/', '', $normalized) ?? '') : '';

        if (strlen($digits) < 11 || strlen($digits) > 15) {
            return null;
        }

        /** Un número venezolano debe ser móvil (58 4xx…): a un fijo no le llega WhatsApp. */
        if (str_starts_with($digits, '58') && ($digits[2] ?? '') !== '4') {
            return null;
        }

        return $digits;
    }

    /**
     * Mensaje de prueba desde Operaciones → «WhatsApp al paciente», a un número cualquiera y
     * con una orden real de muestra. Sale aunque el interruptor esté apagado y recorre el mismo
     * camino que uno real (cola, PDF, UltraMsg y respuesta SI/NO por el webhook), así se valida
     * todo antes de activar. Respeta el turno del teléfono como cualquier otro mensaje.
     */
    public static function sendTest(string $phone, ?int $sampleOrderId, User $user): Notification
    {
        if (! ServiceOrderPatientWhatsAppSettings::serverAllows()) {
            throw new RuntimeException('El envío está frenado en este servidor (SERVICE_ORDER_PATIENT_WHATSAPP_ENABLED=false). Pida apoyo técnico.');
        }

        $normalized = self::normalizePhone($phone);

        if ($normalized === null) {
            throw new RuntimeException('El número no es válido para WhatsApp. Escríbalo con código de área, por ejemplo 0412-1234567.');
        }

        $order = $sampleOrderId !== null
            ? OperationServiceOrder::query()->whereIn('service_type', self::SERVICE_TYPES)->find($sampleOrderId)
            : self::sampleOrdersQuery()->first();

        if (! $order instanceof OperationServiceOrder) {
            throw new RuntimeException('No hay una orden de servicio de no cubiertos para usar de muestra.');
        }

        $notification = DB::transaction(static function () use ($normalized, $order, $user): Notification {
            $notification = Notification::query()->create([
                'operation_service_order_id' => null,
                'is_test' => true,
                'sample_order_id' => $order->getKey(),
                'order_number' => $order->order_number,
                'service_type' => $order->service_type,
                'patient_name' => 'PACIENTE DE PRUEBA',
                'recipient_name' => $user->name,
                'recipient_kind' => Notification::RECIPIENT_PATIENT,
                'phone' => $normalized,
                'status' => Notification::STATUS_WAITING_TURN,
                'queued_at' => now(),
                'created_by' => $user->name,
                'updated_by' => $user->name,
            ]);

            SecurityAudit::log('AUDIT_SERVICE_ORDER_PATIENT_TEST', 'operations.service-order.patient-whatsapp', [
                'notification_id' => $notification->getKey(),
                'sample_order_id' => $order->getKey(),
                'phone' => $normalized,
            ], $user);

            return $notification;
        });

        self::safeDispatchNextFor($normalized);

        return $notification->refresh();
    }

    /**
     * Órdenes de no cubiertos que sirven de muestra para una prueba, la más reciente primero.
     *
     * @return Builder<OperationServiceOrder>
     */
    public static function sampleOrdersQuery(): Builder
    {
        return OperationServiceOrder::query()
            ->whereIn('service_type', self::SERVICE_TYPES)
            ->whereNotNull('order_number')
            ->latest('id');
    }

    /**
     * Como {@see self::dispatchNextFor()}, pero sin propagar fallas (candado ocupado, base
     * caída): la orden queda en espera y la tarea programada la retoma en minutos.
     */
    public static function safeDispatchNextFor(string $phone): void
    {
        try {
            self::dispatchNextFor($phone);
        } catch (Throwable $exception) {
            report($exception);
            Log::warning('WhatsApp al paciente: el turno del teléfono se retomará en la próxima revisión.', ['error' => $exception->getMessage()]);
        }
    }

    /**
     * Entrega en blanco para un envío nuevo: los checks de un envío anterior no cuentan.
     *
     * @return array<string, null>
     */
    private static function freshDelivery(): array
    {
        return [
            'ultramsg_message_id' => null,
            'whatsapp_message_id' => null,
            'delivery_status' => null,
            'delivered_at' => null,
            'read_at' => null,
            'delivery_checked_at' => null,
        ];
    }

    public static function phoneLockKey(string $phone): string
    {
        return 'service-order-patient-whatsapp:phone:'.$phone;
    }

    /**
     * Por la fecha de nacimiento (la columna `age` se guarda al asociar el paciente y envejece);
     * `age` solo si la fecha no se puede leer.
     */
    private static function isMinor(TelemedicinePatient $patient): bool
    {
        $birthDate = trim((string) $patient->birth_date);

        foreach (['d/m/Y', 'j/n/Y', 'Y-m-d'] as $format) {
            try {
                $date = Carbon::createFromFormat($format, $birthDate);

                if ($date !== false && $date->format($format) === $birthDate) {
                    return $date->age < 18;
                }
            } catch (Throwable) {
                continue;
            }
        }

        return is_numeric($patient->age) && (int) $patient->age < 18;
    }

    /**
     * @return array{name: string, phone: string|null}|null
     */
    private static function representative(TelemedicinePatient $patient): ?array
    {
        static $tableExists = null;
        $tableExists ??= Schema::hasTable('telemedicine_representatives');

        if (! $tableExists) {
            return null;
        }

        $row = DB::table('telemedicine_representatives')
            ->where('telemedicine_patient_id', $patient->getKey())
            ->whereNotNull('phone')
            ->orderByDesc('id')
            ->first(['full_name', 'phone']);

        return $row !== null ? ['name' => (string) $row->full_name, 'phone' => (string) $row->phone] : null;
    }

    private static function anotherHoldsTheTurn(Notification $notification): bool
    {
        return Notification::query()
            ->where('phone', $notification->phone)
            ->whereKeyNot($notification->getKey())
            ->where(static fn ($query) => $query
                ->where('status', Notification::STATUS_SENT)
                ->orWhere(static fn ($sending) => $sending
                    ->where('status', Notification::STATUS_SENDING)
                    ->where('id', '<', $notification->getKey())))
            ->exists();
    }

    private static function finish(Notification $notification, string $status, string $error): void
    {
        $notification->forceFill(['status' => $status, 'error' => mb_substr($error, 0, 2000), 'updated_by' => 'system'])->save();

        if (filled($notification->phone)) {
            self::safeDispatchNextFor((string) $notification->phone);
        }
    }

    private static function sendText(string $phone, string $body, string $kind): void
    {
        SendServiceOrderPatientWhatsAppTextJob::dispatch($phone, $body, $kind)->onQueue(self::queueName())->afterCommit();
    }

    private static function notifyAnalyst(Notification $notification, string $title, string $body, string $status): void
    {
        DB::afterCommit(static function () use ($notification, $title, $body, $status): void {
            try {
                $user = filled($notification->created_by)
                    ? User::query()->where('name', $notification->created_by)->where('status', 'ACTIVO')->first()
                    : null;

                if (! $user instanceof User) {
                    return;
                }

                $action = $notification->is_test
                    ? Action::make('ver_pruebas')
                        ->label('Ver las pruebas')
                        ->url(ManageServiceOrderPatientWhatsApp::getUrl(panel: 'operations'))
                        ->markAsRead()
                    : Action::make('ver_orden')
                        ->label('Ver la orden')
                        ->url(OperationServiceOrderResource::getUrl('view', ['record' => $notification->operation_service_order_id], panel: 'operations'))
                        ->markAsRead();

                FilamentNotification::make()
                    ->title($title)
                    ->body($body)
                    ->status($status)
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->actions([$action])
                    ->sendToDatabase($user);
            } catch (Throwable $exception) {
                report($exception);
            }
        });
    }
}
