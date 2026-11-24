<?php

declare(strict_types=1);

use App\Filament\Operations\Pages\ManageServiceOrderPatientWhatsApp;
use App\Http\Controllers\UltraMsg\UltraMsgWebhookController;
use App\Models\ServiceOrderPatientWhatsAppSetting;
use App\Models\User;
use App\Support\Filament\DepartmentNavigationPermissionRegistry;
use App\Jobs\SendServiceOrderPatientWhatsAppJob;
use App\Jobs\SendServiceOrderPatientWhatsAppTextJob;
use App\Models\OperationServiceOrder;
use App\Models\ServiceOrderPatientNotification as PatientNotification;
use App\Models\WhatsAppInboundMessage;
use App\Jobs\RefreshUltraMsgConnectionStatusJob;
use App\Support\Operations\PatientWhatsApp\PatientReplyParser;
use App\Support\Operations\PatientWhatsApp\ServiceOrderPatientDeliveryTracker;
use App\Support\Operations\PatientWhatsApp\UltraMsgConnectionStatus;
use App\Support\Operations\PatientWhatsApp\ServiceOrderPatientMessage;
use App\Support\Operations\PatientWhatsApp\ServiceOrderPatientNotifier;
use App\Support\Operations\PatientWhatsApp\ServiceOrderPatientWhatsAppSettings;
use App\Support\Operations\PatientWhatsApp\UltraMsgClient;
use App\Support\Operations\PatientWhatsApp\UltraMsgInboundHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

uses(Tests\TestCase::class);

/**
 * WhatsApp al paciente de las órdenes de servicio no cubiertas, con respuesta SI/NO.
 * Escribe dentro de una transacción que siempre se revierte; la cola es falsa.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    config([
        'cache.default' => 'array',
        'services.service_order_patient_whatsapp.server_enabled' => true,
        'services.ultramsg_webhook.secret' => 'clave-secreta-de-prueba-0123456789',
        'services.ultramsg_webhook.instance_id' => null,
        'parameters.TOKEN' => null,
    ]);
    Queue::fake();
    ServiceOrderPatientWhatsAppSettings::override(true, 72);

    $this->orders = OperationServiceOrder::query()
        ->whereIn('service_type', ServiceOrderPatientNotifier::SERVICE_TYPES)
        ->whereNotNull('operation_coordination_service_id')
        ->latest('id')
        ->limit(3)
        ->get();

    if ($this->orders->count() < 3) {
        $this->markTestSkipped('Faltan órdenes de servicio de no cubiertos en la base.');
    }
});

afterEach(function (): void {
    ServiceOrderPatientWhatsAppSettings::override(null);
    DB::rollBack();
});

function patientNotificationFor(OperationServiceOrder $order, string $phone = '584121112233'): PatientNotification
{
    PatientNotification::query()->where('operation_service_order_id', $order->getKey())->delete();

    return PatientNotification::query()->create([
        'operation_service_order_id' => $order->getKey(),
        'operation_coordination_service_id' => $order->operation_coordination_service_id,
        'order_number' => $order->order_number,
        'service_type' => $order->service_type,
        'patient_name' => 'PACIENTE DE PRUEBA',
        'recipient_name' => 'PACIENTE DE PRUEBA',
        'recipient_kind' => PatientNotification::RECIPIENT_PATIENT,
        'phone' => $phone,
        'status' => PatientNotification::STATUS_WAITING_TURN,
        'queued_at' => now(),
        'created_by' => 'QA',
    ]);
}

function inboundPayload(string $id, string $from, string $body, array $extra = []): array
{
    return ['event_type' => 'message_received', 'instanceId' => '117518', 'data' => [
        'id' => $id, 'from' => $from, 'type' => 'chat', 'body' => $body, 'fromMe' => false, ...$extra,
    ]];
}

function pushedJobs(string $class): array
{
    return Queue::pushed($class)->all();
}

it('acepta solo SI o NO, sin importar mayúsculas, acentos ni signos', function (): void {
    foreach (['Si', 'SI', 'sí', ' Sí. ', 'SI 👍', '¡si!'] as $text) {
        expect(PatientReplyParser::parse($text))->toBe('SI');
    }

    foreach (['No', 'NO', 'no.', ' nO '] as $text) {
        expect(PatientReplyParser::parse($text))->toBe('NO');
    }

    foreach (['sí, gracias', 'no sé', 'ok', '1', '', 'claro', 'sisi', null] as $text) {
        expect(PatientReplyParser::parse($text))->toBeNull();
    }
});

it('normaliza el teléfono a dígitos con código de país y rechaza fijos venezolanos', function (): void {
    expect(ServiceOrderPatientNotifier::normalizePhone('0412-111.22.33'))->toBe('584121112233')
        ->and(ServiceOrderPatientNotifier::normalizePhone('+58 414 1112233'))->toBe('584141112233')
        ->and(ServiceOrderPatientNotifier::normalizePhone('0212-5551234'))->toBeNull()
        ->and(ServiceOrderPatientNotifier::normalizePhone('123'))->toBeNull()
        ->and(ServiceOrderPatientNotifier::normalizePhone(null))->toBeNull();
});

it('el mensaje trae el detalle de la orden y termina con la pregunta SI/NO, aun con muchos ítems', function (): void {
    $order = $this->orders->first();
    $caption = ServiceOrderPatientMessage::caption($order, 'MARIA PEREZ', 'JOSE PEREZ', true);

    expect($caption)->toContain((string) $order->order_number, 'Hola, Maria.', 'JOSE PEREZ', 'en proceso', 'Responda solo con SI o con NO')
        ->and(mb_strlen($caption))->toBeLessThanOrEqual(ServiceOrderPatientMessage::MAX_CAPTION_LENGTH);

    $order->setRelation('operationServiceOrderItems', collect(range(1, 80))->map(fn (int $i) => (new App\Models\OperationServiceOrderItem)->forceFill([
        'item_name' => 'MEDICAMENTO DE PRUEBA CON NOMBRE LARGO NUMERO '.$i, 'quantity' => 2, 'item_unit' => 'CAJA',
    ])));
    $long = ServiceOrderPatientMessage::caption($order, 'MARIA', 'MARIA', false);

    expect(mb_strlen($long))->toBeLessThanOrEqual(ServiceOrderPatientMessage::MAX_CAPTION_LENGTH)
        ->and($long)->toContain('más (ver documento)')
        ->and(str_ends_with($long, '*Responda solo con SI o con NO.*'))->toBeTrue();
});

it('apagado no registra nada; encendido registra una sola vez por orden', function (): void {
    $order = $this->orders->first();
    PatientNotification::query()->where('operation_service_order_id', $order->getKey())->delete();

    ServiceOrderPatientWhatsAppSettings::override(false);
    expect(ServiceOrderPatientNotifier::queueForOrder($order, 'QA'))->toBeNull();

    ServiceOrderPatientWhatsAppSettings::override(true);
    $first = ServiceOrderPatientNotifier::queueForOrder($order, 'QA');
    $second = ServiceOrderPatientNotifier::queueForOrder($order, 'QA');

    expect($first)->not->toBeNull()
        ->and($second->getKey())->toBe($first->getKey())
        ->and(PatientNotification::query()->where('operation_service_order_id', $order->getKey())->count())->toBe(1)
        ->and(in_array($first->status, [PatientNotification::STATUS_SENDING, PatientNotification::STATUS_NO_PHONE], true))->toBeTrue();
});

it('al mismo teléfono se le hace una sola pregunta a la vez: la segunda orden espera su turno', function (): void {
    [$a, $b] = [$this->orders[0], $this->orders[1]];
    $first = patientNotificationFor($a);
    $second = patientNotificationFor($b);
    $second->forceFill(['queued_at' => now()->addSecond()])->save();

    ServiceOrderPatientNotifier::dispatchNextFor('584121112233');
    ServiceOrderPatientNotifier::dispatchNextFor('584121112233');

    expect($first->fresh()->status)->toBe(PatientNotification::STATUS_SENDING)
        ->and($second->fresh()->status)->toBe(PatientNotification::STATUS_WAITING_TURN)
        ->and(count(pushedJobs(SendServiceOrderPatientWhatsAppJob::class)))->toBe(1);
});

it('un SI cierra la pregunta, confirma al paciente y libera el turno para la siguiente orden', function (): void {
    [$a, $b] = [$this->orders[0], $this->orders[1]];
    $first = patientNotificationFor($a);
    $first->forceFill(['status' => PatientNotification::STATUS_SENT, 'sent_at' => now(), 'expires_at' => now()->addDay()])->save();
    $second = patientNotificationFor($b);

    $inbound = UltraMsgInboundHandler::handle(inboundPayload('false_584121112233@c.us_A1', '584121112233@c.us', 'Sí'));

    expect($inbound->result)->toBe(WhatsAppInboundMessage::RESULT_ANSWER)
        ->and($inbound->service_order_patient_notification_id)->toBe($first->getKey())
        ->and($first->fresh()->status)->toBe(PatientNotification::STATUS_ANSWERED)
        ->and($first->fresh()->answer)->toBe('SI')
        ->and($first->fresh()->answer_raw)->toBe('Sí')
        ->and($second->fresh()->status)->toBe(PatientNotification::STATUS_SENDING)
        ->and(count(pushedJobs(SendServiceOrderPatientWhatsAppTextJob::class)))->toBe(1);
});

it('un texto que no es SI ni NO no se guarda como respuesta y se recuerda una sola vez', function (): void {
    $notification = patientNotificationFor($this->orders[0]);
    $notification->forceFill(['status' => PatientNotification::STATUS_SENT, 'sent_at' => now(), 'expires_at' => now()->addDay()])->save();

    $one = UltraMsgInboundHandler::handle(inboundPayload('false_x_B1', '584121112233@c.us', 'sí, gracias'));
    $two = UltraMsgInboundHandler::handle(inboundPayload('false_x_B2', '584121112233@c.us', 'hola?'));
    $audio = UltraMsgInboundHandler::handle(inboundPayload('false_x_B3', '584121112233@c.us', '', ['type' => 'ptt']));

    expect($one->result)->toBe(WhatsAppInboundMessage::RESULT_INVALID)
        ->and($two->result)->toBe(WhatsAppInboundMessage::RESULT_INVALID)
        ->and($audio->result)->toBe(WhatsAppInboundMessage::RESULT_INVALID)
        ->and($notification->fresh()->status)->toBe(PatientNotification::STATUS_SENT)
        ->and($notification->fresh()->invalid_replies)->toBe(3)
        ->and(count(pushedJobs(SendServiceOrderPatientWhatsAppTextJob::class)))->toBe(1);

    expect(UltraMsgInboundHandler::handle(inboundPayload('false_x_B4', '584121112233@c.us', 'NO'))->result)->toBe(WhatsAppInboundMessage::RESULT_ANSWER)
        ->and($notification->fresh()->answer)->toBe('NO');
});

it('un aviso repetido no se procesa dos veces; mensajes propios, de grupo o de números sin pregunta se descartan', function (): void {
    $notification = patientNotificationFor($this->orders[0]);
    $notification->forceFill(['status' => PatientNotification::STATUS_SENT, 'sent_at' => now(), 'expires_at' => now()->addDay()])->save();

    expect(UltraMsgInboundHandler::handle(inboundPayload('false_dup_C1', '584121112233@c.us', 'SI')))->not->toBeNull()
        ->and(UltraMsgInboundHandler::handle(inboundPayload('false_dup_C1', '584121112233@c.us', 'SI')))->toBeNull()
        ->and(UltraMsgInboundHandler::handle(inboundPayload('true_C2', '584121112233@c.us', 'SI', ['fromMe' => true]))->result)->toBe(WhatsAppInboundMessage::RESULT_SKIPPED)
        ->and(UltraMsgInboundHandler::handle(inboundPayload('false_C3', '120363000000@g.us', 'SI'))->result)->toBe(WhatsAppInboundMessage::RESULT_SKIPPED)
        ->and(UltraMsgInboundHandler::handle(inboundPayload('false_C4', '584149998877@c.us', 'SI'))->result)->toBe(WhatsAppInboundMessage::RESULT_IGNORED)
        ->and(UltraMsgInboundHandler::handle(['event_type' => 'message_ack', 'data' => ['id' => 'x']]))->toBeNull();
});

it('acepta una respuesta tardía de una orden que ya venció sin respuesta', function (): void {
    $notification = patientNotificationFor($this->orders[0]);
    $notification->forceFill(['status' => PatientNotification::STATUS_NO_ANSWER, 'sent_at' => now()->subDays(4), 'expires_at' => now()->subDay()])->save();

    expect(UltraMsgInboundHandler::handle(inboundPayload('false_late_D1', '584121112233@c.us', 'si'))->result)->toBe(WhatsAppInboundMessage::RESULT_ANSWER)
        ->and($notification->fresh()->status)->toBe(PatientNotification::STATUS_ANSWERED);
});

it('la revisión periódica vence las preguntas sin respuesta y envía la siguiente orden del teléfono', function (): void {
    [$a, $b] = [$this->orders[0], $this->orders[1]];
    $expired = patientNotificationFor($a);
    $expired->forceFill(['status' => PatientNotification::STATUS_SENT, 'sent_at' => now()->subDays(4), 'expires_at' => now()->subMinute()])->save();
    $waiting = patientNotificationFor($b);

    $result = ServiceOrderPatientNotifier::sweep();

    expect($result['expired'])->toBeGreaterThanOrEqual(1)
        ->and($expired->fresh()->status)->toBe(PatientNotification::STATUS_NO_ANSWER)
        ->and($waiting->fresh()->status)->toBe(PatientNotification::STATUS_SENDING);
});

it('un envío que no puede salir queda bloqueado y libera el turno', function (): void {
    $notification = patientNotificationFor($this->orders[0]);
    $notification->forceFill(['status' => PatientNotification::STATUS_SENDING])->save();
    ServiceOrderPatientWhatsAppSettings::override(false);

    ServiceOrderPatientNotifier::deliver((int) $notification->getKey());

    expect($notification->fresh()->status)->toBe(PatientNotification::STATUS_BLOCKED)
        ->and($notification->fresh()->isResendable())->toBeTrue();
});

it('lee el id del mensaje que devuelve UltraMsg y reconoce sus errores', function (): void {
    expect(UltraMsgClient::interpret('{"sent":"true","message":"ok","id":4521}', 200))->toBe(['ok' => true, 'id' => '4521', 'error' => null])
        ->and(UltraMsgClient::interpret('{"error":[{"to":"invalid number"}]}', 200)['ok'])->toBeFalse()
        ->and(UltraMsgClient::interpret('', 0, 'timeout')['ok'])->toBeFalse();
});

it('el webhook rechaza una clave equivocada y procesa con la clave correcta', function (): void {
    $controller = new UltraMsgWebhookController;
    $request = Request::create('/ultramsg/webhook/x', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(inboundPayload('false_E1', '584149998877@c.us', 'SI')));

    expect($controller($request, 'clave-equivocada-0000000000000')->getStatusCode())->toBe(403)
        ->and($controller($request, 'clave-secreta-de-prueba-0123456789')->getStatusCode())->toBe(200)
        ->and(WhatsAppInboundMessage::query()->where('message_id', 'false_E1')->exists())->toBeTrue();

    config(['services.ultramsg_webhook.secret' => null]);
    expect($controller($request, 'clave-secreta-de-prueba-0123456789')->getStatusCode())->toBe(403);
});

it('nace al aprobar la cotización, tiene ruta, tarea programada y tipo en el Centro de notificaciones', function (): void {
    $root = dirname(__DIR__, 2);

    expect((string) file_get_contents($root.'/app/Support/Operations/CoordinationServiceQuoteManager.php'))->toContain('ServiceOrderPatientNotifier::queueForOrder($order')
        ->and((string) file_get_contents($root.'/routes/web.php'))->toContain("Route::post('ultramsg/webhook/{secret}'")
        ->and((string) file_get_contents($root.'/routes/console.php'))->toContain('SweepServiceOrderPatientNotificationsJob')
        ->and((string) file_get_contents($root.'/app/Support/NotificationCenter/NotificationCatalogDefaults.php'))->toContain("'ext.service_order_patient'")
        ->and((string) file_get_contents($root.'/app/Filament/Operations/Resources/OperationServiceOrders/Schemas/OperationServiceOrderInfolist.php'))->toContain('ServiceOrderPatientNotificationSection::make(');
});

it('viene desactivado por defecto y se enciende solo desde la pantalla', function (): void {
    ServiceOrderPatientWhatsAppSettings::override(null);
    $settings = ServiceOrderPatientWhatsAppSetting::current();
    $settings->forceFill(['enabled' => false])->save();
    ServiceOrderPatientWhatsAppSettings::flush();

    expect(ServiceOrderPatientWhatsAppSettings::enabled())->toBeFalse()
        ->and(ServiceOrderPatientNotifier::queueForOrder($this->orders->first(), 'QA'))->toBeNull();

    $settings->forceFill(['enabled' => true])->save();
    ServiceOrderPatientWhatsAppSettings::flush();
    expect(ServiceOrderPatientWhatsAppSettings::enabled())->toBeTrue();

    config(['services.service_order_patient_whatsapp.server_enabled' => false]);
    expect(ServiceOrderPatientWhatsAppSettings::enabled())->toBeFalse();
});

it('la prueba sale con el interruptor apagado, marcada como prueba y con una orden de muestra', function (): void {
    ServiceOrderPatientWhatsAppSettings::override(false);
    $tester = (new User)->forceFill(['id' => 999_999_406, 'name' => 'QA PRUEBA WHATSAPP', 'status' => 'ACTIVO']);

    $test = ServiceOrderPatientNotifier::sendTest('0412-555.66.77', null, $tester);

    expect($test->is_test)->toBeTrue()
        ->and($test->operation_service_order_id)->toBeNull()
        ->and($test->sample_order_id)->not->toBeNull()
        ->and($test->phone)->toBe('584125556677')
        ->and($test->status)->toBe(PatientNotification::STATUS_SENDING)
        ->and($test->isResendable())->toBeFalse()
        ->and(count(pushedJobs(SendServiceOrderPatientWhatsAppJob::class)))->toBe(1);

    $caption = ServiceOrderPatientMessage::caption(OperationServiceOrder::find($test->sample_order_id), 'QA', 'PACIENTE DE PRUEBA', false, true);
    expect($caption)->toContain('MENSAJE DE PRUEBA', 'Responda solo con SI o con NO');

    /** La cola es falsa: se simula que UltraMsg aceptó el envío. */
    $test->forceFill(['status' => PatientNotification::STATUS_SENT, 'sent_at' => now(), 'expires_at' => now()->addDay()])->save();
    $inbound = UltraMsgInboundHandler::handle(inboundPayload('false_test_T1', '584125556677@c.us', 'SI'));
    expect($inbound->result)->toBe(WhatsAppInboundMessage::RESULT_ANSWER)
        ->and($test->fresh()->answer)->toBe('SI');
});

it('la prueba rechaza un número inválido y respeta el freno del servidor', function (): void {
    $tester = (new User)->forceFill(['id' => 999_999_407, 'name' => 'QA PRUEBA WHATSAPP', 'status' => 'ACTIVO']);

    expect(fn () => ServiceOrderPatientNotifier::sendTest('0212-5551234', null, $tester))->toThrow(RuntimeException::class, 'no es válido');

    config(['services.service_order_patient_whatsapp.server_enabled' => false]);
    expect(fn () => ServiceOrderPatientNotifier::sendTest('04125556677', null, $tester))->toThrow(RuntimeException::class, 'frenado');
});

it('un mensaje real no sale con el interruptor apagado; una prueba sí', function (): void {
    $real = patientNotificationFor($this->orders[0], '584121119999');
    $real->forceFill(['status' => PatientNotification::STATUS_SENDING])->save();
    ServiceOrderPatientWhatsAppSettings::override(false);

    ServiceOrderPatientNotifier::deliver((int) $real->getKey());

    expect($real->fresh()->status)->toBe(PatientNotification::STATUS_BLOCKED)
        ->and($real->fresh()->error)->toContain('desactivado');
});

it('la pantalla está en Operaciones con permiso propio y solo entra quien lo tiene', function (): void {
    $analyst = (new User)->forceFill(['id' => 999_999_408, 'name' => 'QA OPERACIONES', 'email' => 'qa.ops.whatsapp@tudrencasa.com', 'status' => 'ACTIVO', 'departament' => ['OPERACIONES'], 'is_superAdmin' => 0]);
    Illuminate\Support\Facades\Auth::setUser($analyst);

    expect(ManageServiceOrderPatientWhatsApp::getNavigationGroup())->toBe('COORDINACIÓN DE SERVICIOS')
        ->and(DepartmentNavigationPermissionRegistry::slugsFor(ManageServiceOrderPatientWhatsApp::class))->toBe(['whatsapp-paciente-ordenes'])
        ->and(DepartmentNavigationPermissionRegistry::moduleFor(ManageServiceOrderPatientWhatsApp::class))->toBe('OPERACIONES')
        ->and(ManageServiceOrderPatientWhatsApp::canAccess())->toBeFalse()
        ->and(DB::table('permissions')->where('slug', 'whatsapp-paciente-ordenes')->where('module', 'OPERACIONES')->exists())->toBeTrue();
});

function ackPayload(array $top, array $data): array
{
    return ['event_type' => 'message_ack', 'instanceId' => '117518', ...$top, 'data' => [
        'from' => '584127018390@c.us', 'to' => '584121112233@c.us', 'fromMe' => true, ...$data,
    ]];
}

function sentPatientNotification(OperationServiceOrder $order): PatientNotification
{
    $notification = patientNotificationFor($order);
    $notification->forceFill(['status' => PatientNotification::STATUS_SENT, 'attempts' => 1, 'ultramsg_message_id' => '987654', 'sent_at' => now(), 'expires_at' => now()->addDay()])->save();

    return $notification;
}

it('traduce los checks de WhatsApp al estado de entrega', function (): void {
    expect(ServiceOrderPatientDeliveryTracker::normalizeAck('pending'))->toBe(PatientNotification::DELIVERY_PENDING)
        ->and(ServiceOrderPatientDeliveryTracker::normalizeAck('server'))->toBe(PatientNotification::DELIVERY_SERVER)
        ->and(ServiceOrderPatientDeliveryTracker::normalizeAck('DEVICE'))->toBe(PatientNotification::DELIVERY_DEVICE)
        ->and(ServiceOrderPatientDeliveryTracker::normalizeAck('read'))->toBe(PatientNotification::DELIVERY_READ)
        ->and(ServiceOrderPatientDeliveryTracker::normalizeAck('played'))->toBe(PatientNotification::DELIVERY_READ)
        ->and(ServiceOrderPatientDeliveryTracker::normalizeAck(2))->toBe(PatientNotification::DELIVERY_DEVICE)
        ->and(ServiceOrderPatientDeliveryTracker::normalizeAck('error'))->toBeNull()
        ->and(ServiceOrderPatientDeliveryTracker::normalizeAck(null))->toBeNull();
});

it('el webhook marca entregado y leído por la referencia del envío, sin retroceder ni guardar el aviso como mensaje', function (): void {
    $notification = sentPatientNotification($this->orders[0]);
    $reference = ServiceOrderPatientDeliveryTracker::referenceFor($notification);
    $inboundBefore = WhatsAppInboundMessage::query()->count();

    expect($notification->deliveryLabel())->toBe('Esperando confirmación de WhatsApp')
        ->and($reference)->toBe('sopn-'.$notification->getKey().'-a1');

    UltraMsgInboundHandler::handle(ackPayload(['referenceId' => $reference], ['id' => 'true_584121112233@c.us_W1', 'ack' => 'device']));
    $fresh = $notification->fresh();

    expect($fresh->delivery_status)->toBe(PatientNotification::DELIVERY_DEVICE)
        ->and($fresh->delivered_at)->not->toBeNull()
        ->and($fresh->read_at)->toBeNull()
        ->and($fresh->whatsapp_message_id)->toBe('true_584121112233@c.us_W1')
        ->and($fresh->deliveryLabel())->toBe('✓✓ Entregado')
        ->and(WhatsAppInboundMessage::query()->count())->toBe($inboundBefore);

    /** Un aviso atrasado de «server» no hace retroceder la entrega. */
    UltraMsgInboundHandler::handle(ackPayload(['referenceId' => $reference], ['id' => 'true_584121112233@c.us_W1', 'ack' => 'server']));
    expect($notification->fresh()->delivery_status)->toBe(PatientNotification::DELIVERY_DEVICE);

    UltraMsgInboundHandler::handle(ackPayload([], ['id' => 'true_584121112233@c.us_W1', 'ack' => 'read']));
    expect($notification->fresh()->delivery_status)->toBe(PatientNotification::DELIVERY_READ)
        ->and($notification->fresh()->read_at)->not->toBeNull()
        ->and($notification->fresh()->delivered_at?->toDateTimeString())->toBe($fresh->delivered_at->toDateTimeString());
});

it('también reconoce el aviso por el id de UltraMsg e ignora los checks de otros mensajes o de un envío anterior', function (): void {
    $notification = sentPatientNotification($this->orders[0]);

    UltraMsgInboundHandler::handle(ackPayload(['id' => '987654'], ['id' => 'true_X', 'ack' => 'server']));
    expect($notification->fresh()->delivery_status)->toBe(PatientNotification::DELIVERY_SERVER);

    /** Referencia del intento 0 (envío anterior al reenvío): no cuenta. */
    UltraMsgInboundHandler::handle(ackPayload(['referenceId' => 'sopn-'.$notification->getKey().'-a0'], ['id' => 'true_OLD', 'ack' => 'read']));
    expect($notification->fresh()->delivery_status)->toBe(PatientNotification::DELIVERY_SERVER);

    /** Un cumpleaños u otro mensaje de la instancia: no toca nada. */
    expect(ServiceOrderPatientDeliveryTracker::handleWebhook(ackPayload(['id' => '1'], ['id' => 'true_OTRO', 'ack' => 'read'])))->toBeNull()
        ->and(ServiceOrderPatientDeliveryTracker::handleWebhook(ackPayload(['referenceId' => 'sopn-'.$notification->getKey().'-a1'], ['id' => 'true_X', 'ack' => 'raro'])))->toBeNull();
});

it('un nuevo envío arranca con la entrega en blanco', function (): void {
    $notification = sentPatientNotification($this->orders[0]);
    ServiceOrderPatientDeliveryTracker::apply($notification, PatientNotification::DELIVERY_READ, 'true_PREV');
    $notification->forceFill(['status' => PatientNotification::STATUS_WAITING_TURN])->save();

    ServiceOrderPatientNotifier::dispatchNextFor((string) $notification->phone);
    $fresh = $notification->fresh();

    expect($fresh->status)->toBe(PatientNotification::STATUS_SENDING)
        ->and($fresh->attempts)->toBe(2)
        ->and($fresh->delivery_status)->toBeNull()
        ->and($fresh->delivered_at)->toBeNull()
        ->and($fresh->read_at)->toBeNull()
        ->and($fresh->whatsapp_message_id)->toBeNull()
        ->and($fresh->ultramsg_message_id)->toBeNull()
        ->and(ServiceOrderPatientDeliveryTracker::referenceFor($fresh))->toEndWith('-a2');
});

it('lee el check de un mensaje en la consulta de UltraMsg', function (): void {
    $body = '{"total":1,"pages":1,"messages":[{"id":987654,"referenceId":"sopn-1-a1","to":"584121112233@c.us","status":"sent","ack":"device"}]}';

    expect(UltraMsgClient::interpretMessageAck($body, 200, '987654'))->toBe('device')
        ->and(UltraMsgClient::interpretMessageAck($body, 200, '111'))->toBeNull()
        ->and(UltraMsgClient::interpretMessageAck('{"error":"Wrong token"}', 200, '987654'))->toBeNull()
        ->and(UltraMsgClient::interpretMessageAck('', 500, '987654'))->toBeNull();
});

it('interpreta el estado de la instancia de WhatsApp', function (): void {
    expect(UltraMsgConnectionStatus::interpret('{"status":{"accountStatus":{"status":"authenticated","substatus":"connected"}}}', 200)['state'])->toBe(UltraMsgConnectionStatus::STATE_CONNECTED)
        ->and(UltraMsgConnectionStatus::interpret('{"status":{"accountStatus":{"status":"qr","substatus":"qr"}}}', 200)['state'])->toBe(UltraMsgConnectionStatus::STATE_DISCONNECTED)
        ->and(UltraMsgConnectionStatus::interpret('{"status":{"accountStatus":{"status":"qr"}}}', 200)['detail'])->toContain('escanear el código QR')
        ->and(UltraMsgConnectionStatus::interpret('{"status":{"accountStatus":{"status":"authenticated","substatus":"phone"}}}', 200)['state'])->toBe(UltraMsgConnectionStatus::STATE_DISCONNECTED)
        ->and(UltraMsgConnectionStatus::interpret('{"error":"Wrong token. Please provide token as a GET parameter."}', 200)['state'])->toBe(UltraMsgConnectionStatus::STATE_UNREACHABLE)
        ->and(UltraMsgConnectionStatus::interpret(false, 0, 'Operation timed out')['state'])->toBe(UltraMsgConnectionStatus::STATE_UNREACHABLE)
        ->and(UltraMsgConnectionStatus::interpret('<html>', 502)['state'])->toBe(UltraMsgConnectionStatus::STATE_UNREACHABLE);
});

it('sin token dice «Sin configurar»; con token muestra el último dato y pide uno nuevo en cola sin esperar a UltraMsg', function (): void {
    Cache::forget(UltraMsgConnectionStatus::CACHE_KEY);
    expect(UltraMsgConnectionStatus::current()['state'])->toBe(UltraMsgConnectionStatus::STATE_NOT_CONFIGURED)
        ->and(count(pushedJobs(RefreshUltraMsgConnectionStatusJob::class)))->toBe(0);

    config(['parameters.TOKEN' => 'token-de-prueba', 'parameters.CURLOPT_URL' => 'https://api.ultramsg.com/instance117518/messages/chat']);
    expect(UltraMsgClient::instanceBaseUrl())->toBe('https://api.ultramsg.com/instance117518')
        ->and(UltraMsgConnectionStatus::current()['state'])->toBe(UltraMsgConnectionStatus::STATE_UNKNOWN)
        ->and(count(pushedJobs(RefreshUltraMsgConnectionStatusJob::class)))->toBe(1);

    Cache::forever(UltraMsgConnectionStatus::CACHE_KEY, ['state' => UltraMsgConnectionStatus::STATE_CONNECTED, 'label' => 'x', 'detail' => 'Conectada.', 'checked_at' => now()->toIso8601String()]);
    $current = UltraMsgConnectionStatus::current();
    expect($current['state'])->toBe(UltraMsgConnectionStatus::STATE_CONNECTED)
        ->and($current['label'])->toBe('Conectada');
    Cache::forget(UltraMsgConnectionStatus::CACHE_KEY);
});

it('el webhook anota la hora del último aviso para el indicador de recepción', function (): void {
    Cache::forget(UltraMsgConnectionStatus::WEBHOOK_CACHE_KEY);
    $controller = new UltraMsgWebhookController;
    $request = Request::create('/ultramsg/webhook/x', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(ackPayload(['id' => '1'], ['id' => 'true_Z', 'ack' => 'device'])));

    $controller($request, 'clave-equivocada-0000000000000');
    expect(Cache::get(UltraMsgConnectionStatus::WEBHOOK_CACHE_KEY))->toBeNull();

    $controller($request, 'clave-secreta-de-prueba-0123456789');
    expect(UltraMsgConnectionStatus::lastWebhookAt())->not->toBeNull()
        ->and(UltraMsgConnectionStatus::webhook()['label'])->toBe('Recibiendo');

    config(['services.ultramsg_webhook.secret' => null]);
    expect(UltraMsgConnectionStatus::webhook()['label'])->toBe('Sin configurar');
});
