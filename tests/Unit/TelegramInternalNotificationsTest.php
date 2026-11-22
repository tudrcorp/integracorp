<?php

declare(strict_types=1);

use App\Enums\NotificationDeliveryChannel;
use App\Enums\NotificationDeliveryStatus;
use App\Enums\SystemNotificationKey;
use App\Enums\TelegramDeliveryMode;
use App\Enums\TelegramRecipientStatus;
use App\Enums\TelegramRecipientType;
use App\Jobs\SendNotificacionWhatsApp;
use App\Jobs\SendTelegramDeliveryJob;
use App\Models\NotificationDelivery;
use App\Models\SystemNotificationRecipientSetting;
use App\Models\TelegramLinkToken;
use App\Models\TelegramRecipient;
use App\Models\User;
use App\Support\InternalNotifications\InternalContactResolver;
use App\Support\InternalNotifications\InternalNotification;
use App\Support\InternalNotifications\InternalNotificationDispatcher;
use App\Support\InternalNotifications\InternalNotificationFallback;
use App\Support\LivePresence\SystemHealthWatcher;
use App\Support\Telegram\TelegramApiException;
use App\Support\Telegram\TelegramLinkService;
use App\Support\Telegram\TelegramText;
use App\Support\Telegram\TelegramUpdateHandler;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

uses(Tests\TestCase::class);

/**
 * Escriben en la base, así que corren dentro de una transacción que siempre se
 * revierte (mismo patrón que PlanStructurePersistenceTest). Telegram, WhatsApp
 * y el correo están simulados: no sale nada real.
 */
beforeEach(function (): void {
    DB::beginTransaction();

    /** La base local ya tiene envíos reales de pruebas manuales: cada test mira solo lo que él crea. */
    $this->deliveryFloor = (int) NotificationDelivery::query()->max('id');

    config([
        'telegram.enabled' => true,
        'telegram.bot_token' => '123456:TEST-TOKEN',
        'telegram.bot_username' => 'integracorp_test_bot',
        'telegram.webhook_secret' => 'secreto-de-prueba-1234567890',
        'telegram.api_base_url' => 'https://api.telegram.org',
        'telegram.queue' => 'notifications',
        'telegram.link_token_minutes' => 15,
        'telegram.internal_email_domain' => 'tudrencasa.com',
        'telegram.max_attempts' => 3,
        'telegram.backoff_seconds' => [10, 30, 90],
        'telegram.health_cache_seconds' => 90,
        'telegram.health_retry_delay_ms' => 0,
        'telegram.force_ipv4' => true,
    ]);

    Queue::fake();
    Mail::fake();
});

afterEach(function (): void {
    DB::rollBack();
});

function telegramTestUser(?string $email = null, string $status = 'ACTIVO'): User
{
    return User::factory()->create([
        'email' => $email ?? 'pest.'.uniqid().'@tudrencasa.com',
        'status' => $status,
        'phone' => '04141234567',
    ]);
}

function telegramTestSetting(TelegramDeliveryMode $mode, array $emails = [], array $phones = [], array $groupIds = [], bool $active = true): SystemNotificationRecipientSetting
{
    $setting = SystemNotificationRecipientSetting::for(SystemNotificationKey::TelegramTest);
    $setting->update([
        'is_active' => $active,
        'notification_emails' => $emails,
        'notification_phones' => $phones,
        'telegram_mode' => $mode->value,
        'telegram_group_ids' => $groupIds,
    ]);

    return $setting->refresh();
}

function telegramTestNotification(bool $critical = false): InternalNotification
{
    return new InternalNotification(SystemNotificationKey::TelegramTest, 'Prueba de notificación a Telegram', 'Cuerpo de prueba', $critical, 'pest', false);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function telegramTestDelivery(TelegramRecipient $recipient, array $overrides = []): NotificationDelivery
{
    return NotificationDelivery::query()->create([
        'uuid' => (string) Illuminate\Support\Str::uuid(),
        'notification_key' => SystemNotificationKey::TelegramTest->value,
        'channel' => NotificationDeliveryChannel::Telegram,
        'status' => NotificationDeliveryStatus::Pending,
        'telegram_recipient_id' => $recipient->getKey(),
        'target' => $recipient->chat_id,
        'target_label' => $recipient->name,
        'title' => 'Prueba de notificación a Telegram',
        'body' => 'Cuerpo de prueba',
        ...$overrides,
    ]);
}

// ── Piezas puras ──────────────────────────────────────────────────────────

it('clasifica los errores de Telegram según lo que hay que hacer', function (array $payload, int $http, string $kind, bool $retryable, bool $disables): void {
    $exception = TelegramApiException::fromResponse($http, $payload);

    expect($exception->kind)->toBe($kind)
        ->and($exception->isRetryable())->toBe($retryable)
        ->and($exception->disablesRecipient())->toBe($disables);
})->with([
    'bloqueó el bot' => [['ok' => false, 'error_code' => 403, 'description' => 'Forbidden: bot was blocked by the user'], 403, TelegramApiException::KIND_BLOCKED, false, true],
    'chat inexistente' => [['ok' => false, 'error_code' => 400, 'description' => 'Bad Request: chat not found'], 400, TelegramApiException::KIND_CHAT_NOT_FOUND, false, true],
    'pide frenar' => [['ok' => false, 'error_code' => 429, 'description' => 'Too Many Requests', 'parameters' => ['retry_after' => 7]], 429, TelegramApiException::KIND_RATE_LIMITED, true, false],
    'supergrupo' => [['ok' => false, 'error_code' => 400, 'description' => 'Bad Request: group chat was upgraded', 'parameters' => ['migrate_to_chat_id' => -100123]], 400, TelegramApiException::KIND_MIGRATED, true, false],
    'token inválido' => [['ok' => false, 'error_code' => 401, 'description' => 'Unauthorized'], 401, TelegramApiException::KIND_UNAUTHORIZED, false, false],
    'caída de Telegram' => [['ok' => false, 'error_code' => 502, 'description' => 'Bad Gateway'], 502, TelegramApiException::KIND_TRANSIENT, true, false],
    'HTML mal formado' => [['ok' => false, 'error_code' => 400, 'description' => "Bad Request: can't parse entities"], 400, TelegramApiException::KIND_PERMANENT, false, false],
]);

it('toma de Telegram la espera exacta cuando pide frenar', function (): void {
    $exception = TelegramApiException::fromResponse(429, ['error_code' => 429, 'description' => 'Too Many Requests', 'parameters' => ['retry_after' => 42]]);

    expect($exception->retryAfter)->toBe(42);
});

it('escapa el HTML de lo dinámico para que un nombre no rompa el mensaje', function (): void {
    expect(TelegramText::notification('Alerta <b>falsa</b> & más', 'Paciente <script>'))
        ->toBe('<b>Alerta &lt;b&gt;falsa&lt;/b&gt; &amp; más</b>'."\n\n".'Paciente &lt;script&gt;');
});

it('reconoce el mismo teléfono escrito de distintas formas', function (string $phone): void {
    expect(InternalContactResolver::phoneKey($phone))->toBe('4141234567');
})->with(['04141234567', '+58 414-1234567', '584141234567', '(0414) 123 45 67']);

it('nunca permite enviar una clave OTP a un grupo', function (): void {
    expect(SystemNotificationKey::TelemedicineServiceLimitOverride->allowsTelegramGroups())->toBeFalse()
        ->and(SystemNotificationKey::CorporatePopulationUpdate->allowsTelegramGroups())->toBeFalse()
        ->and(SystemNotificationKey::TelegramTest->allowsTelegramGroups())->toBeTrue()
        ->and(SystemNotificationKey::TelegramTest->supportsTelegram())->toBeTrue()
        ->and(SystemNotificationKey::LiveSystemAlert->supportsTelegram())->toBeTrue()
        ->and(collect(SystemNotificationKey::managed())->every(fn (SystemNotificationKey $key): bool => $key->supportsTelegram()))->toBeTrue();
});

it('guarda el destino de Telegram solo en avisos migrados y nunca grupos en un OTP', function (): void {
    $method = new ReflectionMethod(App\Filament\Business\Pages\ManageCompanyAssociateNotifications::class, 'telegramStateFromForm');

    expect($method->invoke(null, SystemNotificationKey::TelegramTest, ['telegram_mode' => 'both', 'telegram_group_ids' => ['5', '5', '0', 'x'], 'telegram_recipient_ids' => ['7', '7', 'x']]))
        ->toBe(['telegram_mode' => 'both', 'telegram_group_ids' => [5], 'telegram_recipient_ids' => [7]])
        ->and($method->invoke(null, SystemNotificationKey::TelegramTest, ['telegram_mode' => 'person', 'telegram_group_ids' => [5], 'telegram_recipient_ids' => [7]]))
        ->toBe(['telegram_mode' => 'person', 'telegram_group_ids' => [], 'telegram_recipient_ids' => [7]])
        ->and($method->invoke(null, SystemNotificationKey::TelegramTest, ['telegram_mode' => 'group', 'telegram_group_ids' => [5], 'telegram_recipient_ids' => [7]]))
        ->toBe(['telegram_mode' => 'group', 'telegram_group_ids' => [5], 'telegram_recipient_ids' => []])
        ->and($method->invoke(null, SystemNotificationKey::TelegramTest, ['telegram_mode' => 'inventado']))
        ->toBe(['telegram_mode' => 'off', 'telegram_group_ids' => [], 'telegram_recipient_ids' => []])
        ->and($method->invoke(null, SystemNotificationKey::CorporatePopulationUpdate, ['telegram_mode' => 'group', 'telegram_group_ids' => [5]]))
        ->toBe(['telegram_mode' => 'person', 'telegram_group_ids' => [], 'telegram_recipient_ids' => []])
        ->and($method->invoke(null, SystemNotificationKey::HelpdeskNotices, ['telegram_mode' => 'person', 'telegram_recipient_ids' => [7]]))
        ->toBe(['telegram_mode' => 'person', 'telegram_group_ids' => [], 'telegram_recipient_ids' => []]);
});

// ── Vinculación ───────────────────────────────────────────────────────────

it('vincula a una persona del personal con un enlace de un solo uso', function (): void {
    $user = telegramTestUser();
    $link = TelegramLinkService::issueForUser($user, 'pest');

    expect($link['url'])->toStartWith('https://t.me/integracorp_test_bot?start=');

    $token = substr($link['url'], strpos($link['url'], '=') + 1);
    $result = TelegramLinkService::consume($token, '555001', 'pestuser');

    $recipient = TelegramRecipient::query()->firstWhere('user_id', $user->getKey());

    expect($result->linked)->toBeTrue()
        ->and($recipient->status)->toBe(TelegramRecipientStatus::Active)
        ->and($recipient->chat_id)->toBe('555001')
        ->and($recipient->telegram_username)->toBe('pestuser')
        ->and(TelegramLinkToken::query()->where('token_hash', hash('sha256', $token))->value('used_at'))->not->toBeNull();

    $again = TelegramLinkService::consume($token, '555001', 'pestuser');

    expect($again->linked)->toBeFalse()->and($again->message)->toContain('ya se usó');
});

it('guarda solo el hash del código, nunca el código en claro', function (): void {
    $link = TelegramLinkService::issueForUser(telegramTestUser(), 'pest');
    $token = substr($link['url'], strpos($link['url'], '=') + 1);

    expect(TelegramLinkToken::query()->where('token_hash', $token)->exists())->toBeFalse()
        ->and(TelegramLinkToken::query()->where('token_hash', hash('sha256', $token))->exists())->toBeTrue();
});

it('rechaza un enlace vencido', function (): void {
    $link = TelegramLinkService::issueForUser(telegramTestUser(), 'pest');
    $token = substr($link['url'], strpos($link['url'], '=') + 1);
    TelegramLinkToken::query()->where('token_hash', hash('sha256', $token))->update(['expires_at' => now()->subMinute()]);

    $result = TelegramLinkService::consume($token, '555002');

    expect($result->linked)->toBeFalse()->and($result->message)->toContain('venció');
});

it('invalida el enlace anterior al generar uno nuevo', function (): void {
    $user = telegramTestUser();
    $first = TelegramLinkService::issueForUser($user, 'pest');
    TelegramLinkService::issueForUser($user, 'pest');

    $firstToken = substr($first['url'], strpos($first['url'], '=') + 1);

    expect(TelegramLinkService::consume($firstToken, '555003')->linked)->toBeFalse();
});

it('no deja vincular a alguien fuera del personal ni a un usuario inactivo', function (): void {
    expect(fn () => TelegramLinkService::issueForUser(telegramTestUser('alguien@gmail.com'), 'pest'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => TelegramLinkService::issueForUser(telegramTestUser(null, 'INACTIVO'), 'pest'))->toThrow(InvalidArgumentException::class);
});

it('no deja que un mismo Telegram quede vinculado a dos personas', function (): void {
    TelegramRecipient::factory()->create(['chat_id' => '777000', 'name' => 'Ya vinculado']);

    $link = TelegramLinkService::issueForUser(telegramTestUser(), 'pest');
    $result = TelegramLinkService::consume(substr($link['url'], strpos($link['url'], '=') + 1), '777000');

    expect($result->linked)->toBeFalse()->and($result->message)->toContain('Ya vinculado');
});

it('rechaza códigos con formato inválido sin tocar la base', function (string $token): void {
    expect(TelegramLinkService::consume($token, '1')->linked)->toBeFalse();
})->with(['', 'corto', str_repeat('a', 80), 'con espacios y símbolos !!!!!!!!']);

// ── Lo que manda Telegram ─────────────────────────────────────────────────

it('registra un grupo nuevo como pendiente de aprobación y no le envía avisos', function (): void {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);

    TelegramUpdateHandler::make()->handle([
        'update_id' => 900001,
        'my_chat_member' => [
            'chat' => ['id' => -100555, 'type' => 'supergroup', 'title' => 'Operaciones – Alertas'],
            'from' => ['first_name' => 'Ana', 'last_name' => 'Pérez'],
            'new_chat_member' => ['status' => 'member'],
        ],
    ]);

    $group = TelegramRecipient::query()->groups()->firstWhere('chat_id', '-100555');

    expect($group)->not->toBeNull()
        ->and($group->status)->toBe(TelegramRecipientStatus::PendingApproval)
        ->and($group->name)->toBe('Operaciones – Alertas')
        ->and($group->canReceive())->toBeFalse();
});

it('ignora una actualización repetida de Telegram', function (): void {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []])]);
    $update = ['update_id' => 900002, 'my_chat_member' => ['chat' => ['id' => -100556, 'type' => 'group', 'title' => 'Uno'], 'new_chat_member' => ['status' => 'member']]];

    TelegramUpdateHandler::make()->handle($update);
    TelegramRecipient::query()->groups()->where('chat_id', '-100556')->update(['name' => 'Renombrado a mano']);
    TelegramUpdateHandler::make()->handle($update);

    expect(TelegramRecipient::query()->groups()->where('chat_id', '-100556')->count())->toBe(1)
        ->and(TelegramRecipient::query()->groups()->where('chat_id', '-100556')->value('name'))->toBe('Renombrado a mano');
});

it('marca el grupo cuando sacan al bot y la persona cuando lo bloquea', function (): void {
    $group = TelegramRecipient::factory()->group()->create(['chat_id' => '-100557']);
    $person = TelegramRecipient::factory()->create(['chat_id' => '557557']);

    $handler = TelegramUpdateHandler::make();
    $handler->handle(['update_id' => 900003, 'my_chat_member' => ['chat' => ['id' => -100557, 'type' => 'supergroup'], 'new_chat_member' => ['status' => 'left']]]);
    $handler->handle(['update_id' => 900004, 'my_chat_member' => ['chat' => ['id' => 557557, 'type' => 'private'], 'new_chat_member' => ['status' => 'kicked']]]);

    expect($group->refresh()->status)->toBe(TelegramRecipientStatus::Blocked)
        ->and($person->refresh()->status)->toBe(TelegramRecipientStatus::Blocked);
});

it('actualiza el chat_id cuando Telegram convierte el grupo en supergrupo', function (): void {
    $group = TelegramRecipient::factory()->group()->create(['chat_id' => '-4001']);

    TelegramUpdateHandler::make()->handle(['update_id' => 900005, 'message' => ['chat' => ['id' => -4001, 'type' => 'group'], 'migrate_to_chat_id' => -1004001]]);

    expect($group->refresh()->chat_id)->toBe('-1004001');
});

it('vincula desde /start y responde en el chat', function (): void {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 10]])]);
    $link = TelegramLinkService::issueForUser(telegramTestUser(), 'pest');
    $token = substr($link['url'], strpos($link['url'], '=') + 1);

    TelegramUpdateHandler::make()->handle([
        'update_id' => 900006,
        'message' => ['chat' => ['id' => 600600, 'type' => 'private'], 'from' => ['username' => 'nuevo'], 'text' => '/start '.$token],
    ]);

    expect(TelegramRecipient::query()->where('chat_id', '600600')->first()?->status)->toBe(TelegramRecipientStatus::Active);
    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/sendMessage') && str_contains((string) $request['text'], 'vinculado'));
});

it('rechaza el webhook sin la clave secreta', function (): void {
    $this->postJson('/telegram/webhook', ['update_id' => 1])->assertForbidden();
    $this->postJson('/telegram/webhook', ['update_id' => 1], ['X-Telegram-Bot-Api-Secret-Token' => 'otra-clave'])->assertForbidden();
});

it('acepta el webhook con la clave secreta', function (): void {
    $this->postJson('/telegram/webhook', ['update_id' => 900007], ['X-Telegram-Bot-Api-Secret-Token' => 'secreto-de-prueba-1234567890'])
        ->assertSuccessful()
        ->assertJson(['ok' => true]);
});

// ── Reparto del aviso ─────────────────────────────────────────────────────

it('con Telegram apagado envía por WhatsApp y correo como siempre', function (): void {
    telegramTestSetting(TelegramDeliveryMode::Off, ['analista@tudrencasa.com'], ['04141112233']);

    $summary = InternalNotificationDispatcher::dispatch(telegramTestNotification());

    expect($summary['telegram'])->toBe(0)
        ->and($summary['whatsapp'])->toBe(1)
        ->and($summary['mail'])->toBe(1)
        ->and(NotificationDelivery::query()->where('id', '>', $this->deliveryFloor)->where('channel', 'telegram')->count())->toBe(0);
});

it('en modo persona usa Telegram en vez de WhatsApp para los vinculados y el correo no cambia', function (): void {
    $linked = TelegramRecipient::factory()->create(['email' => 'vinculado@tudrencasa.com', 'phone' => '04145550000']);
    telegramTestSetting(TelegramDeliveryMode::Person, ['vinculado@tudrencasa.com', 'sinvincular@tudrencasa.com'], ['0414-555-0000', '04249998877']);

    $summary = InternalNotificationDispatcher::dispatch(telegramTestNotification());

    /** Telegram reemplaza al WhatsApp del vinculado; los dos correos se siguen enviando. */
    expect($summary['telegram'])->toBe(1)
        ->and($summary['whatsapp'])->toBe(1)
        ->and($summary['mail'])->toBe(2)
        ->and(NotificationDelivery::query()->where('id', '>', $this->deliveryFloor)->where('channel', 'telegram')->value('telegram_recipient_id'))->toBe($linked->getKey())
        ->and(NotificationDelivery::query()->where('id', '>', $this->deliveryFloor)->where('channel', 'whatsapp')->value('target'))->toBe('04249998877');
});

it('en modo grupo no envía WhatsApp, sí el correo, y registra los grupos inactivos como omitidos', function (): void {
    $active = TelegramRecipient::factory()->group()->create();
    $pending = TelegramRecipient::factory()->group()->pendingApproval()->create();
    telegramTestSetting(TelegramDeliveryMode::Group, ['alguien@tudrencasa.com'], ['04141112233'], [$active->getKey(), $pending->getKey()]);

    $summary = InternalNotificationDispatcher::dispatch(telegramTestNotification());

    expect($summary['groups'])->toBe(1)
        ->and($summary['skipped'])->toBe(1)
        ->and($summary['whatsapp'])->toBe(0)
        ->and($summary['mail'])->toBe(1)
        ->and(NotificationDelivery::query()->where('id', '>', $this->deliveryFloor)->where('status', 'skipped')->value('telegram_recipient_id'))->toBe($pending->getKey());
});

it('si Telegram no tiene token, todo sale por los canales de siempre aunque esté configurado', function (): void {
    config(['telegram.bot_token' => null]);
    TelegramRecipient::factory()->create(['email' => 'vinculado@tudrencasa.com']);
    telegramTestSetting(TelegramDeliveryMode::Person, ['vinculado@tudrencasa.com']);

    $summary = InternalNotificationDispatcher::dispatch(telegramTestNotification());

    expect($summary['mode'])->toBe(TelegramDeliveryMode::Off)
        ->and($summary['telegram'])->toBe(0)
        ->and($summary['mail'])->toBe(1);
});

it('no envía nada si el aviso está pausado', function (): void {
    telegramTestSetting(TelegramDeliveryMode::Person, ['a@tudrencasa.com'], ['04141112233'], [], false);

    $summary = InternalNotificationDispatcher::dispatch(telegramTestNotification());

    expect($summary['paused'])->toBeTrue()
        ->and(NotificationDelivery::query()->where('id', '>', $this->deliveryFloor)->count())->toBe(0);
});

it('no usa un contacto vencido ni un usuario dado de baja', function (): void {
    TelegramRecipient::factory()->create(['email' => 'vencido@externo.com', 'expires_at' => now()->subDay()]);
    $user = telegramTestUser(null, 'INACTIVO');
    TelegramRecipient::factory()->create(['type' => TelegramRecipientType::User, 'user_id' => $user->getKey(), 'email' => $user->email]);
    telegramTestSetting(TelegramDeliveryMode::Person, ['vencido@externo.com', $user->email]);

    $summary = InternalNotificationDispatcher::dispatch(telegramTestNotification());

    expect($summary['telegram'])->toBe(0)->and($summary['mail'])->toBe(2);
});

// ── Entrega y respaldo ────────────────────────────────────────────────────

it('marca la entrega como enviada con el id del mensaje de Telegram', function (): void {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 4321]])]);
    $recipient = TelegramRecipient::factory()->create();
    $delivery = telegramTestDelivery($recipient);

    (new SendTelegramDeliveryJob($delivery->getKey()))->handle();

    expect($delivery->refresh()->status)->toBe(NotificationDeliveryStatus::Sent)
        ->and($delivery->provider_message_id)->toBe('4321')
        ->and($delivery->attempts)->toBe(1)
        ->and($recipient->refresh()->last_sent_at)->not->toBeNull();

    Http::assertSent(fn ($request): bool => $request['chat_id'] === $recipient->chat_id && $request['parse_mode'] === 'HTML' && $request['protect_content'] === true);
});

it('no vuelve a enviar una entrega ya resuelta', function (): void {
    Http::fake();
    $delivery = telegramTestDelivery(TelegramRecipient::factory()->create(), ['status' => NotificationDeliveryStatus::Sent]);

    (new SendTelegramDeliveryJob($delivery->getKey()))->handle();

    Http::assertNothingSent();
});

it('si la persona bloqueó el bot la marca y le envía el aviso por WhatsApp', function (): void {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => false, 'error_code' => 403, 'description' => 'Forbidden: bot was blocked by the user'], 403)]);
    $recipient = TelegramRecipient::factory()->create(['phone' => '04140001122']);
    $delivery = telegramTestDelivery($recipient);

    (new SendTelegramDeliveryJob($delivery->getKey()))->handle();

    expect($delivery->refresh()->status)->toBe(NotificationDeliveryStatus::FellBack)
        ->and($recipient->refresh()->status)->toBe(TelegramRecipientStatus::Blocked)
        ->and($delivery->fallbacks()->pluck('channel')->map->value->all())->toBe(['whatsapp']);

    Queue::assertPushed(SendNotificacionWhatsApp::class, fn (SendNotificacionWhatsApp $job): bool => $job->phone === '04140001122');
});

it('el respaldo no repite el correo, que el aviso ya envió por su cuenta', function (): void {
    $recipient = TelegramRecipient::factory()->create(['phone' => '04140001122', 'email' => 'critico@tudrencasa.com']);
    $delivery = telegramTestDelivery($recipient, ['is_critical' => true]);

    InternalNotificationFallback::run($delivery->getKey(), 'prueba');

    expect($delivery->fallbacks()->pluck('channel')->map->value->all())->toBe(['whatsapp']);
});

it('sin teléfono, el respaldo de una persona sale por correo', function (): void {
    $delivery = telegramTestDelivery(TelegramRecipient::factory()->create(['phone' => null, 'email' => 'sintelefono@tudrencasa.com']));

    InternalNotificationFallback::run($delivery->getKey(), 'prueba');

    expect($delivery->fallbacks()->pluck('channel')->map->value->all())->toBe(['mail']);
});

it('ante una caída transitoria reintenta sin pasar todavía al respaldo', function (): void {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => false, 'error_code' => 502, 'description' => 'Bad Gateway'], 502)]);
    $delivery = telegramTestDelivery(TelegramRecipient::factory()->create());

    (new SendTelegramDeliveryJob($delivery->getKey()))->handle();

    expect($delivery->refresh()->status)->toBe(NotificationDeliveryStatus::Pending)
        ->and($delivery->attempts)->toBe(1)
        ->and($delivery->fallbacks()->count())->toBe(0);
});

it('agotados los intentos transitorios pasa al respaldo', function (): void {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => false, 'error_code' => 502, 'description' => 'Bad Gateway'], 502)]);
    $delivery = telegramTestDelivery(TelegramRecipient::factory()->create(['phone' => '04140001122']), ['attempts' => 2]);

    (new SendTelegramDeliveryJob($delivery->getKey()))->handle();

    expect($delivery->refresh()->status)->toBe(NotificationDeliveryStatus::FellBack)
        ->and($delivery->attempts)->toBe(3)
        ->and($delivery->error)->toContain('3 intentos');
});

it('cuando Telegram pide frenar espera sin gastar el respaldo', function (): void {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => false, 'error_code' => 429, 'description' => 'Too Many Requests', 'parameters' => ['retry_after' => 3]], 429)]);
    $delivery = telegramTestDelivery(TelegramRecipient::factory()->create(), ['attempts' => 2]);

    (new SendTelegramDeliveryJob($delivery->getKey()))->handle();

    expect($delivery->refresh()->status)->toBe(NotificationDeliveryStatus::Pending)->and($delivery->fallbacks()->count())->toBe(0);
});

it('pasa al respaldo una sola vez aunque se llame dos veces', function (): void {
    $delivery = telegramTestDelivery(TelegramRecipient::factory()->create(['phone' => '04140001122']));

    InternalNotificationFallback::run($delivery->getKey(), 'primera');
    InternalNotificationFallback::run($delivery->getKey(), 'segunda');

    expect($delivery->fallbacks()->count())->toBe(1)->and($delivery->refresh()->error)->toBe('primera');
});

it('sin teléfono ni correo la entrega queda fallida y visible', function (): void {
    $delivery = telegramTestDelivery(TelegramRecipient::factory()->create(['phone' => null, 'email' => null]));

    InternalNotificationFallback::run($delivery->getKey(), 'sin datos');

    expect($delivery->refresh()->status)->toBe(NotificationDeliveryStatus::Failed);
});

it('si el grupo falla avisa a los contactos del aviso por WhatsApp', function (): void {
    $group = TelegramRecipient::factory()->group()->create();
    telegramTestSetting(TelegramDeliveryMode::Group, ['grupo@tudrencasa.com'], ['04141112233'], [$group->getKey()]);
    $delivery = telegramTestDelivery($group);

    InternalNotificationFallback::run($delivery->getKey(), 'grupo caído');

    expect($delivery->fallbacks()->pluck('target')->all())->toBe(['04141112233']);
});

it('si el destino ya no puede recibir, el job pasa al respaldo sin llamar a Telegram', function (): void {
    Http::fake();
    $delivery = telegramTestDelivery(TelegramRecipient::factory()->create(['status' => TelegramRecipientStatus::Disabled, 'phone' => '04140001122']));

    (new SendTelegramDeliveryJob($delivery->getKey()))->handle();

    Http::assertNothingSent();
    expect($delivery->refresh()->status)->toBe(NotificationDeliveryStatus::FellBack);
});

it('el token del bot nunca aparece en el error guardado', function (): void {
    Http::fake(fn () => throw new Illuminate\Http\Client\ConnectionException('cURL error 28 for https://api.telegram.org/bot123456:TEST-TOKEN/sendMessage'));
    $delivery = telegramTestDelivery(TelegramRecipient::factory()->create());

    (new SendTelegramDeliveryJob($delivery->getKey()))->handle();

    expect((string) $delivery->refresh()->error)->not->toContain('TEST-TOKEN')->toContain('***');
});

// ── Vigilancia ────────────────────────────────────────────────────────────

it('alerta cuando el bot no responde y no alerta si Telegram está apagado', function (): void {
    Cache::forget('telegram:health');

    $events = SystemHealthWatcher::telegramProblems(['ok' => false, 'error' => 'Sin conexión', 'error_kind' => 'transient']);

    expect(collect($events)->pluck('type')->all())->toContain('telegram_down');

    config(['telegram.enabled' => false]);

    expect(SystemHealthWatcher::telegramProblems(['ok' => false]))->toBe([]);
});

it('conecta a Telegram solo por IPv4 salvo que se desactive', function (): void {
    expect(App\Support\Telegram\TelegramBotClient::make()->transportOptions())->toBe(['force_ip_resolve' => 'v4']);

    config(['telegram.force_ipv4' => false]);

    expect(App\Support\Telegram\TelegramBotClient::make()->transportOptions())->toBe([]);
});

it('un corte de red momentáneo se reintenta y no deja el bot como caído', function (): void {
    Http::fake(['api.telegram.org/*' => Http::sequence()
        ->pushFailedConnection('cURL error 28: Failed to connect to api.telegram.org port 443 after 5000 ms')
        ->push(['ok' => true, 'result' => ['username' => 'integracorp_test_bot']])
        ->push(['ok' => true, 'result' => ['url' => 'https://example.test/telegram/webhook', 'pending_update_count' => 0]]),
    ]);

    $health = App\Support\Telegram\TelegramHealth::probe(App\Support\Telegram\TelegramBotClient::make());

    expect($health['ok'])->toBeTrue()
        ->and($health['bot_username'])->toBe('integracorp_test_bot')
        ->and(SystemHealthWatcher::telegramProblems($health))->not->toContain(fn ($event) => $event['type'] === 'telegram_down');
    Http::assertSentCount(3);
});

it('si el corte sigue en el reintento sí alerta, sin exponer el token', function (): void {
    Http::fake(fn () => throw new Illuminate\Http\Client\ConnectionException('cURL error 28 for https://api.telegram.org/bot123456:TEST-TOKEN/getMe'));

    $health = App\Support\Telegram\TelegramHealth::probe(App\Support\Telegram\TelegramBotClient::make());

    expect($health['ok'])->toBeFalse()
        ->and($health['error_kind'])->toBe(TelegramApiException::KIND_TRANSIENT)
        ->and($health['error'])->not->toContain('TEST-TOKEN')
        ->and(collect(SystemHealthWatcher::telegramProblems($health))->pluck('type')->all())->toContain('telegram_down');
});

it('un token inválido no se reintenta: se informa al primer intento', function (): void {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => false, 'error_code' => 401, 'description' => 'Unauthorized'], 401)]);

    $health = App\Support\Telegram\TelegramHealth::probe(App\Support\Telegram\TelegramBotClient::make());

    expect($health['ok'])->toBeFalse()
        ->and($health['error_kind'])->toBe(TelegramApiException::KIND_UNAUTHORIZED);
    Http::assertSentCount(1);
});

it('alerta cuando hay avisos internos atascados', function (): void {
    $delivery = telegramTestDelivery(TelegramRecipient::factory()->create());
    $delivery->forceFill(['created_at' => now()->subMinutes(30)])->save();

    $events = SystemHealthWatcher::telegramProblems(['ok' => true]);

    expect(collect($events)->pluck('type')->all())->toContain('notifications_undelivered');
});

// ── Permisos y registro en paneles ────────────────────────────────────────

it('la página Telegram y la bitácora son solo para SUPERADMIN', function (string $path): void {
    $source = (string) file_get_contents(dirname(__DIR__, 2).'/'.$path);

    expect($source)->toContain('UserNavigationAccess::isSuperAdmin($user)')
        ->toContain('public static function canAccess(): bool')
        ->toContain("whereRaw('1 = 0')")
        ->toContain('public static function canCreate(): bool');
})->with([
    'app/Filament/Business/Resources/TelegramRecipients/TelegramRecipientResource.php',
    'app/Filament/Business/Resources/NotificationDeliveries/NotificationDeliveryResource.php',
]);

it('Conectar Telegram está en el menú de los 8 paneles internos y en ningún panel comercial', function (): void {
    $root = dirname(__DIR__, 2).'/app/Providers/Filament/';

    foreach (['Admin', 'Business', 'Operations', 'Administration', 'Marketing', 'Telemedicina', 'Projects', 'Metrics'] as $panel) {
        $source = (string) file_get_contents($root.$panel.'PanelProvider.php');

        expect($source)->toContain('ConnectTelegram::class')->toContain('TelegramUserMenu::action()');
    }

    foreach (['Agents', 'Master', 'General'] as $panel) {
        expect((string) file_get_contents($root.$panel.'PanelProvider.php'))->not->toContain('ConnectTelegram');
    }
});

it('solo el personal activo del dominio interno puede abrir Conectar Telegram', function (): void {
    $page = App\Filament\Shared\Telegram\Pages\ConnectTelegram::class;

    expect($page::userCanConnect(User::factory()->make(['email' => 'x@tudrencasa.com', 'status' => 'ACTIVO'])))->toBeTrue()
        ->and($page::userCanConnect(User::factory()->make(['email' => 'x@gmail.com', 'status' => 'ACTIVO'])))->toBeFalse()
        ->and($page::userCanConnect(User::factory()->make(['email' => 'x@tudrencasa.com', 'status' => 'INACTIVO'])))->toBeFalse()
        ->and($page::userCanConnect(null))->toBeFalse();
});

it('la cola notifications está en la lista del monitor de colas', function (): void {
    $config = require dirname(__DIR__, 2).'/config/live-presence.php';

    expect($config['queues']['names'])->toContain('notifications');
});

// ── Pantallas ─────────────────────────────────────────────────────────────

function telegramTestActAsSuperAdmin(object $test, string $panel = 'business'): User
{
    $user = User::factory()->create([
        'name' => 'QA TELEGRAM',
        'email' => 'qa.telegram@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['SUPERADMIN', 'NEGOCIOS'],
    ]);

    $test->actingAs($user);
    Filament\Facades\Filament::setCurrentPanel($panel);

    return $user;
}

it('la página Telegram se dibuja con sus pestañas y la bitácora también', function (): void {
    telegramTestActAsSuperAdmin($this);
    TelegramRecipient::factory()->group()->pendingApproval()->create(['name' => 'Grupo QA pendiente']);

    Livewire\Livewire::test(App\Filament\Business\Resources\TelegramRecipients\Pages\ListTelegramRecipients::class)
        ->assertSuccessful()
        ->assertSee('Grupos por aprobar')
        ->assertSee('Grupo QA pendiente');

    Livewire\Livewire::test(App\Filament\Business\Resources\NotificationDeliveries\Pages\ListNotificationDeliveries::class)
        ->assertSuccessful()
        ->assertSee('Cada aviso interno queda registrado antes de salir');
});

it('la página Telegram muestra el enlace con QR al agregar un contacto externo', function (): void {
    telegramTestActAsSuperAdmin($this);

    Livewire\Livewire::test(App\Filament\Business\Resources\TelegramRecipients\Pages\ListTelegramRecipients::class)
        ->mountAction('addExternal')
        ->set('mountedActions.0.data.name', 'Auditor Externo QA')
        ->set('mountedActions.0.data.email', 'auditor@externo.com')
        ->set('mountedActions.0.data.reason', 'Revisión de alertas')
        ->callMountedAction()
        ->assertHasNoErrors()
        ->assertActionMounted('showTelegramLink')
        ->assertSet('mountedActions.0.arguments.url', fn (?string $url): bool => is_string($url) && str_starts_with($url, 'https://t.me/integracorp_test_bot?start='));

    expect(TelegramRecipient::query()->where('name', 'Auditor Externo QA')->first())
        ->type->toBe(TelegramRecipientType::External)
        ->status->toBe(TelegramRecipientStatus::PendingLink);
});

it('la ventana del enlace muestra el QR, el enlace y su vencimiento', function (): void {
    $html = view('filament.business.telegram.link-modal', [
        'url' => 'https://t.me/integracorp_test_bot?start=abc',
        'code' => '482913',
        'bot' => 'integracorp_test_bot',
        'expiresAt' => '10:15 AM',
        'qr' => '<svg data-qr="1"></svg>',
    ])->render();

    expect($html)->toContain('<svg data-qr="1"></svg>')
        ->toContain('https://t.me/integracorp_test_bot?start=abc')
        ->toContain('482 913')
        ->toContain('@integracorp_test_bot')
        ->toContain('vencen a las 10:15 AM');
});

it('aprobar un grupo lo deja activo y registra quién lo aprobó', function (): void {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);
    $admin = telegramTestActAsSuperAdmin($this);
    $group = TelegramRecipient::factory()->group()->pendingApproval()->create();

    Livewire\Livewire::test(App\Filament\Business\Resources\TelegramRecipients\Pages\ListTelegramRecipients::class)
        ->callTableAction('approveGroup', $group)
        ->assertHasNoTableActionErrors();

    expect($group->refresh()->status)->toBe(TelegramRecipientStatus::Active)
        ->and($group->approved_by)->toBe($admin->getKey())
        ->and($group->approved_at)->not->toBeNull();
});

it('el Centro de notificaciones muestra la sección Telegram solo en la prueba y envía la prueba', function (): void {
    telegramTestActAsSuperAdmin($this);
    telegramTestSetting(TelegramDeliveryMode::Off, ['qa.telegram@tudrencasa.com'], ['04141112233']);

    $page = Livewire\Livewire::test(App\Filament\Business\Pages\ManageCompanyAssociateNotifications::class)
        ->assertSuccessful()
        ->call('selectNotificationKey', SystemNotificationKey::OperationsAffiliateUpdate->value)
        ->assertSee('Enviar por Telegram a')
        ->assertDontSee('Enviar prueba');

    $page->call('selectNotificationKey', SystemNotificationKey::TelegramTest->value)
        ->assertSee('Enviar por Telegram a')
        ->assertSee('Enviar prueba')
        ->call('sendTelegramTest')
        ->assertNotified('Prueba en camino');

    expect(NotificationDelivery::query()->where('id', '>', $this->deliveryFloor)->where('notification_key', 'telegram_test')->pluck('channel')->map->value->sort()->values()->all())
        ->toBe(['mail', 'whatsapp']);
});

it('Conectar Telegram genera el enlace del usuario y confirma cuando queda vinculado', function (): void {
    $user = telegramTestUser('qa.conectar@tudrencasa.com');
    $this->actingAs($user);
    Filament\Facades\Filament::setCurrentPanel('business');

    $page = Livewire\Livewire::test(App\Filament\Shared\Telegram\Pages\ConnectTelegram::class)
        ->assertSuccessful()
        ->assertSee('Sin vincular')
        ->call('generateLink')
        ->assertSet('linkUrl', fn (?string $url): bool => is_string($url) && str_starts_with($url, 'https://t.me/integracorp_test_bot?start='));

    $url = (string) $page->get('linkUrl');
    TelegramLinkService::consume(substr($url, strpos($url, '=') + 1), '880880');

    $page->call('checkLinked')
        ->assertSet('linkUrl', null)
        ->assertNotified('¡Telegram vinculado!');
});

// ── Vincular sin escanear: código corto y enlace por WhatsApp ────────────

it('cada enlace trae un código de 6 dígitos que se guarda cifrado', function (): void {
    $link = TelegramLinkService::issueForUser(telegramTestUser(), 'pest');

    expect($link['code'])->toMatch('/^\d{6}$/');

    $row = TelegramLinkToken::query()->latest('id')->first();

    expect($row->short_code_hash)->toBe(TelegramLinkService::shortCodeHash($link['code']))
        ->and($row->short_code_hash)->not->toBe($link['code'])
        ->and($row->short_code_hash)->not->toBe(hash('sha256', $link['code']));
});

it('vincula escribiéndole el código al bot, con o sin espacio', function (string $format): void {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);
    $user = telegramTestUser();
    $code = TelegramLinkService::issueForUser($user, 'pest')['code'];
    $text = str_replace(['ABC', 'DEF'], [substr($code, 0, 3), substr($code, 3)], $format);

    TelegramUpdateHandler::make()->handle([
        'update_id' => random_int(910000, 919999),
        'message' => ['chat' => ['id' => 700700, 'type' => 'private'], 'text' => $text],
    ]);

    expect(TelegramRecipient::query()->firstWhere('user_id', $user->getKey())?->status)->toBe(TelegramRecipientStatus::Active);
    Http::assertSent(fn ($request): bool => str_contains((string) $request['text'], 'vinculado'));
})->with(['ABCDEF', 'ABC DEF', 'ABC-DEF', '/vincular ABCDEF']);

it('un código sirve una sola vez', function (): void {
    $code = TelegramLinkService::issueForUser(telegramTestUser(), 'pest')['code'];

    expect(TelegramLinkService::consumeShortCode($code, '710710')->linked)->toBeTrue()
        ->and(TelegramLinkService::consumeShortCode($code, '710711')->linked)->toBeFalse();
});

it('un código vencido no vincula', function (): void {
    $code = TelegramLinkService::issueForUser(telegramTestUser(), 'pest')['code'];
    TelegramLinkToken::query()->latest('id')->first()->forceFill(['expires_at' => now()->subMinute()])->save();

    expect(TelegramLinkService::consumeShortCode($code, '720720')->linked)->toBeFalse();
});

it('bloquea el chat tras 5 códigos incorrectos, aunque luego escriba el correcto', function (): void {
    $code = TelegramLinkService::issueForUser(telegramTestUser(), 'pest')['code'];
    $wrong = str_pad((string) (((int) $code + 1) % 1_000_000), 6, '0', STR_PAD_LEFT);
    Cache::forget('telegram:short-code-attempts:730730');

    foreach (range(1, TelegramLinkService::MAX_SHORT_CODE_ATTEMPTS) as $attempt) {
        expect(TelegramLinkService::consumeShortCode($wrong, '730730')->linked)->toBeFalse();
    }

    $blocked = TelegramLinkService::consumeShortCode($code, '730730');

    expect($blocked->linked)->toBeFalse()->and($blocked->message)->toContain('Demasiados códigos');

    Cache::forget('telegram:short-code-attempts:730730');
});

it('el bloqueo es por chat: otro chat sí puede usar su código', function (): void {
    Cache::put('telegram:short-code-attempts:740740', TelegramLinkService::MAX_SHORT_CODE_ATTEMPTS, 60);
    $code = TelegramLinkService::issueForUser(telegramTestUser(), 'pest')['code'];

    expect(TelegramLinkService::consumeShortCode($code, '740741')->linked)->toBeTrue();

    Cache::forget('telegram:short-code-attempts:740740');
});

it('rechaza códigos que no tienen 6 números', function (string $code): void {
    expect(TelegramLinkService::consumeShortCode($code, '750750')->linked)->toBeFalse();
})->with(['12345', '1234567', 'abcdef', '']);

it('envía el enlace al WhatsApp del propio usuario y no guarda el enlace en la bitácora', function (): void {
    $user = telegramTestUser();
    $link = TelegramLinkService::issueForUser($user, 'pest');

    $masked = App\Support\Telegram\TelegramLinkDelivery::send($user, 'whatsapp', $link['url'], $link['code'], $link['expires_at']);

    expect($masked)->toBe('0414•••4567');
    Queue::assertPushed(SendNotificacionWhatsApp::class, fn (SendNotificacionWhatsApp $job): bool => $job->phone === '04141234567'
        && str_contains($job->body, $link['url'])
        && str_contains($job->body, TelegramLinkService::formatShortCode($link['code'])));

    Queue::assertPushedOn('notifications', SendNotificacionWhatsApp::class);

    $delivery = NotificationDelivery::query()->where('notification_key', 'telegram_link')->latest('id')->first();

    expect($delivery->user_id)->toBe($user->getKey())
        ->and($delivery->body)->not->toContain($link['url'])
        ->and($delivery->body)->not->toContain($link['code']);

    Illuminate\Support\Facades\RateLimiter::clear('telegram-link-delivery:'.$user->getKey());
});

it('limita a 3 envíos del enlace cada 15 minutos por persona', function (): void {
    $user = telegramTestUser();
    $link = TelegramLinkService::issueForUser($user, 'pest');
    Illuminate\Support\Facades\RateLimiter::clear('telegram-link-delivery:'.$user->getKey());

    foreach (range(1, 3) as $attempt) {
        App\Support\Telegram\TelegramLinkDelivery::send($user, 'mail', $link['url'], $link['code'], $link['expires_at']);
    }

    expect(fn () => App\Support\Telegram\TelegramLinkDelivery::send($user, 'mail', $link['url'], $link['code'], $link['expires_at']))
        ->toThrow(InvalidArgumentException::class, 'Ya se le enviaron 3 enlaces');

    Illuminate\Support\Facades\RateLimiter::clear('telegram-link-delivery:'.$user->getKey());
});

it('sin teléfono en el usuario explica que use el correo', function (): void {
    $user = telegramTestUser();
    $user->forceFill(['phone' => null])->save();
    $link = TelegramLinkService::issueForUser($user, 'pest');

    expect(fn () => App\Support\Telegram\TelegramLinkDelivery::send($user, 'whatsapp', $link['url'], $link['code'], $link['expires_at']))
        ->toThrow(InvalidArgumentException::class, 'no tiene teléfono');
});

it('Conectar Telegram muestra el código y envía el enlace por WhatsApp desde la página', function (): void {
    $user = telegramTestUser('qa.whatsapp@tudrencasa.com');
    Illuminate\Support\Facades\RateLimiter::clear('telegram-link-delivery:'.$user->getKey());
    $this->actingAs($user);
    Filament\Facades\Filament::setCurrentPanel('business');

    $page = Livewire\Livewire::test(App\Filament\Shared\Telegram\Pages\ConnectTelegram::class)
        ->call('generateLink')
        ->assertSet('linkCode', fn (?string $code): bool => is_string($code) && preg_match('/^\d{6}$/', $code) === 1);

    $code = (string) $page->get('linkCode');

    $page->assertSee(TelegramLinkService::formatShortCode($code))
        ->assertSee('@integracorp_test_bot')
        ->assertSee('Enviarme el enlace por WhatsApp')
        ->callAction('sendLinkByWhatsApp')
        ->assertNotified('Revise su WhatsApp');

    Queue::assertPushed(SendNotificacionWhatsApp::class, fn (SendNotificacionWhatsApp $job): bool => $job->phone === '04141234567' && str_contains($job->body, TelegramLinkService::formatShortCode($code)));

    Illuminate\Support\Facades\RateLimiter::clear('telegram-link-delivery:'.$user->getKey());
});

// ── Entrega B1: alertas del sistema por Telegram ─────────────────────────

function telegramTestRoute(SystemNotificationKey $key, TelegramDeliveryMode $mode, array $groupIds = []): void
{
    SystemNotificationRecipientSetting::for($key)->update([
        'is_active' => true,
        'telegram_mode' => $mode->value,
        'telegram_group_ids' => $groupIds,
    ]);
}

it('con el aviso en Apagado devuelve todos los teléfonos y no registra nada', function (): void {
    TelegramRecipient::factory()->create(['phone' => '04161110000']);
    telegramTestRoute(SystemNotificationKey::DailyAuditSummary, TelegramDeliveryMode::Off);

    $route = App\Support\InternalNotifications\InternalMessaging::route(SystemNotificationKey::DailyAuditSummary, 'Auditorías', '*Auditorías*', ['04161110000', '04169990000']);

    expect($route['whatsapp'])->toBe(['04161110000', '04169990000'])
        ->and(NotificationDelivery::query()->where('id', '>', $this->deliveryFloor)->count())->toBe(0);
});

it('sin token del bot ningún aviso usa Telegram aunque tenga destino guardado', function (): void {
    config(['telegram.bot_token' => null]);
    TelegramRecipient::factory()->create(['phone' => '04161110001']);
    telegramTestRoute(SystemNotificationKey::BirthdayNotificationSummary, TelegramDeliveryMode::Person);

    $route = App\Support\InternalNotifications\InternalMessaging::route(SystemNotificationKey::BirthdayNotificationSummary, 'Resumen cumpleaños', 'x', ['04161110001']);

    expect($route['whatsapp'])->toBe(['04161110001'])->and($route['telegram'])->toBe(0);
});

it('en modo persona saca del WhatsApp a quien tiene Telegram y lo encola en notifications', function (): void {
    $linked = TelegramRecipient::factory()->create(['phone' => '+58 416-111-0002']);
    telegramTestRoute(SystemNotificationKey::OperationInventoryLowStock, TelegramDeliveryMode::Person);

    $route = App\Support\InternalNotifications\InternalMessaging::route(SystemNotificationKey::OperationInventoryLowStock, 'Stock bajo', "*Stock bajo*\nGasas: 2", ['04161110002', '04169990002']);

    expect($route['whatsapp'])->toBe(['04169990002'])
        ->and($route['telegram'])->toBe(1);

    $delivery = NotificationDelivery::query()->where('id', '>', $this->deliveryFloor)->first();

    expect($delivery->telegram_recipient_id)->toBe($linked->getKey())
        ->and($delivery->body)->toBe("*Stock bajo*\nGasas: 2");

    Queue::assertPushedOn('notifications', SendTelegramDeliveryJob::class);
});

it('reconoce también a una persona vinculada que solo figura por su correo', function (): void {
    TelegramRecipient::factory()->create(['email' => 'solo.correo@tudrencasa.com', 'phone' => null]);
    telegramTestRoute(SystemNotificationKey::LiveSecurityAlert, TelegramDeliveryMode::Person);

    $route = App\Support\InternalNotifications\InternalMessaging::route(SystemNotificationKey::LiveSecurityAlert, 'Seguridad', 'x', ['04169990003'], ['solo.correo@tudrencasa.com']);

    expect($route['telegram'])->toBe(1)->and($route['whatsapp'])->toBe(['04169990003']);
});

it('en modo grupo nadie recibe WhatsApp; si no hay grupo activo, sale como siempre', function (): void {
    $group = TelegramRecipient::factory()->group()->create();
    $pending = TelegramRecipient::factory()->group()->pendingApproval()->create();

    telegramTestRoute(SystemNotificationKey::StructureBackup, TelegramDeliveryMode::Group, [$group->getKey()]);
    $route = App\Support\InternalNotifications\InternalMessaging::route(SystemNotificationKey::StructureBackup, 'Exportaciones', 'x', ['04169990004']);

    expect($route['whatsapp'])->toBe([])->and($route['groups'])->toBe(1);

    telegramTestRoute(SystemNotificationKey::StructureBackup, TelegramDeliveryMode::Group, [$pending->getKey()]);
    $fallback = App\Support\InternalNotifications\InternalMessaging::route(SystemNotificationKey::StructureBackup, 'Exportaciones', 'x', ['04169990004']);

    expect($fallback['whatsapp'])->toBe(['04169990004'])->and($fallback['groups'])->toBe(0);
});

it('las alertas sobre el propio Telegram siempre salen por WhatsApp', function (): void {
    TelegramRecipient::factory()->create(['phone' => '04161110005']);
    telegramTestRoute(SystemNotificationKey::LiveSystemAlert, TelegramDeliveryMode::Person);

    $route = App\Support\InternalNotifications\InternalMessaging::route(SystemNotificationKey::LiveSystemAlert, 'Telegram no responde', 'x', ['04161110005'], bypassTelegram: true);

    expect($route['whatsapp'])->toBe(['04161110005'])->and($route['telegram'])->toBe(0);
});

it('sabe qué alertas del vigilante tratan sobre Telegram', function (array $event, bool $expected): void {
    expect(SystemHealthWatcher::concernsTelegram($event))->toBe($expected);
})->with([
    'bot caído' => [['type' => 'telegram_down'], true],
    'webhook' => [['type' => 'telegram_webhook'], true],
    'avisos sin entregar' => [['type' => 'notifications_undelivered'], true],
    'cola notifications sin worker' => [['type' => 'queue_unattended', 'subject' => 'notifications'], true],
    'todas las colas sin worker' => [['type' => 'queue_no_workers', 'subject' => 'all'], true],
    'cola default atascada' => [['type' => 'queue_stuck', 'subject' => 'default'], false],
    'error del sistema' => [['type' => 'error_new', 'subject' => 'x'], false],
]);

it('un aviso inmediato se entrega por Telegram en el acto, sin pasar por la cola', function (): void {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 77]])]);
    Queue::fakeExcept([SendTelegramDeliveryJob::class]);
    Illuminate\Support\Facades\Bus::fake([SendNotificacionWhatsApp::class]);
    Cache::forget('telegram:health');
    TelegramRecipient::factory()->create(['phone' => '04161110006']);
    telegramTestRoute(SystemNotificationKey::LiveSystemAlert, TelegramDeliveryMode::Person);

    $route = App\Support\InternalNotifications\InternalMessaging::route(SystemNotificationKey::LiveSystemAlert, 'Cola atascada', '⚠️ *Cola atascada*', ['04161110006'], immediate: true);

    expect($route['telegram'])->toBe(1)
        ->and(NotificationDelivery::query()->where('id', '>', $this->deliveryFloor)->first()->status)->toBe(NotificationDeliveryStatus::Sent);

    Illuminate\Support\Facades\Bus::assertNotDispatched(SendNotificacionWhatsApp::class);
    Http::assertSent(fn ($request): bool => $request['text'] === '⚠️ <b>Cola atascada</b>');
});

it('si un aviso inmediato falla en Telegram, el WhatsApp de respaldo sale en el acto', function (): void {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => false, 'error_code' => 502, 'description' => 'Bad Gateway'], 502)]);
    Queue::fakeExcept([SendTelegramDeliveryJob::class]);
    Illuminate\Support\Facades\Bus::fake([SendNotificacionWhatsApp::class]);
    Cache::forget('telegram:health');
    TelegramRecipient::factory()->create(['phone' => '04161110007']);
    telegramTestRoute(SystemNotificationKey::LiveSystemAlert, TelegramDeliveryMode::Person);

    App\Support\InternalNotifications\InternalMessaging::route(SystemNotificationKey::LiveSystemAlert, 'Cola atascada', '⚠️ *Cola atascada*', ['04161110007'], immediate: true);

    $delivery = NotificationDelivery::query()->where('id', '>', $this->deliveryFloor)->whereNull('parent_id')->first();

    expect($delivery->status)->toBe(NotificationDeliveryStatus::FellBack)
        ->and($delivery->fallbacks()->first()->status)->toBe(NotificationDeliveryStatus::Sent);

    Illuminate\Support\Facades\Bus::assertDispatchedSync(SendNotificacionWhatsApp::class, fn (SendNotificacionWhatsApp $job): bool => $job->phone === '04161110007' && $job->body === '⚠️ *Cola atascada*');
});

it('un aviso inmediato no espera a Telegram si el último chequeo dijo que no responde', function (): void {
    Cache::put('telegram:health', ['configured' => true, 'ok' => false], 60);
    TelegramRecipient::factory()->create(['phone' => '04161110008']);
    telegramTestRoute(SystemNotificationKey::LiveSystemAlert, TelegramDeliveryMode::Person);

    $route = App\Support\InternalNotifications\InternalMessaging::route(SystemNotificationKey::LiveSystemAlert, 'x', 'x', ['04161110008'], immediate: true);

    expect($route['whatsapp'])->toBe(['04161110008']);

    Cache::forget('telegram:health');
});

it('la alerta de seguridad va por Telegram a los vinculados y por WhatsApp al resto, y el correo no cambia', function (): void {
    config(['live-presence.allowed_emails' => []]);
    TelegramRecipient::factory()->create(['phone' => '04161110009']);
    SystemNotificationRecipientSetting::for(SystemNotificationKey::LiveSecurityAlert)->update([
        'is_active' => true,
        'notification_emails' => ['seguridad@tudrencasa.com'],
        'notification_phones' => ['04161110009', '04169990009'],
        'telegram_mode' => 'person',
        'telegram_group_ids' => [],
    ]);

    (new App\Jobs\NotifyLiveSecurityAlertJob(['type' => 'brute_force', 'title' => 'Fuerza bruta', 'detail' => 'Muchos intentos', 'at' => time()]))->handle();

    Queue::assertPushed(SendTelegramDeliveryJob::class, 1);
    Queue::assertPushed(SendNotificacionWhatsApp::class, fn (SendNotificacionWhatsApp $job): bool => str_ends_with($job->phone, '4169990009'));
    Queue::assertNotPushed(SendNotificacionWhatsApp::class, fn (SendNotificacionWhatsApp $job): bool => str_ends_with($job->phone, '4161110009'));
    Mail::assertSent(App\Mail\LiveSecurityAlertMail::class, 1);
});

it('las tareas sin aviso propio usan la entrada «Resúmenes de tareas programadas» del Centro', function (): void {
    $source = (string) file_get_contents(dirname(__DIR__, 2).'/app/Support/ScheduledTaskRunReport.php');

    expect($source)->toContain('self::$notificationKey ?? SystemNotificationKey::ScheduledTaskSummary')
        ->toContain('! SystemNotificationRecipients::isActive(SystemNotificationKey::ScheduledTaskSummary)')
        ->toContain('InternalMessaging::route(self::effectiveKey()')
        ->toContain('self::sendDocumentToPhone($phone)');
});

it('las 6 alertas migradas pasan su WhatsApp por el reparto de Telegram', function (string $file): void {
    expect((string) file_get_contents(dirname(__DIR__, 2).'/'.$file))->toContain('InternalMessaging::route(');
})->with([
    'app/Jobs/NotifyLiveSecurityAlertJob.php',
    'app/Support/ScheduledTaskRunReport.php',
    'app/Jobs/AnulateAgentQuotes.php',
    'app/Jobs/SendDailyAuditSummary.php',
    'app/Jobs/NotifyOperationInventoryProductLowStockJob.php',
    'app/Jobs/SendOperationInventoryLowStockAlert.php',
]);

// ── Entrega B2: claves OTP internas por Telegram ─────────────────────────

it('las tres claves OTP pasan por Telegram en el acto y cuentan lo entregado por Telegram', function (string $file): void {
    $source = (string) file_get_contents(dirname(__DIR__, 2).'/'.$file);

    expect($source)->toContain('InternalMessaging::route(')
        ->toContain('immediate: true')
        ->toContain("\$phonesSent += \$route['delivered'];")
        ->toContain("foreach (\$route['whatsapp'] as");
})->with([
    'app/Support/ClinicalEntitlements/ClinicalServiceOverrideOtp.php',
    'app/Support/ClinicalEntitlements/ClinicalUsageAccessOtp.php',
    'app/Support/AffiliationCorporates/Population/CorporatePopulationOtp.php',
]);

it('ninguna clave OTP puede ir a un grupo de Telegram', function (SystemNotificationKey $key): void {
    $group = TelegramRecipient::factory()->group()->create();
    telegramTestRoute($key, TelegramDeliveryMode::Both, [$group->getKey()]);

    expect($key->allowsTelegramGroups())->toBeFalse()
        ->and(App\Support\InternalNotifications\InternalMessaging::mode($key))->toBe(TelegramDeliveryMode::Person);
})->with([
    SystemNotificationKey::TelemedicineServiceLimitOverride,
    SystemNotificationKey::CorporatePopulationUpdate,
    SystemNotificationKey::ClinicalUsageAccessOtp,
]);

it('la clave de uso clínico no pide contactos en el Centro: va siempre a los SUPERADMIN', function (): void {
    $key = SystemNotificationKey::ClinicalUsageAccessOtp;

    expect($key->usesEmailRecipients())->toBeFalse()
        ->and($key->usesPhoneRecipients())->toBeFalse()
        ->and($key->supportsTelegram())->toBeTrue()
        ->and($key->pausesScheduledTask())->toBeFalse()
        ->and($key->activationHelp())->toContain('no se bloquea');
});

it('una clave entregada por Telegram cuenta como entregada', function (): void {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 9]])]);
    Queue::fakeExcept([SendTelegramDeliveryJob::class]);
    Illuminate\Support\Facades\Bus::fake([SendNotificacionWhatsApp::class]);
    Cache::forget('telegram:health');
    TelegramRecipient::factory()->create(['phone' => '04161120001']);
    telegramTestRoute(SystemNotificationKey::CorporatePopulationUpdate, TelegramDeliveryMode::Person);

    $route = App\Support\InternalNotifications\InternalMessaging::route(SystemNotificationKey::CorporatePopulationUpdate, 'Clave', 'Clave: *123456*', ['04161120001'], immediate: true, critical: true);

    expect($route['delivered'])->toBe(1)->and($route['whatsapp'])->toBe([]);
    Illuminate\Support\Facades\Bus::assertNotDispatched(SendNotificacionWhatsApp::class);
});

it('si Telegram falla con una clave, sale por WhatsApp en el acto y también cuenta como entregada', function (): void {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => false, 'error_code' => 403, 'description' => 'Forbidden: bot was blocked by the user'], 403)]);
    Queue::fakeExcept([SendTelegramDeliveryJob::class]);
    Illuminate\Support\Facades\Bus::fake([SendNotificacionWhatsApp::class]);
    Cache::forget('telegram:health');
    TelegramRecipient::factory()->create(['phone' => '04161120002']);
    telegramTestRoute(SystemNotificationKey::TelemedicineServiceLimitOverride, TelegramDeliveryMode::Person);

    $route = App\Support\InternalNotifications\InternalMessaging::route(SystemNotificationKey::TelemedicineServiceLimitOverride, 'Clave', 'Clave: *654321*', ['04161120002'], immediate: true, critical: true);

    expect($route['delivered'])->toBe(1);
    Illuminate\Support\Facades\Bus::assertDispatchedSync(SendNotificacionWhatsApp::class, fn (SendNotificacionWhatsApp $job): bool => $job->phone === '04161120002' && $job->body === 'Clave: *654321*');
});

it('si una clave no llega por ningún canal no cuenta como entregada', function (): void {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => false, 'error_code' => 502, 'description' => 'Bad Gateway'], 502)]);
    Queue::fakeExcept([SendTelegramDeliveryJob::class]);
    Cache::forget('telegram:health');
    TelegramRecipient::factory()->create(['phone' => null, 'email' => 'sincanal@tudrencasa.com']);
    telegramTestRoute(SystemNotificationKey::ClinicalUsageAccessOtp, TelegramDeliveryMode::Person);
    Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP caído'));

    $route = App\Support\InternalNotifications\InternalMessaging::route(SystemNotificationKey::ClinicalUsageAccessOtp, 'Clave', 'x', [], ['sincanal@tudrencasa.com'], immediate: true, critical: true);

    expect($route['telegram'])->toBe(1)->and($route['delivered'])->toBe(0);
});

it('el aviso de clave autoautorizada usa el destino de Telegram de su tipo de clave', function (App\Enums\SystemOtpCodeKind $kind, SystemNotificationKey $key): void {
    expect(App\Jobs\SendOtpSelfAuthorizationNoticeJob::notificationKeyFor($kind))->toBe($key);
})->with([
    [App\Enums\SystemOtpCodeKind::ClinicalLimitOverride, SystemNotificationKey::TelemedicineServiceLimitOverride],
    [App\Enums\SystemOtpCodeKind::PopulationApply, SystemNotificationKey::CorporatePopulationUpdate],
    [App\Enums\SystemOtpCodeKind::PopulationRollback, SystemNotificationKey::CorporatePopulationUpdate],
    [App\Enums\SystemOtpCodeKind::ClinicalUsageAccess, SystemNotificationKey::ClinicalUsageAccessOtp],
]);

it('el aviso de clave autoautorizada va por Telegram al vinculado y por WhatsApp al resto', function (): void {
    TelegramRecipient::factory()->create(['phone' => '04161120003']);
    telegramTestRoute(SystemNotificationKey::CorporatePopulationUpdate, TelegramDeliveryMode::Person);

    (new App\Jobs\SendOtpSelfAuthorizationNoticeJob(
        App\Enums\SystemOtpCodeKind::PopulationApply->value,
        'Analista QA',
        'Empresa X',
        'Urgencia del cliente',
        now()->toIso8601String(),
        ['otp@tudrencasa.com'],
        ['04161120003', '04169990010'],
    ))->handle();

    Queue::assertPushed(SendTelegramDeliveryJob::class, 1);
    Queue::assertPushed(SendNotificacionWhatsApp::class, fn (SendNotificacionWhatsApp $job): bool => str_ends_with($job->phone, '4169990010'));
    Queue::assertNotPushed(SendNotificacionWhatsApp::class, fn (SendNotificacionWhatsApp $job): bool => str_ends_with($job->phone, '4161120003'));
    Mail::assertSent(App\Mail\OtpSelfAuthorizationNoticeMail::class, 1);
});

// ── Centro de notificaciones: diferencia visible entre WhatsApp y Telegram ──

it('cada aviso del Centro tiene un área y, si aún no migró, la entrega en que pasa a Telegram', function (): void {
    /** Desde B7 todos los avisos del Centro pueden usar Telegram: ninguno queda con entrega pendiente. */
    foreach (SystemNotificationKey::managed() as $key) {
        expect(array_key_exists($key->area(), SystemNotificationKey::AREAS))->toBeTrue();

        $key->supportsTelegram()
            ? expect($key->telegramMigrationStage())->toBeNull()
            : expect($key->telegramMigrationStage())->toMatch('/^B\d$/');
    }
});

it('el selector distingue los avisos por Telegram, listos para Telegram, solo WhatsApp y pausados', function (): void {
    telegramTestActAsSuperAdmin($this);
    telegramTestRoute(SystemNotificationKey::LiveSystemAlert, TelegramDeliveryMode::Both);
    telegramTestRoute(SystemNotificationKey::DailyAuditSummary, TelegramDeliveryMode::Off);
    SystemNotificationRecipientSetting::for(SystemNotificationKey::BirthdayNotificationSummary)->update(['is_active' => false]);

    $page = Livewire\Livewire::test(App\Filament\Business\Pages\ManageCompanyAssociateNotifications::class);
    $catalog = $page->instance()->keyCatalog();
    $items = collect($catalog['areas'])->flatMap(fn (array $area): array => $area['items'])->keyBy('key');

    expect($items['live_system_alert'])->toMatchArray(['channel' => 'telegram', 'mode' => 'Ambos'])
        ->and($items['daily_audit_summary']['channel'])->toBe('ready')
        ->and($items['birthday_notification_summary'])->toMatchArray(['channel' => 'ready', 'stage' => null, 'active' => false])
        ->and($catalog['counts']['whatsapp'])->toBe(0)
        ->and($catalog['counts']['total'])->toBe(count(SystemNotificationKey::managed()))
        ->and($catalog['counts']['telegram'] + $catalog['counts']['ready'] + $catalog['counts']['whatsapp'])->toBe($catalog['counts']['total']);

    /** Filtros compactos de la barra lateral; «Solo WhatsApp» se oculta cuando no queda ningún aviso así. */
    $page->assertSee('Por Telegram')
        ->assertSee('Listos')
        ->assertDontSee('Solo WhatsApp</button>', false)
        ->assertSee('Seguridad y claves OTP')
        ->assertSee('Pausado')
        ->call('selectNotificationKey', 'birthday_notification_summary')
        ->assertSee('Listo para Telegram');
});

it('el selector carga el estado de todos los avisos en una sola consulta', function (): void {
    telegramTestActAsSuperAdmin($this);
    $page = Livewire\Livewire::test(App\Filament\Business\Pages\ManageCompanyAssociateNotifications::class);

    DB::flushQueryLog();
    DB::enableQueryLog();
    unset($page->instance()->keyCatalog);
    $page->instance()->keyCatalog();
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queries)->toBe(1);
});

// ── Entrega B3a: avisos de Negocios con entrada en el Centro ─────────────

it('los 6 avisos de Negocios con entrada en el Centro pasan por el reparto de Telegram', function (string $file, string $key): void {
    expect((string) file_get_contents(dirname(__DIR__, 2).'/'.$file))
        ->toContain('InternalMessaging::route(')
        ->toContain('SystemNotificationKey::'.$key);

    expect(SystemNotificationKey::{$key}->supportsTelegram())->toBeTrue()
        ->and(SystemNotificationKey::{$key}->telegramMigrationStage())->toBeNull();
})->with([
    ['app/Jobs/NotifyAnalystsOfCompanyAssociateRegistrationJob.php', 'CompanyAssociateRegistration'],
    ['app/Support/Companies/CompanyAssociateDocumentsDeliverer.php', 'CompanyAssociateRegistration'],
    ['app/Jobs/NotifyCompanyAssociateIlsCoverageConfirmedJob.php', 'CompanyAssociateIlsCoverage'],
    ['app/Jobs/NotifyAnalystsOfTdevRegistrationJob.php', 'TdevRegistration'],
    ['app/Support/IndividualQuotes/IndividualQuoteFollowUpInternalCopies.php', 'IndividualQuoteFollowUp'],
    ['app/Jobs/NotifySuperAdminsOfEarlyRenovationJob.php', 'EarlyRenovationAcceptance'],
]);

it('en los documentos del asociado solo los analistas pasan a Telegram; el carnet y el QR siguen por WhatsApp', function (): void {
    $source = (string) file_get_contents(dirname(__DIR__, 2).'/app/Support/Companies/CompanyAssociateDocumentsDeliverer.php');

    expect($source)->toContain('$name === self::ANALYST_LABEL')
        ->toContain('isset($introByTelegram[(string) $rawPhone]) ? [] : [new SendNotificacionWhatsApp(')
        ->toContain('new SendNotificacionWhatsAppDocument(');
});

it('en la cobertura ILS el voucher sigue saliendo por WhatsApp aunque el aviso vaya por Telegram', function (): void {
    $source = (string) file_get_contents(dirname(__DIR__, 2).'/app/Jobs/NotifyCompanyAssociateIlsCoverageConfirmedJob.php');

    expect($source)->toContain('isset($textByWhatsApp[$rawPhone])')
        ->toContain("'asset' => 'voucher-ils'")
        ->toContain('if ($jobs === []) {');
});

it('la renovación anticipada no repite el aviso por Telegram ni el WhatsApp si el job se reintenta', function (): void {
    Illuminate\Support\Facades\Bus::fake([SendNotificacionWhatsApp::class]);
    TelegramRecipient::factory()->create(['phone' => '04161130001']);
    SystemNotificationRecipientSetting::for(SystemNotificationKey::EarlyRenovationAcceptance)->update([
        'is_active' => true,
        'notification_emails' => [],
        'notification_phones' => ['04161130001', '04169990030'],
        'telegram_mode' => 'person',
        'telegram_group_ids' => [],
    ]);

    $payload = App\Support\Renovations\EarlyRenovationNotificationPayload::build(
        'individual',
        [['history_id' => 1, 'affiliation_code' => 'X', 'days_before_renewal' => 40]],
        new App\Support\Renovations\EarlyRenovationAuthorization(reason: 'Prueba', userId: 1, userName: 'QA', userEmail: 'qa@tudrencasa.com'),
    );

    (new App\Jobs\NotifySuperAdminsOfEarlyRenovationJob($payload))->handle();
    $telegramAfterFirst = Queue::pushed(SendTelegramDeliveryJob::class)->count();
    $whatsAppAfterFirst = Illuminate\Support\Facades\Bus::dispatchedSync(SendNotificacionWhatsApp::class)->count();

    (new App\Jobs\NotifySuperAdminsOfEarlyRenovationJob($payload))->handle();

    expect($telegramAfterFirst)->toBeGreaterThanOrEqual(1)
        ->and(Queue::pushed(SendTelegramDeliveryJob::class)->count())->toBe($telegramAfterFirst)
        ->and(Illuminate\Support\Facades\Bus::dispatchedSync(SendNotificacionWhatsApp::class)->count())->toBe($whatsAppAfterFirst);

    Illuminate\Support\Facades\Bus::assertNotDispatchedSync(SendNotificacionWhatsApp::class, fn (SendNotificacionWhatsApp $job): bool => str_ends_with($job->phone, '4161130001'));
    Illuminate\Support\Facades\Bus::assertDispatchedSync(SendNotificacionWhatsApp::class, fn (SendNotificacionWhatsApp $job): bool => str_ends_with($job->phone, '4169990030'));
});

// ── Entrega B3b: avisos que iban a números fijos ─────────────────────────

it('las 4 entradas nuevas arrancan con los números que estaban fijos en el código', function (): void {
    expect(SystemNotificationKey::DressTaylorRequest->defaultPhones())->toBe(['+584241869168'])
        ->and(SystemNotificationKey::GuiaChatFeedback->defaultPhones())->toBe(['04127018390', '04121931865', '04143027250'])
        ->and(SystemNotificationKey::NewQuoteAnalysts->defaultPhones())->toContain('04127018390', '04143027250')
        ->and(SystemNotificationKey::CommercialNetworkActivity->defaultPhones())->toBe(array_values(array_filter([trim((string) config('parameters.PHONE_COTIZACIONES_AFILIACIONES'))])));

    foreach ([SystemNotificationKey::NewQuoteAnalysts, SystemNotificationKey::CommercialNetworkActivity, SystemNotificationKey::DressTaylorRequest, SystemNotificationKey::GuiaChatFeedback] as $key) {
        expect($key->supportsTelegram())->toBeTrue()
            ->and($key->usesEmailRecipients())->toBeFalse()
            ->and($key->area())->toBe('negocios')
            ->and($key->pausesScheduledTask())->toBeFalse();
    }
});

it('un movimiento de la red comercial va por Telegram al vinculado y por WhatsApp en cola al resto', function (): void {
    TelegramRecipient::factory()->create(['phone' => '04161140001']);
    SystemNotificationRecipientSetting::for(SystemNotificationKey::CommercialNetworkActivity)->update([
        'is_active' => true,
        'notification_phones' => ['04161140001', '04169990040'],
        'telegram_mode' => 'person',
        'telegram_group_ids' => [],
    ]);

    $sent = App\Http\Controllers\NotificationController::uploadVoucherOfPayment('AF-0001', 'Agente QA');

    expect($sent)->toBeTrue();
    Queue::assertPushed(SendTelegramDeliveryJob::class, 1);
    Queue::assertPushed(SendNotificacionWhatsApp::class, fn (SendNotificacionWhatsApp $job): bool => str_ends_with($job->phone, '4169990040') && str_contains($job->body, 'AF-0001'));
    Queue::assertNotPushed(SendNotificacionWhatsApp::class, fn (SendNotificacionWhatsApp $job): bool => str_ends_with($job->phone, '4161140001'));
    Http::assertNothingSent();
});

it('con la entrada pausada el aviso de la red comercial no sale y responde que no se envió', function (): void {
    SystemNotificationRecipientSetting::for(SystemNotificationKey::CommercialNetworkActivity)->update([
        'is_active' => false,
        'notification_phones' => ['04169990041'],
        'telegram_mode' => 'off',
    ]);

    expect(App\Http\Controllers\NotificationController::saddObervationToCorporateQuote('COR-1', 'Agente QA', 'Revisar'))->toBeFalse();
    Queue::assertNothingPushed();
});

it('la solicitud Dress Taylor usa su propia entrada del Centro', function (): void {
    SystemNotificationRecipientSetting::for(SystemNotificationKey::DressTaylorRequest)->update([
        'is_active' => true,
        'notification_phones' => ['04169990042'],
        'telegram_mode' => 'off',
    ]);

    expect(App\Http\Controllers\NotificationController::createdRequestDressTaylor('DT-9', 'Agente QA', 'Cobertura especial'))->toBeTrue();
    Queue::assertPushed(SendNotificacionWhatsApp::class, fn (SendNotificacionWhatsApp $job): bool => str_ends_with($job->phone, '4169990042') && str_contains($job->body, 'DT-9'));
});

it('los avisos de números fijos ya no tienen números escritos en el código', function (): void {
    $controller = (string) file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/NotificationController.php');
    $guia = (string) file_get_contents(dirname(__DIR__, 2).'/app/Support/GuiaChat/GuiaChatFeedbackNotifier.php');

    expect($controller)->not->toContain("'to' => '+584241869168'")
        ->not->toContain('quoteAnalystRecipientPhones')
        ->toContain('InternalMessaging::route(SystemNotificationKey::NewQuoteAnalysts');

    expect(substr_count($controller, 'self::sendInternalNotice(SystemNotificationKey::'))->toBe(6);

    expect($guia)->not->toContain("'04121931865'")
        ->toContain('SystemNotificationKey::GuiaChatFeedback');
});

// ── Entrega B3c: avisos a una persona concreta ───────────────────────────

it('un aviso personal sale por Telegram si la persona está vinculada, reconocida por su usuario', function (): void {
    $user = telegramTestUser('asignado.qa@tudrencasa.com');
    TelegramRecipient::factory()->create(['type' => TelegramRecipientType::User, 'user_id' => $user->getKey(), 'email' => $user->email, 'phone' => null]);
    telegramTestRoute(SystemNotificationKey::PersonalAssignments, TelegramDeliveryMode::Person);

    $sent = App\Support\InternalNotifications\InternalMessaging::routeToPerson(SystemNotificationKey::PersonalAssignments, 'Tarea asignada', '*Tarea*', $user->getKey(), '04169990050');

    expect($sent)->toBeTrue();
    Queue::assertPushedOn('notifications', SendTelegramDeliveryJob::class);
});

it('un aviso personal reconoce a la persona por su teléfono si no hay usuario', function (): void {
    TelegramRecipient::factory()->create(['phone' => '04161150001']);
    telegramTestRoute(SystemNotificationKey::PersonalAssignments, TelegramDeliveryMode::Person);

    expect(App\Support\InternalNotifications\InternalMessaging::routeToPerson(SystemNotificationKey::PersonalAssignments, 'x', 'x', null, '+58 416 115 0001'))->toBeTrue();
});

it('un aviso personal sigue por WhatsApp si la persona no está vinculada o el aviso está en Apagado', function (): void {
    $linked = TelegramRecipient::factory()->create(['phone' => '04161150002']);

    telegramTestRoute(SystemNotificationKey::PersonalAssignments, TelegramDeliveryMode::Person);
    expect(App\Support\InternalNotifications\InternalMessaging::routeToPerson(SystemNotificationKey::PersonalAssignments, 'x', 'x', null, '04169990051'))->toBeFalse();

    telegramTestRoute(SystemNotificationKey::PersonalAssignments, TelegramDeliveryMode::Off);
    expect(App\Support\InternalNotifications\InternalMessaging::routeToPerson(SystemNotificationKey::PersonalAssignments, 'x', 'x', null, '04161150002'))->toBeFalse();

    Queue::assertNotPushed(SendTelegramDeliveryJob::class);
});

it('la entrada de avisos personales no pide contactos ni permite grupos', function (): void {
    $key = SystemNotificationKey::PersonalAssignments;

    expect($key->supportsTelegram())->toBeTrue()
        ->and($key->allowsTelegramGroups())->toBeFalse()
        ->and($key->usesEmailRecipients())->toBeFalse()
        ->and($key->usesPhoneRecipients())->toBeFalse()
        ->and($key->activationHelp())->toContain('solo dejan de usar Telegram');
});

it('la tarea de prospecto y la Agenda prueban Telegram antes de mandar el WhatsApp', function (string $file, int $times): void {
    $source = (string) file_get_contents(dirname(__DIR__, 2).'/'.$file);

    expect(substr_count($source, 'InternalMessaging::routeToPerson('))->toBe($times)
        ->and($source)->toContain('SystemNotificationKey::PersonalAssignments');
})->with([
    ['app/Support/ProspectAgents/ProspectAgentTaskAssigneeNotifier.php', 1],
    ['app/Services/CorporateAgendaInvitationWhatsAppService.php', 2],
]);

// ── Entrega B4: Administración y Operaciones ─────────────────────────────

it('los avisos de B4 pasan por el reparto de Telegram con su entrada del Centro', function (string $file, string $key): void {
    expect((string) file_get_contents(dirname(__DIR__, 2).'/'.$file))
        ->toContain('InternalMessaging::route(');

    expect(SystemNotificationKey::{$key}->supportsTelegram())->toBeTrue()
        ->and(SystemNotificationKey::{$key}->telegramMigrationStage())->toBeNull();
})->with([
    ['app/Jobs/NotifyAdministrationOfStorefrontQuotePaymentReceiptJob.php', 'StorefrontQuotePaymentReceipt'],
    ['app/Jobs/NotifyAdministrationOfCorporatePaymentFrequencyChangeJob.php', 'CorporatePaymentFrequencyChange'],
    ['app/Jobs/NotifyAffiliationsTeamOfAffiliateUpdateJob.php', 'OperationsAffiliateUpdate'],
    ['app/Support/Operations/ContadoQuotePaymentNotifier.php', 'ContadoQuotePayment'],
    ['app/Support/Operations/CourtesyFinanceNotifier.php', 'CourtesyFinance'],
]);

it('pago de contado y cortesías ya no tienen números fijos y arrancan con los de antes', function (): void {
    foreach (['ContadoQuotePaymentNotifier', 'CourtesyFinanceNotifier'] as $class) {
        expect((string) file_get_contents(dirname(__DIR__, 2).'/app/Support/Operations/'.$class.'.php'))
            ->not->toContain('WHATSAPP_PHONES')
            ->not->toContain("'04242875732'");
    }

    foreach ([SystemNotificationKey::ContadoQuotePayment, SystemNotificationKey::CourtesyFinance] as $key) {
        expect($key->defaultPhones())->toBe(['04242875732', '04143027250'])
            ->and($key->area())->toBe('administracion')
            ->and($key->usesEmailRecipients())->toBeFalse();
    }
});

it('el comprobante PWA sigue llegando por WhatsApp aunque el aviso vaya por Telegram', function (): void {
    expect((string) file_get_contents(dirname(__DIR__, 2).'/app/Jobs/NotifyAdministrationOfStorefrontQuotePaymentReceiptJob.php'))
        ->toContain('isset($textByWhatsApp[$rawPhone])')
        ->toContain("'asset' => 'receipt'")
        ->toContain('if ($jobs === []) {');
});

it('el aviso de afiliado actualizado no repite Telegram ni WhatsApp si el job se reintenta', function (): void {
    expect((string) file_get_contents(dirname(__DIR__, 2).'/app/Jobs/NotifyAffiliationsTeamOfAffiliateUpdateJob.php'))
        ->toContain("\$routeKey = \$this->deliveryCacheKey('telegram', 'route');")
        ->toContain('Cache::put($routeKey, $phonesForWhatsApp, now()->addDay());')
        ->toContain('foreach ($phonesForWhatsApp as $rawPhone)');
});

// ── Testigos de recibos y avisos de cobro por Telegram, con su PDF ───────

function telegramTestPdf(): string
{
    $path = sys_get_temp_dir().'/telegram-testigo-'.uniqid().'.pdf';
    file_put_contents($path, "%PDF-1.4\n% testigo de prueba\n");

    return $path;
}

it('el testigo vinculado recibe el mensaje y el PDF por Telegram; el resto, el PDF por WhatsApp', function (): void {
    $pdf = telegramTestPdf();
    TelegramRecipient::factory()->create(['phone' => '04161160001']);
    telegramTestRoute(SystemNotificationKey::SaleReceiptWitnessCopy, TelegramDeliveryMode::Person);

    $queued = App\Support\DocumentEmails\DocumentEmailWitnesses::queueWhatsAppCopies(
        ['04161160001', '04169990060'],
        '*Copia testigo · Recibo de pago Nro. 77*',
        'https://integracorp.example/reciboDePago/RDP-77.pdf?v=1',
        'RDP-77.pdf',
        ['source' => 'pest'],
        SystemNotificationKey::SaleReceiptWitnessCopy,
        $pdf,
    );

    $delivery = NotificationDelivery::query()->where('id', '>', $this->deliveryFloor)->where('channel', 'telegram')->first();

    expect($queued)->toBe(2)
        ->and($delivery->attachment_path)->toBe($pdf)
        ->and($delivery->attachment_name)->toBe('RDP-77.pdf')
        ->and($delivery->attachment_url)->toBe('https://integracorp.example/reciboDePago/RDP-77.pdf?v=1');

    Queue::assertPushed(App\Jobs\SendNotificacionWhatsAppDocument::class, 1);
    Queue::assertPushed(App\Jobs\SendNotificacionWhatsAppDocument::class, fn ($job): bool => str_ends_with($job->phone, '4169990060'));

    @unlink($pdf);
});

it('sin entrada de Telegram el testigo sigue exactamente como antes', function (): void {
    telegramTestRoute(SystemNotificationKey::CollectionNoticeWitnessCopy, TelegramDeliveryMode::Off);

    $queued = App\Support\DocumentEmails\DocumentEmailWitnesses::queueWhatsAppCopies(['04169990061'], 'x', 'https://x/aviso.pdf', 'aviso.pdf');

    expect($queued)->toBe(1);
    Queue::assertPushed(App\Jobs\SendNotificacionWhatsAppDocument::class, 1);
    Queue::assertNotPushed(SendTelegramDeliveryJob::class);
});

it('Telegram recibe el PDF protegido, con el mensaje como pie', function (): void {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 501]])]);
    $pdf = telegramTestPdf();
    $delivery = telegramTestDelivery(TelegramRecipient::factory()->create(), [
        'body' => '*Copia testigo · Aviso de cobro Nro. 9*',
        'attachment_path' => $pdf,
        'attachment_name' => 'AC-9.pdf',
        'attachment_url' => 'https://x/AC-9.pdf',
    ]);

    (new SendTelegramDeliveryJob($delivery->getKey()))->handle();

    expect($delivery->refresh()->status)->toBe(NotificationDeliveryStatus::Sent)
        ->and($delivery->provider_message_id)->toBe('501');

    Http::assertSent(function ($request): bool {
        $parts = collect($request->data())->keyBy('name');

        return str_ends_with($request->url(), '/sendDocument')
            && ($parts['caption']['contents'] ?? null) === '<b>Copia testigo · Aviso de cobro Nro. 9</b>'
            && ($parts['protect_content']['contents'] ?? null) === 'true'
            && ($parts['document']['filename'] ?? null) === 'AC-9.pdf';
    });

    @unlink($pdf);
});

it('si el PDF ya no existe, el testigo recibe el documento por WhatsApp sin reintentar Telegram', function (): void {
    Http::fake();
    $delivery = telegramTestDelivery(TelegramRecipient::factory()->create(['phone' => '04161160002']), [
        'attachment_path' => '/no/existe/RDP-1.pdf',
        'attachment_name' => 'RDP-1.pdf',
        'attachment_url' => 'https://x/RDP-1.pdf',
    ]);

    (new SendTelegramDeliveryJob($delivery->getKey()))->handle();

    Http::assertNothingSent();
    expect($delivery->refresh()->status)->toBe(NotificationDeliveryStatus::FellBack)
        ->and($delivery->fallbacks()->first()->attachment_url)->toBe('https://x/RDP-1.pdf');

    Queue::assertPushed(App\Jobs\SendNotificacionWhatsAppDocument::class, fn ($job): bool => $job->documentUrl === 'https://x/RDP-1.pdf' && $job->filename === 'RDP-1.pdf');
});

it('si Telegram falla, el respaldo por correo lleva el PDF adjunto', function (): void {
    $pdf = telegramTestPdf();
    $mail = new App\Mail\InternalNotificationMail('Copia testigo', 'x', true, $pdf, 'RDP-5.pdf');

    expect($mail->attachments())->toHaveCount(1);
    expect((new App\Mail\InternalNotificationMail('Copia testigo', 'x', true, '/no/existe.pdf', 'x.pdf'))->attachments())->toBe([]);

    @unlink($pdf);
});

it('los tres caminos de testigos pasan el PDF a Telegram', function (): void {
    $root = dirname(__DIR__, 2);

    expect((string) file_get_contents($root.'/app/Support/Sales/SaleReceiptEmailDelivery.php'))->toContain('SystemNotificationKey::SaleReceiptWitnessCopy,');
    expect((string) file_get_contents($root.'/app/Support/Collections/CollectionNoticeEmailDelivery.php'))->toContain('SystemNotificationKey::CollectionNoticeWitnessCopy,');
    expect((string) file_get_contents($root.'/app/Support/Filament/Administration/SaleReciboPagoWhatsAppRecipients.php'))
        ->toContain('sendWitnessCopiesByTelegram')
        ->toContain("\$target['role'] === 'internal' && isset(\$witnessesByTelegram['phones'][\$target['phone']])");

    expect(SystemNotificationKey::SaleReceiptWitnessCopy->supportsTelegram())->toBeTrue()
        ->and(SystemNotificationKey::CollectionNoticeWitnessCopy->supportsTelegram())->toBeTrue();
});

// ── Entrega B5: Telemedicina, sin datos clínicos por Telegram ────────────

it('el reverso de caso por Telegram no lleva la nota del médico, el motivo ni los datos de contacto', function (): void {
    $payload = [
        'case_code' => 'TLM-77',
        'status' => 'ASIGNADO',
        'managed_by' => 'TDG',
        'priority' => 'ALTA',
        'reason' => 'Dolor torácico intenso',
        'assigned_by' => 'Analista',
        'patient_name' => 'Paciente Prueba',
        'patient_phone' => '04140000000',
        'patient_age' => '50',
        'patient_sex' => 'M',
        'patient_address' => 'Calle 1',
        'doctor_name' => 'Dra. QA',
        'reversed_by' => 'dra.qa',
        'reversed_at' => '16/11/2026 10:00',
        'reversal_note' => 'Paciente con antecedentes de infarto',
    ];

    $telegram = App\Support\Telemedicine\TelemedicineCaseReversalNotificationMessage::telegramBody($payload);
    $whatsApp = App\Support\Telemedicine\TelemedicineCaseReversalNotificationMessage::whatsappBody($payload);

    expect($telegram)->toContain('TLM-77')->toContain('Paciente Prueba')->toContain('Reasigne')
        ->not->toContain('Dolor torácico')
        ->not->toContain('infarto')
        ->not->toContain('04140000000')
        ->not->toContain('Calle 1');

    expect($whatsApp)->toContain('infarto')->toContain('Dolor torácico');
});

it('Telegram recibe la versión sin datos clínicos y el WhatsApp de los no vinculados sigue completo', function (): void {
    TelegramRecipient::factory()->create(['phone' => '04161170001']);
    telegramTestRoute(SystemNotificationKey::TelemedicineCaseReversal, TelegramDeliveryMode::Person);

    $route = App\Support\InternalNotifications\InternalMessaging::route(
        SystemNotificationKey::TelemedicineCaseReversal,
        'Reverso',
        'COMPLETO con nota clínica',
        ['04161170001', '04169990070'],
        telegramMessage: 'RESUMIDO sin datos clínicos',
    );

    expect($route['whatsapp'])->toBe(['04169990070'])
        ->and(NotificationDelivery::query()->where('id', '>', $this->deliveryFloor)->first()->body)->toBe('RESUMIDO sin datos clínicos');
});

it('la copia de control del aviso al agente por Telegram no lleva cédula, edad ni teléfono del paciente', function (): void {
    $payload = [
        'case_code' => 'TLM-88',
        'registered_at' => '16/11/2026',
        'registered_by' => 'Operaciones',
        'patient_name' => 'Paciente Dos',
        'patient_document' => 'V-12345678',
        'patient_age' => '33',
        'patient_phone' => '04145550000',
        'affiliation_type' => 'Individual',
        'affiliation_code' => 'AF-1',
        'plan' => 'Plan Ideal',
        'owner' => ['kind' => 'agent', 'name' => 'Agente X', 'code' => 'AGT-1', 'phone' => '+584140000001'],
    ];

    $telegram = App\Support\Telemedicine\TelemedicineCaseAgentNotice::copyTelegramBody($payload);

    expect($telegram)->toContain('TLM-88')->toContain('Paciente Dos')->toContain('AF-1')->toContain('Plan Ideal')
        ->not->toContain('V-12345678')
        ->not->toContain('04145550000')
        ->not->toContain('33');
});

it('el caso asignado al médico por Telegram no lleva el motivo de consulta y no se repite en reintentos', function (): void {
    $source = (string) file_get_contents(dirname(__DIR__, 2).'/app/Jobs/AssignedCase.php');
    $telegramPart = substr($source, (int) strpos($source, 'private function sentByTelegram'));

    expect($telegramPart)->toContain('SystemNotificationKey::DoctorCaseAssignment')
        ->toContain("'Paciente: '.\$this->name_patient")
        ->toContain('Cache::put($guard, true, now()->addDay());')
        ->not->toContain('$this->reason');
});

it('el médico se reconoce también por su correo', function (): void {
    TelegramRecipient::factory()->create(['email' => 'dra.qa@clinica.com', 'phone' => null]);
    telegramTestRoute(SystemNotificationKey::DoctorCaseAssignment, TelegramDeliveryMode::Person);

    expect(App\Support\InternalNotifications\InternalMessaging::routeToPerson(SystemNotificationKey::DoctorCaseAssignment, 'Caso', 'x', null, '04169990071', email: 'DRA.QA@clinica.com'))->toBeTrue();
});

it('los avisos de Telemedicina migrados', function (SystemNotificationKey $key): void {
    expect($key->supportsTelegram())->toBeTrue()
        ->and($key->area())->toBe('telemedicina');
})->with([SystemNotificationKey::TelemedicineCaseReversal, SystemNotificationKey::TelemedicineCaseAgentNotice, SystemNotificationKey::DoctorCaseAssignment]);

// ── Entregas B6 (Helpdesk) y B7 (RRHH) ───────────────────────────────────

it('los tres puntos de envío del Helpdesk prueban Telegram con la persona antes del WhatsApp', function (): void {
    $assignee = (string) file_get_contents(dirname(__DIR__, 2).'/app/Services/HelpdeskTicketAssigneeWhatsAppService.php');
    $team = (string) file_get_contents(dirname(__DIR__, 2).'/app/Services/HelpdeskTeamMembersWhatsAppService.php');

    expect(substr_count($assignee, 'self::dispatchInternalWhatsApp('))->toBe(2)
        ->and($assignee)->toContain('SystemNotificationKey::HelpdeskNotices')
        ->and($team)->toContain('HelpdeskTicketAssigneeWhatsAppService::dispatchInternalWhatsApp(');

    $key = SystemNotificationKey::HelpdeskNotices;

    expect($key->supportsTelegram())->toBeTrue()
        ->and($key->allowsTelegramGroups())->toBeFalse()
        ->and($key->usesPhoneRecipients())->toBeFalse()
        ->and($key->area())->toBe('helpdesk');
});

it('un aviso del Helpdesk va por Telegram al asignado vinculado y por WhatsApp si no lo está', function (): void {
    $user = telegramTestUser('helpdesk.qa@tudrencasa.com');
    TelegramRecipient::factory()->create(['type' => TelegramRecipientType::User, 'user_id' => $user->getKey(), 'email' => $user->email, 'phone' => null]);
    telegramTestRoute(SystemNotificationKey::HelpdeskNotices, TelegramDeliveryMode::Person);

    App\Services\HelpdeskTicketAssigneeWhatsAppService::dispatchInternalWhatsApp($user->getKey(), 1, '*Ticket #5*', '04169990080', ['helpdesk_id' => 5]);
    App\Services\HelpdeskTicketAssigneeWhatsAppService::dispatchInternalWhatsApp(null, 1, '*Ticket #5*', '04169990081', ['helpdesk_id' => 5]);

    Queue::assertPushed(SendTelegramDeliveryJob::class, 1);
    Queue::assertPushed(SendNotificacionWhatsApp::class, 1);
    Queue::assertPushed(SendNotificacionWhatsApp::class, fn (SendNotificacionWhatsApp $job): bool => str_ends_with($job->phone, '4169990081'));

    expect(NotificationDelivery::query()->where('id', '>', $this->deliveryFloor)->value('title'))->toBe('Helpdesk · ticket #5');
});

it('el bot manda imágenes como foto, videos como video y el resto como documento', function (string $file, string $method): void {
    expect(App\Support\Telegram\TelegramBotClient::uploadMethodFor('/tmp/'.$file)[0])->toBe($method);
})->with([
    ['tarjeta.jpg', 'sendPhoto'],
    ['tarjeta.PNG', 'sendPhoto'],
    ['saludo.mp4', 'sendVideo'],
    ['RDP-1.pdf', 'sendDocument'],
]);

it('la copia testigo de cumpleaños lleva la tarjeta a Telegram y el resumen pasa por el reparto', function (): void {
    $source = (string) file_get_contents(dirname(__DIR__, 2).'/app/Support/BirthdayNotificationRunReport.php');

    expect($source)->toContain('SystemNotificationKey::BirthdayNotificationWitnessCopy,')
        ->toContain('attachment: self::telegramAttachmentFor(')
        ->toContain("InternalMessaging::route(SystemNotificationKey::BirthdayNotificationSummary, 'Resumen de cumpleaños'")
        ->toContain('foreach (array_values($route[\'whatsapp\']) as $index => $phone)');

    expect(SystemNotificationKey::BirthdayNotificationWitnessCopy->supportsTelegram())->toBeTrue()
        ->and(SystemNotificationKey::BirthdayNotificationSummary->supportsTelegram())->toBeTrue();
});

it('todos los avisos del Centro de notificaciones ya pueden usar Telegram', function (): void {
    $pending = collect(SystemNotificationKey::managed())->reject(fn (SystemNotificationKey $key): bool => $key->supportsTelegram())->map->name->all();

    expect($pending)->toBe([]);
});

it('el Centro explica a quién llega cada tipo de aviso por Telegram sin confundir OTP con avisos personales', function (): void {
    expect(SystemNotificationKey::HelpdeskNotices->telegramModeHelp())
        ->toContain('a los asignados')
        ->toContain('al creador')
        ->toContain('Nadie más lo recibe')
        ->not->toContain('OTP');

    expect(SystemNotificationKey::HelpdeskNotices->telegramPersonLabel())->toBe('Persona · a quien le corresponde el aviso')
        ->and(SystemNotificationKey::DoctorCaseAssignment->telegramModeHelp())->not->toContain('OTP')
        ->and(SystemNotificationKey::CorporatePopulationUpdate->telegramModeHelp())->toContain('clave OTP')
        ->and(SystemNotificationKey::ClinicalUsageAccessOtp->telegramPersonLabel())->toBe('Persona · a cada SUPERADMIN vinculado')
        ->and(SystemNotificationKey::LiveSystemAlert->telegramModeHelp())->toContain('Grupo:');

    telegramTestActAsSuperAdmin($this);

    Livewire\Livewire::test(App\Filament\Business\Pages\ManageCompanyAssociateNotifications::class)
        ->call('selectNotificationKey', SystemNotificationKey::HelpdeskNotices->value)
        ->assertSee('cuando un asignado responde o actualiza, al creador')
        ->assertDontSee('Este aviso lleva una clave OTP');
});

// ── Personas de Telegram elegidas a mano ─────────────────────────────────

it('una persona elegida a mano recibe por Telegram aunque no esté en los contactos del aviso', function (): void {
    $chosen = TelegramRecipient::factory()->create(['phone' => '04161180001']);
    SystemNotificationRecipientSetting::for(SystemNotificationKey::DailyAuditSummary)->update([
        'is_active' => true,
        'telegram_mode' => 'person',
        'telegram_group_ids' => [],
        'telegram_recipient_ids' => [$chosen->getKey()],
    ]);

    $route = App\Support\InternalNotifications\InternalMessaging::route(SystemNotificationKey::DailyAuditSummary, 'Auditorías', 'x', ['04169990090']);

    expect($route['telegram'])->toBe(1)
        ->and($route['whatsapp'])->toBe(['04169990090'])
        ->and(NotificationDelivery::query()->where('id', '>', $this->deliveryFloor)->value('telegram_recipient_id'))->toBe($chosen->getKey());
});

it('si la persona elegida también está en los contactos, recibe una sola vez', function (): void {
    $chosen = TelegramRecipient::factory()->create(['phone' => '04161180002']);
    SystemNotificationRecipientSetting::for(SystemNotificationKey::DailyAuditSummary)->update([
        'is_active' => true,
        'telegram_mode' => 'person',
        'telegram_recipient_ids' => [$chosen->getKey()],
    ]);

    $route = App\Support\InternalNotifications\InternalMessaging::route(SystemNotificationKey::DailyAuditSummary, 'Auditorías', 'x', ['04161180002']);

    expect($route['telegram'])->toBe(1)->and($route['whatsapp'])->toBe([]);
});

it('una persona elegida que ya no puede recibir (bloqueó el bot o venció) no se usa', function (): void {
    $blocked = TelegramRecipient::factory()->create(['status' => TelegramRecipientStatus::Blocked]);
    $expired = TelegramRecipient::factory()->create(['expires_at' => now()->subDay()]);
    SystemNotificationRecipientSetting::for(SystemNotificationKey::DailyAuditSummary)->update([
        'is_active' => true,
        'telegram_mode' => 'person',
        'telegram_recipient_ids' => [$blocked->getKey(), $expired->getKey()],
    ]);

    $route = App\Support\InternalNotifications\InternalMessaging::route(SystemNotificationKey::DailyAuditSummary, 'Auditorías', 'x', []);

    expect($route['telegram'])->toBe(0);
});

it('la prueba de Telegram también llega a las personas elegidas', function (): void {
    $chosen = TelegramRecipient::factory()->create();
    telegramTestSetting(TelegramDeliveryMode::Person);
    SystemNotificationRecipientSetting::for(SystemNotificationKey::TelegramTest)->update(['telegram_recipient_ids' => [$chosen->getKey()]]);

    $summary = InternalNotificationDispatcher::dispatch(telegramTestNotification());

    expect($summary['telegram'])->toBe(1);
});

it('el Centro deja elegir personas y muestra la vista previa, salvo en los avisos personales', function (): void {
    $chosen = TelegramRecipient::factory()->create(['name' => 'Persona Elegible QA']);
    telegramTestActAsSuperAdmin($this);
    SystemNotificationRecipientSetting::for(SystemNotificationKey::DailyAuditSummary)->update([
        'is_active' => true,
        'telegram_mode' => 'person',
        'telegram_recipient_ids' => [$chosen->getKey()],
    ]);

    Livewire\Livewire::test(App\Filament\Business\Pages\ManageCompanyAssociateNotifications::class)
        ->call('selectNotificationKey', SystemNotificationKey::DailyAuditSummary->value)
        ->assertSee('Personas de Telegram')
        ->assertSee('Lo recibirán por Telegram')
        ->assertSee('Persona Elegible QA')
        ->call('selectNotificationKey', SystemNotificationKey::HelpdeskNotices->value)
        ->assertDontSee('Personas de Telegram');
});

it('el Centro rediseñado ordena el detalle en pestañas y deja «Cómo funciona» plegado', function (): void {
    telegramTestActAsSuperAdmin($this);

    $page = Livewire\Livewire::test(App\Filament\Business\Pages\ManageCompanyAssociateNotifications::class)
        ->call('selectNotificationKey', SystemNotificationKey::DailyAuditSummary->value);

    $page->assertSee('Destinatarios')
        ->assertSee('Telegram')
        ->assertSee('Estado')
        ->assertSee('Cómo funciona este aviso')
        ->assertSee('Sistema')
        ->assertSee('Correos electrónicos')
        ->assertSee('Teléfonos WhatsApp')
        ->assertSee('Tarea activa');

    $page->call('selectNotificationKey', SystemNotificationKey::HelpdeskNotices->value)
        ->assertSee('A quien le corresponde')
        ->assertDontSee('Teléfonos WhatsApp');

    $view = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/business/pages/manage-company-associate-notifications.blade.php');

    expect($view)->toContain('<details class="can-howto">')
        ->toContain('.can-settings-page .can-layout')
        ->not->toMatch('/^\s{12}\.(?!can-settings-page|dark \.can-settings-page)[a-z]/m');
});
