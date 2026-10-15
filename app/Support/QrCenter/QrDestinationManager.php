<?php

declare(strict_types=1);

namespace App\Support\QrCenter;

use App\Models\Plan;
use App\Models\QrDestination;
use App\Models\QrDestinationVersion;
use App\Models\QrHostedPage;
use App\Models\User;
use App\Services\AffiliationBusinessDocumentsService;
use App\Support\Companies\CompanyAssociateInclusionQrCatalog;
use App\Support\TarjetaAfiliacionQrPlanCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Destinos del QR de las tarjetas: a dónde lleva cada uno, sus versiones y el
 * historial. El QR impreso codifica `/q/{code}`; aquí se decide a dónde redirige.
 */
final class QrDestinationManager
{
    public const PNG_MAX_BYTES = 2 * 1024 * 1024;

    public const PNG_MIN_SIDE = 300;

    public const PNG_MAX_SIDE = 2400;

    public const URL_MAX = 2000;

    /** Contraste mínimo entre puntos y fondo para que los lectores lo lean sin problemas. */
    public const MIN_CONTRAST = 4.5;

    /** El logo tapa módulos del QR: por encima de esto, aun con corrección alta, falla en móviles. */
    public const LOGO_MAX = 0.3;

    private const DOT_STYLES = ['square', 'dots', 'rounded', 'classy', 'classy-rounded', 'extra-rounded'];

    private const CORNER_SQUARE_STYLES = ['square', 'dot', 'extra-rounded'];

    private const CORNER_DOT_STYLES = ['square', 'dot'];

    /**
     * Todos los destinos posibles, con su estado actual, en el orden de la tabla.
     *
     * @return list<array{key: string, scope: string, plan_id: ?int, name: string, scope_label: string, destination: ?QrDestination}>
     */
    public static function catalog(): array
    {
        $existing = QrDestination::query()
            ->with(['currentVersion.hostedPage:id,name,slug', 'plan:id,description'])
            ->get()
            ->keyBy(fn (QrDestination $destination): string => self::key($destination->scope, $destination->plan_id));

        $rows = [[
            'key' => self::key(QrDestination::SCOPE_INCLUSION, null),
            'scope' => QrDestination::SCOPE_INCLUSION,
            'plan_id' => null,
            'name' => 'Tarjetas de nuevos negocios',
            'scope_label' => self::scopeLabel(QrDestination::SCOPE_INCLUSION),
            'destination' => $existing->get(self::key(QrDestination::SCOPE_INCLUSION, null)),
        ]];

        foreach ([
            QrDestination::SCOPE_INDIVIDUAL => TarjetaAfiliacionQrPlanCatalog::individualSelectOptions(),
            QrDestination::SCOPE_CORPORATE => TarjetaAfiliacionQrPlanCatalog::corporateSelectOptions(),
        ] as $scope => $plans) {
            foreach ($plans as $planId => $description) {
                $rows[] = [
                    'key' => self::key($scope, (int) $planId),
                    'scope' => $scope,
                    'plan_id' => (int) $planId,
                    'name' => Str::title(mb_strtolower((string) $description)),
                    'scope_label' => self::scopeLabel($scope),
                    'destination' => $existing->get(self::key($scope, (int) $planId)),
                ];
            }
        }

        return $rows;
    }

    public static function key(string $scope, ?int $planId): string
    {
        return $scope.':'.($planId ?? 0);
    }

    public static function scopeLabel(string $scope): string
    {
        return match ($scope) {
            QrDestination::SCOPE_INDIVIDUAL => 'Individuales',
            QrDestination::SCOPE_CORPORATE => 'Corporativos',
            default => 'Nuevos negocios',
        };
    }

    public static function find(string $scope, ?int $planId): ?QrDestination
    {
        return QrDestination::query()
            ->where('scope', $scope)
            ->where('plan_key', $planId ?? 0)
            ->first();
    }

    /**
     * El documento propio del alcance: el condicionado del plan o el PDF de inclusión.
     */
    public static function documentUrl(string $scope, ?int $planId): ?string
    {
        if ($scope === QrDestination::SCOPE_INCLUSION) {
            return CompanyAssociateInclusionQrCatalog::pdfPublicUrl();
        }

        return AffiliationBusinessDocumentsService::condicionadoPublicUrlForPlanId($planId);
    }

    public static function documentLabel(string $scope): string
    {
        return $scope === QrDestination::SCOPE_INCLUSION ? 'Documento de inclusión' : 'Condicionado del plan';
    }

    /**
     * El enlace estable que va impreso en el QR.
     */
    public static function stableUrl(QrDestination $destination): string
    {
        return route('qr.go', ['code' => $destination->code]);
    }

    /**
     * A dónde redirige hoy el enlace estable del destino.
     */
    public static function targetUrl(QrDestination $destination): ?string
    {
        $version = $destination->currentVersion;

        return match (true) {
            $version === null => self::documentUrl($destination->scope, $destination->plan_id),
            $version->target_type === QrDestination::TARGET_URL => $version->target_url,
            $version->target_type === QrDestination::TARGET_HOSTED_PAGE => $version->hostedPage !== null
                ? route('qr.page', ['slug' => $version->hostedPage->slug])
                : null,
            default => self::documentUrl($destination->scope, $destination->plan_id),
        };
    }

    /**
     * Reserva (o devuelve) el destino y su enlace estable, para que el editor dibuje
     * el QR con la dirección definitiva antes de guardar.
     */
    public function ensure(string $scope, ?int $planId, User $user): QrDestination
    {
        $this->assertScope($scope, $planId);

        return DB::transaction(function () use ($scope, $planId, $user): QrDestination {
            $existing = QrDestination::query()
                ->where('scope', $scope)
                ->where('plan_key', $planId ?? 0)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            return QrDestination::query()->create([
                'code' => self::newCode(),
                'scope' => $scope,
                'plan_key' => $planId ?? 0,
                'plan_id' => $planId,
                'target_type' => QrDestination::TARGET_AUTOMATIC,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);
        }, 3);
    }

    /**
     * Guarda una versión nueva del QR de un destino.
     *
     * @param  array<string, mixed>  $style
     *
     * @throws ValidationException
     */
    public function save(
        QrDestination $destination,
        string $targetType,
        ?string $targetUrl,
        ?int $hostedPageId,
        array $style,
        string $png,
        User $user,
        ?string $note = null,
    ): QrDestinationVersion {
        if (! in_array($targetType, [QrDestination::TARGET_HOSTED_PAGE, QrDestination::TARGET_URL, QrDestination::TARGET_DOCUMENT], true)) {
            throw ValidationException::withMessages(['targetType' => 'Elige a dónde debe llevar el QR.']);
        }

        $targetUrl = $targetType === QrDestination::TARGET_URL ? $this->validatedUrl($targetUrl) : null;
        $hostedPageId = $targetType === QrDestination::TARGET_HOSTED_PAGE ? $this->validatedPageId($hostedPageId) : null;

        if ($targetType === QrDestination::TARGET_DOCUMENT && self::documentUrl($destination->scope, $destination->plan_id) === null) {
            throw ValidationException::withMessages(['targetType' => 'Este plan no tiene un condicionado publicado. Elige otro destino.']);
        }

        $style = $this->validatedStyle($style);
        $this->assertPng($png);

        return $this->writeVersion($destination, [
            'target_type' => $targetType,
            'target_url' => $targetUrl,
            'qr_hosted_page_id' => $hostedPageId,
            'style' => $style + ['encoded_url' => self::stableUrl($destination)],
            'note' => $note !== null ? Str::limit(trim($note), 250, '') : null,
        ], $png, $user);
    }

    /**
     * Restaura una versión anterior: crea una versión nueva con su destino, su
     * estilo y una copia de su imagen. Nada se borra.
     */
    public function restore(QrDestinationVersion $version, User $user): QrDestinationVersion
    {
        $destination = $version->destination()->firstOrFail();

        if ($version->target_type === QrDestination::TARGET_AUTOMATIC) {
            return $this->resetToAutomatic($destination, $user, 'Restauró la versión '.$version->version);
        }

        if ($version->target_type === QrDestination::TARGET_HOSTED_PAGE && ! QrHostedPage::query()->whereKey($version->qr_hosted_page_id)->exists()) {
            throw ValidationException::withMessages(['restore' => 'La página de esa versión ya no existe.']);
        }

        $png = QrCenterStorage::exists($version->image_path) ? QrCenterStorage::get((string) $version->image_path) : null;

        if ($png === null) {
            throw ValidationException::withMessages(['restore' => 'La imagen de la versión '.$version->version.' no está en el servidor.']);
        }

        return $this->writeVersion($destination, [
            'target_type' => $version->target_type,
            'target_url' => $version->target_url,
            'qr_hosted_page_id' => $version->qr_hosted_page_id,
            'style' => $version->style,
            'note' => 'Restauró la versión '.$version->version,
            'restored_from_version_id' => $version->id,
        ], $png, $user);
    }

    /**
     * Las tarjetas vuelven al QR automático. El enlace estable sigue funcionando
     * (lleva al documento del alcance) para las tarjetas que ya lo tienen impreso.
     */
    public function resetToAutomatic(QrDestination $destination, User $user, ?string $note = null): QrDestinationVersion
    {
        return $this->writeVersion($destination, [
            'target_type' => QrDestination::TARGET_AUTOMATIC,
            'target_url' => null,
            'qr_hosted_page_id' => null,
            'style' => null,
            'note' => $note ?? 'Volvió al QR automático',
        ], null, $user);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function writeVersion(QrDestination $destination, array $attributes, ?string $png, User $user): QrDestinationVersion
    {
        $writtenPath = null;

        try {
            $version = DB::transaction(function () use ($destination, $attributes, $png, $user, &$writtenPath): QrDestinationVersion {
                $locked = QrDestination::query()->lockForUpdate()->findOrFail($destination->id);
                $next = (int) QrDestinationVersion::query()->where('qr_destination_id', $locked->id)->max('version') + 1;

                $imagePath = null;

                if ($png !== null) {
                    $imagePath = QrCenterStorage::destinationImagePath($locked->id, $next);
                    QrCenterStorage::put($imagePath, $png);
                    $writtenPath = $imagePath;
                }

                $version = QrDestinationVersion::query()->create($attributes + [
                    'qr_destination_id' => $locked->id,
                    'version' => $next,
                    'image_path' => $imagePath,
                    'image_sha256' => $png !== null ? hash('sha256', $png) : null,
                    'created_by' => $user->id,
                    'created_by_name' => $user->name,
                ]);

                $locked->forceFill([
                    'target_type' => $attributes['target_type'],
                    'current_version_id' => $version->id,
                    'updated_by' => $user->id,
                ])->save();

                return $version;
            }, 3);
        } catch (Throwable $exception) {
            if ($writtenPath !== null) {
                QrCenterStorage::delete($writtenPath);
            }

            throw $exception;
        }

        CardQrResolver::forget();

        return $version;
    }

    private function assertScope(string $scope, ?int $planId): void
    {
        if (! in_array($scope, QrDestination::scopes(), true)) {
            throw ValidationException::withMessages(['scope' => 'Destino no válido.']);
        }

        if ($scope === QrDestination::SCOPE_INCLUSION) {
            if ($planId !== null) {
                throw ValidationException::withMessages(['scope' => 'Las tarjetas de nuevos negocios no van por plan.']);
            }

            return;
        }

        $allowed = $scope === QrDestination::SCOPE_CORPORATE
            ? TarjetaAfiliacionQrPlanCatalog::allowedCorporatePlanIds()
            : TarjetaAfiliacionQrPlanCatalog::allowedIndividualPlanIds();

        if ($planId === null || ! in_array($planId, $allowed, true)) {
            throw ValidationException::withMessages(['scope' => 'Ese plan no está disponible para '.mb_strtolower(self::scopeLabel($scope)).'.']);
        }

        if (! Plan::query()->whereKey($planId)->exists()) {
            throw ValidationException::withMessages(['scope' => 'El plan ya no existe.']);
        }
    }

    private function validatedUrl(?string $url): string
    {
        $url = trim((string) $url);

        if ($url === '' || strlen($url) > self::URL_MAX || filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw ValidationException::withMessages(['targetUrl' => 'Escribe una dirección completa, por ejemplo https://tudrencasa.com/planes.']);
        }

        if (strtolower((string) parse_url($url, PHP_URL_SCHEME)) !== 'https') {
            throw ValidationException::withMessages(['targetUrl' => 'La dirección debe empezar por https:// (los móviles avisan de las páginas sin cifrar).']);
        }

        $ownHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        $path = (string) parse_url($url, PHP_URL_PATH);

        if (strtolower((string) parse_url($url, PHP_URL_HOST)) === $ownHost && str_starts_with($path, '/q/')) {
            throw ValidationException::withMessages(['targetUrl' => 'Esa dirección es otro QR del centro: crearía un bucle de redirecciones.']);
        }

        return $url;
    }

    private function validatedPageId(?int $pageId): int
    {
        $page = $pageId !== null ? QrHostedPage::query()->whereKey($pageId)->whereNotNull('current_version_id')->first() : null;

        if ($page === null) {
            throw ValidationException::withMessages(['hostedPageId' => 'Elige una página alojada publicada.']);
        }

        return $page->id;
    }

    /**
     * @param  array<string, mixed>  $style
     * @return array<string, mixed>
     */
    private function validatedStyle(array $style): array
    {
        $dot = self::hexColor($style['dotColor'] ?? null) ?? '#000000';
        $background = self::hexColor($style['bgColor'] ?? null) ?? '#ffffff';
        $logoSize = round((float) ($style['logoSize'] ?? 0), 2);
        $hasLogo = (bool) ($style['logo'] ?? false);

        if (self::luminance($dot) >= self::luminance($background)) {
            throw ValidationException::withMessages(['style' => 'Los puntos deben ser más oscuros que el fondo: muchos lectores no leen QR invertidos.']);
        }

        if (self::contrast($dot, $background) < self::MIN_CONTRAST) {
            throw ValidationException::withMessages(['style' => 'Sube el contraste entre los puntos y el fondo para que los móviles lo lean.']);
        }

        if ($hasLogo && ($logoSize < 0.1 || $logoSize > self::LOGO_MAX)) {
            throw ValidationException::withMessages(['style' => 'El logo debe ocupar entre el 10 % y el 30 % del QR.']);
        }

        return [
            'preset' => Str::limit((string) ($style['preset'] ?? 'personalizado'), 40, ''),
            'dotColor' => $dot,
            'bgColor' => $background,
            'dotStyle' => in_array($style['dotStyle'] ?? null, self::DOT_STYLES, true) ? $style['dotStyle'] : 'square',
            'cornerSquareStyle' => in_array($style['cornerSquareStyle'] ?? null, self::CORNER_SQUARE_STYLES, true) ? $style['cornerSquareStyle'] : 'square',
            'cornerDotStyle' => in_array($style['cornerDotStyle'] ?? null, self::CORNER_DOT_STYLES, true) ? $style['cornerDotStyle'] : 'square',
            'logo' => $hasLogo,
            'logoSize' => $hasLogo ? $logoSize : 0,
            'errorCorrection' => 'H',
        ];
    }

    private function assertPng(string $png): void
    {
        if ($png === '' || strlen($png) > self::PNG_MAX_BYTES || ! str_starts_with($png, "\x89PNG\r\n\x1a\n")) {
            throw ValidationException::withMessages(['png' => 'La imagen del QR no es un PNG válido de hasta 2 MB.']);
        }

        $size = @getimagesizefromstring($png);

        if ($size === false || $size[2] !== IMAGETYPE_PNG) {
            throw ValidationException::withMessages(['png' => 'La imagen del QR no se pudo leer.']);
        }

        [$width, $height] = $size;

        if ($width !== $height || $width < self::PNG_MIN_SIDE || $width > self::PNG_MAX_SIDE) {
            throw ValidationException::withMessages(['png' => 'El QR debe ser cuadrado y medir entre '.self::PNG_MIN_SIDE.' y '.self::PNG_MAX_SIDE.' px.']);
        }
    }

    private static function newCode(): string
    {
        // Sin 0/O ni 1/I/L: el código a veces se dicta o se copia a mano.
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

        do {
            $code = '';

            for ($i = 0; $i < 8; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (QrDestination::query()->where('code', $code)->exists());

        return $code;
    }

    public static function hexColor(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^#[0-9a-f]{6}$/i', $value) ? strtolower($value) : null;
    }

    public static function contrast(string $first, string $second): float
    {
        $a = self::luminance($first);
        $b = self::luminance($second);

        return (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
    }

    private static function luminance(string $hex): float
    {
        $channels = array_map(static function (string $pair): float {
            $value = hexdec($pair) / 255;

            return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, str_split(substr($hex, 1), 2));

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }
}
