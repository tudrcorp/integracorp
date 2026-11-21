<?php

declare(strict_types=1);

namespace App\Support\NotificationCenter;

use App\Enums\NotificationAudience;
use App\Enums\OutboundChannel;
use App\Models\NotificationCatalogEntry;
use App\Models\NotificationChannelSetting;
use App\Models\OutboundMessage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Catálogo del Centro de notificaciones en memoria: una sola lectura (cacheada en
 * Redis) para reconocer el tipo de cada envío y saber si puede salir.
 *
 * - Cache compartida: se invalida al guardar cualquier cambio en el Centro.
 * - Copia por proceso de 30 s: un worker largo ve los cambios en medio minuto sin
 *   consultar Redis en cada mensaje.
 * - Si algo falla (tablas aún sin migrar, Redis caído), devuelve un catálogo vacío
 *   y todo sale como siempre: el guardián nunca bloquea por un error propio.
 */
final class NotificationCatalog
{
    public const CACHE_KEY = 'notification-center:catalog:v1';

    private const LOCAL_TTL_SECONDS = 30;

    /**
     * @var array{entries: array<string, array<string, mixed>>, origins: array<string, string>, channels: array<string, bool>}|null
     */
    private static ?array $snapshot = null;

    private static int $loadedAt = 0;

    private static ?bool $tablesExist = null;

    /**
     * @return array{entries: array<string, array<string, mixed>>, origins: array<string, string>, channels: array<string, bool>}
     */
    public static function snapshot(): array
    {
        if (self::$snapshot !== null && (time() - self::$loadedAt) < self::LOCAL_TTL_SECONDS) {
            return self::$snapshot;
        }

        $empty = ['entries' => [], 'origins' => [], 'channels' => []];

        try {
            if (! self::tablesExist()) {
                return $empty;
            }

            self::$snapshot = Cache::rememberForever(self::CACHE_KEY, fn (): array => self::build());
            self::$loadedAt = time();

            /** Primera vez tras la migración: se carga el catálogo solo, una vez y con candado. */
            if (self::$snapshot['entries'] === []) {
                $lock = Cache::lock('notification-center:seed', 60);

                if ($lock->get()) {
                    try {
                        self::seed();
                        self::$snapshot = Cache::rememberForever(self::CACHE_KEY, fn (): array => self::build());
                        self::$loadedAt = time();
                    } finally {
                        $lock->release();
                    }
                }
            }

            return self::$snapshot;
        } catch (Throwable $exception) {
            Log::warning('Centro de notificaciones: no se pudo leer el catálogo; los envíos salen sin control.', ['error' => $exception->getMessage()]);

            return $empty;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function entry(string $key): ?array
    {
        return self::snapshot()['entries'][$key] ?? null;
    }

    public static function keyForOrigin(string $origin): ?string
    {
        return self::snapshot()['origins'][$origin] ?? null;
    }

    public static function channelEnabled(OutboundChannel $channel): bool
    {
        return self::snapshot()['channels'][$channel->value] ?? true;
    }

    /**
     * Tipo de un envío que no está en el catálogo: se agrega como «Sin clasificar».
     *
     * @return array<string, mixed>|null
     */
    public static function discover(string $origin, OutboundChannel $channel, NotificationAudience $audience): ?array
    {
        try {
            if (! self::tablesExist()) {
                return null;
            }

            $key = 'auto:'.substr(sha1($origin), 0, 24);
            $isFramework = str_starts_with($origin, 'mail:Filament\\') || str_starts_with($origin, 'mail:Illuminate\\');

            NotificationCatalogEntry::query()->insertOrIgnore([
                'key' => $key,
                'name' => self::humanize($origin),
                'area' => $isFramework ? 'seguridad' : 'sin-clasificar',
                'audience' => $audience->value,
                'origins' => json_encode([$origin]),
                'channels' => json_encode([$channel->value]),
                'is_active' => true,
                'whatsapp_enabled' => true,
                'mail_enabled' => true,
                'is_critical' => $isFramework,
                'is_bulk' => false,
                'is_discovered' => true,
                'last_seen_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            self::flush();

            return self::entry($key);
        } catch (Throwable $exception) {
            Log::warning('Centro de notificaciones: no se pudo registrar un tipo nuevo.', ['origin' => $origin, 'error' => $exception->getMessage()]);

            return null;
        }
    }

    /**
     * Carga aditiva de los valores por defecto: crea lo que falta y suma orígenes
     * nuevos, sin pisar nada que el analista haya cambiado.
     */
    public static function seed(): int
    {
        if (! self::tablesExist()) {
            return 0;
        }

        $created = 0;
        $definitions = [...NotificationCatalogDefaults::systemEntries(), ...NotificationCatalogDefaults::entries()];
        $existing = NotificationCatalogEntry::query()->get()->keyBy('key');

        foreach ($definitions as $key => $definition) {
            $entry = $existing->get($key);

            if ($entry instanceof NotificationCatalogEntry) {
                $origins = array_values(array_unique([...(array) ($entry->origins ?? []), ...$definition['origins']]));
                $channels = array_values(array_unique([...(array) ($entry->channels ?? []), ...$definition['channels']]));

                if ($origins !== ($entry->origins ?? []) || $channels !== ($entry->channels ?? [])) {
                    $entry->forceFill(['origins' => $origins, 'channels' => $channels])->save();
                }

                continue;
            }

            NotificationCatalogEntry::query()->create([
                'key' => $key,
                'name' => $definition['name'],
                'description' => $definition['description'] ?? null,
                'area' => $definition['area'],
                'audience' => $definition['audience'],
                'system_key' => $definition['system_key'] ?? null,
                'origins' => $definition['origins'],
                'channels' => $definition['channels'],
                'is_critical' => (bool) ($definition['critical'] ?? false),
                'is_bulk' => (bool) ($definition['bulk'] ?? false),
            ]);

            $created++;
        }

        /** Un origen descubierto antes de la carga pasa a su tipo catalogado. */
        $catalogued = [];

        foreach ($definitions as $key => $definition) {
            foreach ($definition['origins'] as $origin) {
                $catalogued[$origin] = $key;
            }
        }

        NotificationCatalogEntry::query()
            ->where('is_discovered', true)
            ->get()
            ->each(function (NotificationCatalogEntry $entry) use ($catalogued): void {
                $targets = array_values(array_unique(array_map(
                    static fn (string $origin): ?string => $catalogued[$origin] ?? null,
                    (array) ($entry->origins ?? []),
                )));

                /** Todo lo que se descubrió ya tiene un tipo único: su historial pasa a ese tipo. */
                if (count($targets) === 1 && $targets[0] !== null) {
                    OutboundMessage::query()->where('catalog_key', $entry->key)->update(['catalog_key' => $targets[0]]);
                    $entry->delete();
                }
            });

        foreach (OutboundChannel::cases() as $channel) {
            NotificationChannelSetting::query()->firstOrCreate(['channel' => $channel->value], ['is_enabled' => true]);
        }

        self::flush();

        return $created;
    }

    public static function flush(): void
    {
        self::$snapshot = null;
        self::$loadedAt = 0;

        try {
            Cache::forget(self::CACHE_KEY);
        } catch (Throwable) {
        }
    }

    public static function tablesExist(): bool
    {
        if (self::$tablesExist === true) {
            return true;
        }

        try {
            $exists = Schema::hasTable('notification_catalog_entries')
                && Schema::hasTable('notification_channel_settings')
                && Schema::hasTable('outbound_messages');
        } catch (Throwable) {
            return false;
        }

        if ($exists) {
            self::$tablesExist = true;
        }

        return $exists;
    }

    /**
     * Nombre legible de un origen descubierto: «Sin clasificar · SendFooJob (handle)».
     */
    public static function humanize(string $origin): string
    {
        $isMail = str_starts_with($origin, 'mail:');
        $target = $isMail ? substr($origin, 5) : $origin;
        [$class, $method] = array_pad(explode('::', $target, 2), 2, null);
        $base = class_basename((string) $class);
        $words = trim((string) preg_replace('/(?<!^)([A-Z])/', ' $1', (string) preg_replace('/(Mail|Email|Job|Notification|Service|Controller)$/', '', $base)));

        return Str::limit(($isMail ? 'Correo · ' : 'WhatsApp · ').($words !== '' ? $words : $base).($method && ! $isMail ? ' ('.$method.')' : ''), 155, '…');
    }

    /**
     * @return array{entries: array<string, array<string, mixed>>, origins: array<string, string>, channels: array<string, bool>}
     */
    private static function build(): array
    {
        $entries = [];
        $origins = [];

        foreach (NotificationCatalogEntry::query()->get() as $entry) {
            $entries[$entry->key] = [
                'key' => $entry->key,
                'name' => $entry->name,
                'audience' => $entry->audience->value,
                'system_key' => $entry->system_key,
                'is_active' => $entry->is_active,
                'whatsapp_enabled' => $entry->whatsapp_enabled,
                'mail_enabled' => $entry->mail_enabled,
                'is_critical' => $entry->is_critical,
                'is_bulk' => $entry->is_bulk,
            ];

            foreach ((array) ($entry->origins ?? []) as $origin) {
                /** Un origen catalogado gana sobre uno descubierto. */
                if (! isset($origins[$origin]) || ! $entry->is_discovered) {
                    $origins[$origin] = $entry->key;
                }
            }
        }

        $channels = NotificationChannelSetting::query()->pluck('is_enabled', 'channel')->map(fn (mixed $enabled): bool => (bool) $enabled)->all();

        return ['entries' => $entries, 'origins' => $origins, 'channels' => $channels];
    }
}
