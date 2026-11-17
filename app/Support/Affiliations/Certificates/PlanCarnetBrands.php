<?php

declare(strict_types=1);

namespace App\Support\Affiliations\Certificates;

use App\Models\Plan;
use App\Models\PlanCarnetBrand;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Marca del carnet por plan (p. ej. Renacer Ecofuneral en el plan funerario).
 *
 * Cambia solo los carnets de los afiliados de ese plan, en el PDF del
 * Generador de Certificado y en el carnet digital que abre el QR: logo, color
 * de todos los detalles que en el estándar son azules y el nombre del pie.
 * El fondo gris del frente, los datos del afiliado, los teléfonos y el correo
 * quedan como en el estándar. Sin marca, el plan sale con el carnet de siempre.
 *
 * Los certificados ya guardados no cambian hasta que se emiten de nuevo.
 */
final class PlanCarnetBrands
{
    public const DIRECTORY = 'plan-carnet-brands';

    public const MAX_KILOBYTES = 5120;

    public const DEFAULT_COLOR = '#2F6034';

    public const LABEL_MAX_LENGTH = 80;

    /**
     * @var list<string>
     */
    public const ACCEPTED_TYPES = ['image/png', 'image/jpeg'];

    /** Caja del logo en el frente del carnet (px de la plantilla): un logo redondo queda de 44 × 44. */
    public const LOGO_BOX_WIDTH = 150;

    public const LOGO_BOX_HEIGHT = 44;

    /** Base gris del carnet estándar: la marca solo cambia los acentos. */
    private const BASE = ['bg' => '#EFEFEF', 'ink' => '#12293A', 'title' => '#12293A', 'wave' => '#E4E6E8'];

    private static ?bool $available = null;

    /** @var array<int, PlanCarnetBrand|null> */
    private static array $memo = [];

    public static function available(): bool
    {
        return self::$available ??= Schema::hasTable('plan_carnet_brands');
    }

    /** @var array<int, true>|null plan_id con marca (una consulta por petición, para la tabla de planes) */
    private static ?array $planIds = null;

    public static function flush(): void
    {
        self::$available = null;
        self::$memo = [];
        self::$planIds = null;
    }

    public static function hasBrand(Plan $plan): bool
    {
        if (! self::available()) {
            return false;
        }

        self::$planIds ??= array_fill_keys(PlanCarnetBrand::query()->pluck('plan_id')->map(fn (mixed $id): int => (int) $id)->all(), true);

        return isset(self::$planIds[(int) $plan->getKey()]);
    }

    public static function recordFor(?int $planId): ?PlanCarnetBrand
    {
        if ($planId === null || $planId < 1 || ! self::available()) {
            return null;
        }

        if (! array_key_exists($planId, self::$memo)) {
            self::$memo[$planId] = PlanCarnetBrand::query()->where('plan_id', $planId)->first();
        }

        return self::$memo[$planId];
    }

    /**
     * Marca lista para la plantilla PDF de los planes dados, por plan_id.
     *
     * @param  list<int|null>  $planIds
     * @return array<int, array{th: array<string, string>, logo: string, wave: string, accent: string, accentInk: string, label: string}>
     */
    public static function forDocument(array $planIds): array
    {
        $brands = [];

        foreach (array_unique(array_filter(array_map('intval', $planIds))) as $planId) {
            try {
                $record = self::recordFor($planId);
                if ($record !== null) {
                    $brands[$planId] = self::documentBrand($record);
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $brands;
    }

    /**
     * Marca para el carnet digital (página pública del QR).
     *
     * @return array{color: string, dark: string, light: string, soft: string, rgb: string, darkRgb: string, lightRgb: string, logoUrl: ?string, label: string}|null
     */
    public static function forWeb(?int $planId): ?array
    {
        try {
            $record = self::recordFor($planId);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }

        if ($record === null) {
            return null;
        }

        $color = self::normalizeColor($record->color) ?? self::DEFAULT_COLOR;
        $dark = self::mix($color, '#000000', 0.4);
        $light = self::mix($color, '#FFFFFF', 0.3);
        $logoPath = self::existingLogo($record->logo_path);

        return [
            'color' => $color,
            'dark' => $dark,
            'light' => $light,
            'soft' => self::mix($color, '#4A4A4A', 0.35),
            'rgb' => self::rgb($color),
            'darkRgb' => self::rgb($dark),
            'lightRgb' => self::rgb($light),
            'logoUrl' => $logoPath !== null ? Storage::disk('public')->url($logoPath) : null,
            'label' => (string) $record->company_label,
        ];
    }

    /**
     * Cambia los azules del contenido del carnet digital por el color de la marca.
     *
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    public static function recolor(array $content, string $color): array
    {
        array_walk_recursive($content, static function (mixed &$value) use ($color): void {
            if (is_string($value) && in_array(strtoupper($value), ['#2D89CA', '#26B4E8'], true)) {
                $value = $color;
            }
        });

        return $content;
    }

    /**
     * @param  array{logo_path?: mixed, color?: mixed, company_label?: mixed}  $data
     */
    public static function save(Plan $plan, array $data, User $user): PlanCarnetBrand
    {
        $color = self::normalizeColor($data['color'] ?? null);
        $label = trim(preg_replace('/\s+/u', ' ', (string) ($data['company_label'] ?? '')) ?? '');
        $logo = $data['logo_path'] ?? null;
        $logo = is_array($logo) ? (reset($logo) ?: null) : $logo;
        $logo = filled($logo) ? (string) $logo : null;

        $errors = [];
        if ($color === null) {
            $errors['color'] = 'Elija un color válido (formato #RRGGBB).';
        }
        if ($label === '' || mb_strlen($label) > self::LABEL_MAX_LENGTH) {
            $errors['company_label'] = 'Escriba el nombre del pie del carnet (máximo '.self::LABEL_MAX_LENGTH.' caracteres).';
        }
        if ($logo === null || self::existingLogo($logo) === null) {
            $errors['logo_path'] = 'Suba el logo del carnet (PNG o JPG).';
        } elseif (! self::isReadableImage((string) self::existingLogo($logo))) {
            $errors['logo_path'] = 'El logo no es una imagen PNG o JPG válida.';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $brand = DB::transaction(function () use ($plan, $color, $label, $logo, $user): PlanCarnetBrand {
            $brand = PlanCarnetBrand::query()->lockForUpdate()->firstOrNew(['plan_id' => (int) $plan->getKey()]);

            if (! $brand->exists) {
                $brand->created_by = $user->name;
            }

            $brand->fill([
                'logo_path' => $logo,
                'color' => $color,
                'company_label' => $label,
                'updated_by' => $user->name,
            ])->save();

            return $brand;
        });

        self::$memo = [];
        self::$planIds = null;

        return $brand;
    }

    /**
     * Quita la marca: el plan vuelve al carnet estándar. El archivo del logo se
     * conserva porque un certificado en curso puede estar dibujándose con él.
     */
    public static function remove(Plan $plan): void
    {
        PlanCarnetBrand::query()->where('plan_id', (int) $plan->getKey())->delete();
        self::$memo = [];
        self::$planIds = null;
    }

    public static function normalizeColor(mixed $value): ?string
    {
        $value = strtoupper(trim((string) $value));

        if (preg_match('/^#?([0-9A-F]{3})$/', $value, $short) === 1) {
            return '#'.implode('', array_map(static fn (string $c): string => $c.$c, str_split($short[1])));
        }

        return preg_match('/^#?([0-9A-F]{6})$/', $value, $full) === 1 ? '#'.$full[1] : null;
    }

    /**
     * Ruta del logo en el disco público, solo dentro del directorio de la marca.
     */
    public static function existingLogo(?string $relativePath): ?string
    {
        $relativePath = trim((string) $relativePath);

        if ($relativePath === ''
            || ! str_starts_with($relativePath, self::DIRECTORY.'/')
            || str_contains($relativePath, '..')
            || str_contains($relativePath, "\0")) {
            return null;
        }

        return Storage::disk('public')->exists($relativePath) ? $relativePath : null;
    }

    /**
     * @return array{th: array<string, string>, logo: string, wave: string, accent: string, accentInk: string, label: string}
     */
    private static function documentBrand(PlanCarnetBrand $record): array
    {
        $color = self::normalizeColor($record->color) ?? self::DEFAULT_COLOR;
        $logoPath = self::existingLogo($record->logo_path);
        $logo = $logoPath !== null
            ? CertificateAssets::fittedImage(Storage::disk('public')->path($logoPath), self::LOGO_BOX_WIDTH * 4, self::LOGO_BOX_HEIGHT * 4)
            : '';

        return [
            'th' => [
                'bg' => self::BASE['bg'],
                'ink' => self::BASE['ink'],
                'sub' => $color,
                'title' => self::BASE['title'],
                'pillBg' => $color,
                'pillInk' => '#FFFFFF',
            ],
            'logo' => $logo,
            'wave' => CertificateAssets::wave(self::BASE['wave'], 324, 40, 'M0 30 C 90 6, 190 42, 324 12 L324 40 L0 40 Z'),
            'accent' => $color,
            'accentInk' => '#FFFFFF',
            'label' => (string) $record->company_label,
        ];
    }

    private static function isReadableImage(string $relativePath): bool
    {
        $info = @getimagesize(Storage::disk('public')->path($relativePath));

        return $info !== false && in_array($info[2] ?? null, [IMAGETYPE_PNG, IMAGETYPE_JPEG], true);
    }

    private static function mix(string $color, string $with, float $amount): string
    {
        [$r1, $g1, $b1] = sscanf($color, '#%02x%02x%02x');
        [$r2, $g2, $b2] = sscanf($with, '#%02x%02x%02x');

        return sprintf(
            '#%02X%02X%02X',
            (int) round($r1 + ($r2 - $r1) * $amount),
            (int) round($g1 + ($g2 - $g1) * $amount),
            (int) round($b1 + ($b2 - $b1) * $amount),
        );
    }

    private static function rgb(string $color): string
    {
        return implode(', ', sscanf($color, '#%02x%02x%02x'));
    }
}
