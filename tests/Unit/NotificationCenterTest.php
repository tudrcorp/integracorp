<?php

declare(strict_types=1);

use App\Enums\NotificationAudience;
use App\Enums\OutboundChannel;
use App\Enums\OutboundMessageStatus;
use App\Enums\SystemNotificationKey;
use App\Models\NotificationCatalogEntry;
use App\Models\NotificationChannelSetting;
use App\Models\OutboundMessage;
use App\Support\NotificationCenter\NotificationCatalog;
use App\Support\NotificationCenter\NotificationCatalogDefaults;
use App\Support\NotificationCenter\NotificationCenterSettings;
use App\Support\NotificationCenter\OutboundGuard;
use App\Support\NotificationCenter\OutboundWhatsApp;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Centro de notificaciones con control total: catálogo, guardián y bitácora.
 *
 * Las pruebas con base de datos corren en una transacción que siempre se revierte y
 * se saltan si la migración aún no se aplicó.
 */
uses(Tests\TestCase::class);

function notificationCenterDefinitions(): array
{
    return [...NotificationCatalogDefaults::systemEntries(), ...NotificationCatalogDefaults::entries()];
}

it('cada origen pertenece a un solo tipo del catálogo', function (): void {
    $seen = [];

    foreach (notificationCenterDefinitions() as $key => $definition) {
        foreach ($definition['origins'] as $origin) {
            expect($seen)->not->toHaveKey($origin, "El origen {$origin} está en {$key} y en ".($seen[$origin] ?? ''));
            $seen[$origin] = $key;
        }
    }

    expect(count($seen))->toBeGreaterThan(150);
});

it('todos los avisos internos del Centro están en el catálogo y los críticos existen', function (): void {
    $definitions = notificationCenterDefinitions();

    foreach (SystemNotificationKey::managed() as $key) {
        expect($definitions)->toHaveKey('internal:'.$key->value);
    }

    foreach (NotificationCatalogDefaults::CRITICAL_SYSTEM_KEYS as $critical) {
        expect(SystemNotificationKey::tryFrom($critical))->not->toBeNull()
            ->and($definitions['internal:'.$critical]['critical'])->toBeTrue();
    }

    foreach (array_keys(NotificationCatalogDefaults::SYSTEM_KEY_ORIGINS) as $systemKey) {
        expect(SystemNotificationKey::tryFrom($systemKey))->not->toBeNull();
    }
});

it('los orígenes catalogados apuntan a clases y métodos que existen', function (): void {
    foreach (notificationCenterDefinitions() as $key => $definition) {
        foreach ($definition['origins'] as $origin) {
            if (str_starts_with($origin, 'mail:')) {
                $class = substr($origin, 5);

                if (str_starts_with($class, 'App\\')) {
                    expect(class_exists($class))->toBeTrue("{$key}: no existe {$class}");
                }

                continue;
            }

            [$class, $method] = explode('::', $origin, 2);
            expect(class_exists($class))->toBeTrue("{$key}: no existe {$class}");

            if ($method !== '{closure}') {
                expect(method_exists($class, $method))->toBeTrue("{$key}: no existe {$origin}");
            }
        }
    }
});

it('las áreas y audiencias del catálogo son válidas', function (): void {
    foreach (notificationCenterDefinitions() as $key => $definition) {
        expect(NotificationCatalogDefaults::AREAS)->toHaveKey($definition['area'])
            ->and($definition['audience'])->toBeInstanceOf(NotificationAudience::class)
            ->and($definition['channels'])->not->toBeEmpty();
    }

    expect(NotificationCatalogDefaults::entries()['bulk.mass_campaigns']['bulk'])->toBeTrue()
        ->and(NotificationCatalogDefaults::entries()['bulk.birthdays']['bulk'])->toBeTrue()
        ->and(NotificationCatalogDefaults::entries()['int.framework_auth']['critical'])->toBeTrue();
});

it('nombra de forma legible un envío descubierto', function (): void {
    expect(NotificationCatalog::humanize('App\Jobs\SendFooBarJob::handle'))->toBe('WhatsApp · Send Foo Bar (handle)')
        ->and(NotificationCatalog::humanize('mail:App\Mail\WelcomePartnerMail'))->toBe('Correo · Welcome Partner');
});

it('reconoce el tipo de mensaje de WhatsApp por sus campos', function (array $params, string $type): void {
    expect(OutboundWhatsApp::data(['to' => '0414', ...$params])['type'])->toBe($type);
})->with([
    'texto' => [['body' => 'hola'], 'chat'],
    'imagen' => [['image' => 'https://x/a.png', 'caption' => 'hola'], 'image'],
    'documento' => [['document' => 'https://x/a.pdf', 'filename' => 'a.pdf'], 'document'],
    'video' => [['video' => 'https://x/a.mp4'], 'video'],
]);

it('enmascara teléfonos y correos en la bitácora', function (): void {
    expect((new OutboundMessage(['recipient' => '+58 414-123-4567']))->maskedRecipient())->toBe('5841•••4567')
        ->and((new OutboundMessage(['recipient' => 'gabriela@example.com']))->maskedRecipient())->toBe('ga•••@example.com')
        ->and((new OutboundMessage(['recipient' => null]))->maskedRecipient())->toBe('—');
});

it('todos los envíos síncronos a UltraMsg pasan por el guardián', function (): void {
    foreach ([
        'app/Http/Controllers/NotificationController.php',
        'app/Services/NotificationMasiveService.php',
        'app/Support/Companies/CompanyPublicRegistrationLinkSender.php',
    ] as $path) {
        $source = file_get_contents(base_path($path));

        expect($source)->not->toContain('curl_exec(')
            ->and($source)->toContain('OutboundWhatsApp::exec($curl, $params)');
    }

    /** UtilsController solo conserva el curl_exec de la consulta de estado (no envía mensajes). */
    $utils = file_get_contents(base_path('app/Http/Controllers/UtilsController.php'));
    expect(substr_count($utils, 'curl_exec('))->toBe(1)
        ->and($utils)->toContain('instance/status?');
});

it('los jobs de WhatsApp deciden antes de enviar y capturan su origen al despacharse', function (string $path): void {
    $source = file_get_contents(base_path($path));

    expect($source)->toContain("\$this->auditContext['origin'] ??= OutboundOrigin::capture();")
        ->and($source)->toContain('OutboundWhatsApp::decide($this->phone, $this->auditContext)')
        ->and($source)->toContain('OutboundWhatsApp::recordBlocked($decision')
        ->and(strpos($source, 'OutboundWhatsApp::decide(') < strpos($source, 'curl_exec('))->toBeTrue();
})->with([
    'app/Jobs/SendNotificacionWhatsApp.php',
    'app/Jobs/SendNotificacionWhatsAppDocument.php',
    'app/Jobs/SendNotificacionWhatsAppVideo.php',
]);

it('los correos pasan por el guardián y las campañas masivas se pueden pausar', function (): void {
    expect(file_get_contents(base_path('app/Providers/AppServiceProvider.php')))
        ->toContain("Event::listen(MessageSending::class, [OutboundMailListener::class, 'onSending'])")
        ->toContain("Event::listen(MessageSent::class, [OutboundMailListener::class, 'onSent'])");

    expect(file_get_contents(base_path('app/Support/MassNotificationWhatsAppSender.php')))
        ->toContain("OutboundWhatsApp::decide(\$phone, ['catalog' => 'bulk.mass_campaigns'])");

    expect(file_get_contents(base_path('routes/console.php')))
        ->toContain("Schedule::command('model:prune', ['--model' => [\\App\\Models\\OutboundMessage::class]])");
});

it('el Centro se abre con permiso propio y lo delicado queda para SUPERADMIN', function (): void {
    expect(file_get_contents(base_path('app/Support/Filament/DepartmentNavigationPermissionRegistry.php')))
        ->toContain("ManageCompanyAssociateNotifications::class => ['centro-notificaciones']");

    $access = file_get_contents(base_path('app/Support/NotificationCenter/NotificationCenterAccess.php'));
    expect($access)->toContain('public static function canManageChannels(?User $user = null): bool')
        ->toContain('public static function canManageCritical(?User $user = null): bool')
        ->toContain('public static function canReadExternalContent(?User $user = null): bool');
});

describe('guardián con base de datos', function (): void {
    beforeEach(function (): void {
        if (! Schema::hasTable('notification_catalog_entries') || ! Schema::hasTable('outbound_messages')) {
            $this->markTestSkipped('Falta la migración 2026_11_21_120000_create_notification_center_tables.php.');
        }

        Cache::setDefaultDriver('array');
        DB::beginTransaction();
        NotificationCatalog::flush();
        NotificationCatalog::seed();
    });

    afterEach(function (): void {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        NotificationCatalog::flush();
    });

    it('bloquea un tipo apagado sin llamar a UltraMsg y lo deja en la bitácora', function (): void {
        $entry = NotificationCatalogEntry::query()->where('key', 'ext.quote_pdf')->firstOrFail();
        NotificationCenterSettings::setEntryFlag($entry, 'whatsapp_enabled', false);

        $curl = curl_init('http://127.0.0.1:9/');
        $result = OutboundWhatsApp::exec($curl, ['to' => '04141234567', 'caption' => 'x', 'document' => 'https://x/q.pdf', 'filename' => 'q.pdf'], [
            'origin' => 'App\Http\Controllers\NotificationController::sendQuote',
        ]);

        $row = OutboundMessage::query()->latest('id')->first();

        expect($result)->toBeFalse()
            ->and(curl_getinfo($curl, CURLINFO_HTTP_CODE))->toBe(0)
            ->and($row->status)->toBe(OutboundMessageStatus::Blocked)
            ->and($row->catalog_key)->toBe('ext.quote_pdf')
            ->and($row->audience)->toBe(NotificationAudience::External);
    });

    it('el interruptor general no detiene a los críticos', function (): void {
        NotificationChannelSetting::query()->where('channel', 'whatsapp')->update(['is_enabled' => false]);
        NotificationCatalog::flush();

        expect(OutboundWhatsApp::decide('04141234567', ['origin' => 'App\Http\Controllers\NotificationController::sendQuote'])->blocked())->toBeTrue()
            ->and(OutboundWhatsApp::decide('04141234567', ['notification_key' => 'live_security_alert'])->allowed())->toBeTrue();
    });

    it('un envío desconocido se agrega como «Sin clasificar» y los masivos no van a la bitácora', function (): void {
        $decision = OutboundWhatsApp::decide('04141234567', ['origin' => 'App\Jobs\EnvioNuevoDePruebaJob::handle']);

        expect(NotificationCatalogEntry::query()->where('key', $decision->catalogKey)->value('is_discovered'))->toBeTrue();

        $count = OutboundMessage::query()->count();
        OutboundGuard::record(OutboundGuard::evaluate(OutboundChannel::WhatsApp, '0414', ['catalog' => 'bulk.birthdays']), OutboundMessageStatus::Sent, ['recipient' => '0414']);

        expect(OutboundMessage::query()->count())->toBe($count);
    });
});
