<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Filament\Operations\Resources\TelemedicinePatients\Actions\RegisterTpaRetailServicesAction;
use App\Models\RetailMainService;
use App\Models\User;
use App\Support\SecurityAudit;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Servicios principales del registro RETAIL. Quien tiene el permiso los agrega o los quita; quitar solo
 * desactiva, así las coordinaciones viejas siguen reconociendo su servicio. Telemedicina y AMD están
 * protegidos: tienen lógica propia (llegan al médico de guardia).
 *
 * Si la tabla todavía no existe (migración pendiente), se usa la lista histórica del código.
 */
final class RetailMainServiceCatalog
{
    public const LABEL_MIN = 3;

    public const LABEL_MAX = 60;

    private const CACHE_KEY = 'operations:retail-main-services:v1';

    /** Segundos que un proceso largo (worker de colas) confía en su copia antes de releer. */
    private const MEMO_SECONDS = 30;

    /** @var array<string, array{label: string, active: bool, protected: bool, id: int}>|null */
    private static ?array $memo = null;

    private static int $memoAt = 0;

    /**
     * Claves de los servicios que se ofrecen hoy, en su orden.
     *
     * @return list<string>
     */
    public static function active(): array
    {
        return array_keys(array_filter(self::rows(), fn (array $row): bool => $row['active']));
    }

    /**
     * Todas las claves, también las quitadas: sirven para reconocer coordinaciones ya creadas.
     *
     * @return list<string>
     */
    public static function known(): array
    {
        return array_values(array_unique([...array_keys(self::rows()), ...RegisterTpaRetailServicesAction::LEGACY_SERVICES]));
    }

    public static function label(string $name): string
    {
        $label = self::rows()[$name]['label'] ?? null;

        return $label !== null && $label !== '' ? $label : mb_convert_case(mb_strtolower($name), MB_CASE_TITLE);
    }

    /**
     * Servicios activos que se pueden quitar (id => nombre visible).
     *
     * @return array<int, string>
     */
    public static function removable(): array
    {
        $options = [];

        foreach (self::rows() as $row) {
            if ($row['active'] && ! $row['protected'] && $row['id'] > 0) {
                $options[$row['id']] = $row['label'];
            }
        }

        return $options;
    }

    /**
     * Clave que queda en la coordinación: mayúsculas, sin acentos ni espacios dobles.
     */
    public static function keyFor(string $label): string
    {
        return Str::upper(Str::squish(Str::ascii($label)));
    }

    /**
     * Agrega un servicio. Si ya existió y se había quitado, lo vuelve a activar con el nombre escrito.
     *
     * @return array{ok: true, reactivated: bool, label: string}|array{ok: false, message: string}
     */
    public static function add(string $label, ?User $user): array
    {
        $label = Str::squish($label);
        $length = mb_strlen($label);

        if ($length < self::LABEL_MIN || $length > self::LABEL_MAX) {
            return ['ok' => false, 'message' => 'El nombre debe tener entre '.self::LABEL_MIN.' y '.self::LABEL_MAX.' caracteres.'];
        }

        if (preg_match('/^[\p{L}\p{N}][\p{L}\p{N} ().,\/·-]*$/u', $label) !== 1) {
            return ['ok' => false, 'message' => 'Use solo letras, números, espacios y los signos ( ) . , / -'];
        }

        $key = self::keyFor($label);
        $actor = self::actor($user);

        $result = DB::transaction(function () use ($key, $label, $actor): array {
            $existing = RetailMainService::query()->where('name', $key)->lockForUpdate()->first();

            if ($existing !== null && $existing->is_active) {
                return ['ok' => false, 'message' => '«'.$existing->label.'» ya está en la lista.'];
            }

            if ($existing !== null) {
                $existing->forceFill(['is_active' => true, 'label' => $label, 'updated_by' => $actor])->save();

                return ['ok' => true, 'reactivated' => true, 'label' => $label];
            }

            RetailMainService::query()->create([
                'name' => $key,
                'label' => $label,
                'is_active' => true,
                'is_protected' => false,
                'sort' => ((int) RetailMainService::query()->max('sort')) + 10,
                'created_by' => $actor,
                'updated_by' => $actor,
            ]);

            return ['ok' => true, 'reactivated' => false, 'label' => $label];
        });

        if ($result['ok']) {
            SecurityAudit::log('AUDIT_RETAIL_MAIN_SERVICE_ADDED', 'operations.retail-main-services.add', ['service' => $key, 'reactivated' => $result['reactivated']], $user);
        }

        return $result;
    }

    /**
     * Quita (desactiva) un servicio. Telemedicina y AMD no se pueden quitar.
     *
     * @return array{ok: true, label: string}|array{ok: false, message: string}
     */
    public static function remove(int $id, ?User $user): array
    {
        $actor = self::actor($user);

        $result = DB::transaction(function () use ($id, $actor): array {
            $service = RetailMainService::query()->whereKey($id)->lockForUpdate()->first();

            if ($service === null || ! $service->is_active) {
                return ['ok' => false, 'message' => 'Ese servicio ya no está en la lista.'];
            }

            if ($service->is_protected) {
                return ['ok' => false, 'message' => '«'.$service->label.'» no se puede quitar: llega al médico de guardia.'];
            }

            $service->forceFill(['is_active' => false, 'updated_by' => $actor])->save();

            return ['ok' => true, 'label' => (string) $service->label, 'name' => (string) $service->name];
        });

        if ($result['ok']) {
            SecurityAudit::log('AUDIT_RETAIL_MAIN_SERVICE_REMOVED', 'operations.retail-main-services.remove', ['service' => $result['name']], $user);
            unset($result['name']);
        }

        return $result;
    }

    public static function flush(): void
    {
        self::$memo = null;

        try {
            Cache::forget(self::CACHE_KEY);
        } catch (Throwable) {
        }
    }

    /**
     * @return array<string, array{label: string, active: bool, protected: bool, id: int}>
     */
    private static function rows(): array
    {
        if (self::$memo !== null && time() - self::$memoAt < self::MEMO_SECONDS) {
            return self::$memo;
        }

        try {
            $rows = Cache::remember(self::CACHE_KEY, now()->addMinutes(10), fn (): array => RetailMainService::query()
                ->orderBy('sort')
                ->orderBy('id')
                ->get(['id', 'name', 'label', 'is_active', 'is_protected'])
                ->mapWithKeys(fn (RetailMainService $service): array => [(string) $service->name => [
                    'label' => (string) $service->label,
                    'active' => (bool) $service->is_active,
                    'protected' => (bool) $service->is_protected,
                    'id' => (int) $service->id,
                ]])
                ->all());
        } catch (Throwable) {
            $rows = [];
        }

        if ($rows === []) {
            $rows = collect(RegisterTpaRetailServicesAction::LEGACY_SERVICES)
                ->mapWithKeys(fn (string $name): array => [$name => ['label' => '', 'active' => true, 'protected' => true, 'id' => 0]])
                ->all();
        }

        self::$memoAt = time();

        return self::$memo = $rows;
    }

    private static function actor(?User $user): string
    {
        return mb_substr(trim((string) ($user?->name ?: $user?->email ?: 'system')), 0, 120);
    }
}
