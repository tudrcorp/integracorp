<?php

declare(strict_types=1);

namespace App\Support\Storefront;

use App\Models\Plan;

/**
 * Copia comercial de cada plan básico. El catálogo guarda el nombre
 * técnico; aquí se convierte en una ficha de producto.
 *
 * @phpstan-type Narrative array{
 *     key: string,
 *     title: string,
 *     kicker: string,
 *     promise: string,
 *     audience: string,
 *     accent: string,
 *     accent_rgb: string,
 *     cover: string
 * }
 */
final class StorefrontPlanNarrative
{
    /**
     * @return Narrative
     */
    public static function for(Plan $plan): array
    {
        $key = self::keyFor($plan);
        $presets = self::presets();

        if (isset($presets[$key])) {
            $preset = $presets[$key];
            $preset['title'] = filled($plan->description)
                ? self::displayTitle((string) $plan->description)
                : $preset['title'];

            return $preset;
        }

        return [
            'key' => 'otro',
            'title' => self::displayTitle((string) ($plan->description ?: 'Plan de asistencia')),
            'kicker' => 'Asistencia médica',
            'promise' => 'Cobertura pensada para acompañarte cuando más lo necesitas, con beneficios claros y tarifas por edad.',
            'audience' => 'Para ti y tu grupo familiar.',
            'accent' => '#7dd3fc',
            'accent_rgb' => '125, 211, 252',
            'cover' => 'image/storefront/plan-inicial.jpg',
        ];
    }

    public static function keyFor(Plan $plan): string
    {
        if (in_array((int) $plan->getKey(), [1], true)) {
            return 'inicial';
        }

        if (in_array((int) $plan->getKey(), [2], true)) {
            return 'ideal';
        }

        if (in_array((int) $plan->getKey(), [3], true)) {
            return 'especial';
        }

        $haystack = mb_strtoupper(trim((string) $plan->description.' '.$plan->code));

        if (str_contains($haystack, 'INICIAL')) {
            return 'inicial';
        }

        if (str_contains($haystack, 'IDEAL')) {
            return 'ideal';
        }

        if (str_contains($haystack, 'ESPECIAL')) {
            return 'especial';
        }

        return 'otro';
    }

    public static function displayTitle(string $description): string
    {
        $trimmed = trim($description);

        if ($trimmed === '') {
            return 'Plan';
        }

        if (preg_match('/^plan\s+/iu', $trimmed) === 1) {
            return $trimmed;
        }

        return 'Plan '.$trimmed;
    }

    public static function formatMoney(float $amount): string
    {
        if (floor($amount) == $amount) {
            return 'US$ '.number_format($amount, 0, ',', '.');
        }

        return 'US$ '.number_format($amount, 2, ',', '.');
    }

    public static function sentenceLabel(string $text): string
    {
        $trimmed = trim($text);

        if ($trimmed === '') {
            return $trimmed;
        }

        $letters = preg_replace('/[^\p{L}]+/u', '', $trimmed) ?? '';
        $isAllCaps = $letters !== '' && $letters === mb_strtoupper($letters, 'UTF-8');

        if (! $isAllCaps) {
            return $trimmed;
        }

        $lower = mb_strtolower($trimmed, 'UTF-8');

        return mb_strtoupper(mb_substr($lower, 0, 1, 'UTF-8'), 'UTF-8').mb_substr($lower, 1, null, 'UTF-8');
    }

    /**
     * Nombre para lectura: el borrador se guarda en mayúsculas.
     * Iniciales cortas (SD) se dejan; nombres largos pasan a título.
     */
    public static function personName(string $name): string
    {
        $trimmed = trim($name);

        if ($trimmed === '') {
            return $trimmed;
        }

        $letters = preg_replace('/[^\p{L}]+/u', '', $trimmed) ?? '';

        if ($letters === '') {
            return $trimmed;
        }

        $isAllCaps = $letters === mb_strtoupper($letters, 'UTF-8');

        if (! $isAllCaps) {
            return $trimmed;
        }

        if (mb_strlen($letters, 'UTF-8') <= 3) {
            return mb_strtoupper($trimmed, 'UTF-8');
        }

        return mb_convert_case(mb_strtolower($trimmed, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    }

    public static function phoneLabel(string $phone): string
    {
        $trimmed = trim($phone);
        $digits = preg_replace('/\D+/', '', $trimmed) ?? '';

        if (strlen($digits) === 12 && str_starts_with($digits, '58')) {
            return self::phoneLabel('0'.substr($digits, 2));
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            return substr($digits, 0, 4).' '.substr($digits, 4, 3).' '.substr($digits, 7);
        }

        if (strlen($digits) === 10) {
            return substr($digits, 0, 4).' '.substr($digits, 4, 3).' '.substr($digits, 7);
        }

        return $trimmed;
    }

    public static function planLabel(string $text): string
    {
        $trimmed = trim($text);

        if ($trimmed === '') {
            return 'Plan';
        }

        if (preg_match('/^plan\s+(.+)$/iu', $trimmed, $matches) === 1) {
            return 'Plan '.self::titleCaseLabel($matches[1]);
        }

        return self::titleCaseLabel($trimmed);
    }

    public static function titleCaseLabel(string $text): string
    {
        $trimmed = trim($text);

        if ($trimmed === '') {
            return $trimmed;
        }

        $letters = preg_replace('/[^\p{L}]+/u', '', $trimmed) ?? '';
        $isAllCaps = $letters !== '' && $letters === mb_strtoupper($letters, 'UTF-8');

        if (! $isAllCaps) {
            return $trimmed;
        }

        return mb_convert_case(mb_strtolower($trimmed, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Versión WebP de la portada si existe en disco (mismo look, menos peso).
     */
    public static function coverWebp(string $cover): ?string
    {
        $cover = ltrim(str_replace('\\', '/', $cover), '/');

        if ($cover === '' || ! str_ends_with(mb_strtolower($cover), '.jpg')) {
            return null;
        }

        $webp = preg_replace('/\.jpg$/i', '.webp', $cover) ?? '';

        if ($webp === '' || $webp === $cover) {
            return null;
        }

        return is_file(public_path($webp)) ? $webp : null;
    }

    /**
     * @return array<string, Narrative>
     */
    private static function presets(): array
    {
        return [
            'inicial' => [
                'key' => 'inicial',
                'title' => 'Plan Inicial',
                'kicker' => '',
                'promise' => 'Orientación médica y beneficios esenciales para empezar a cuidarte sin complicaciones. Ideal si buscas una red de asistencia clara, cercana y al alcance.',
                'audience' => 'Atención médica en tu domicilio cuando la necesitas: orientación telefónica, consultas, laboratorio e imágenes, sin salir de casa. Hasta los 99 años.',
                'accent' => '#38bdf8',
                'accent_rgb' => '56, 189, 248',
                'cover' => 'image/storefront/plan-inicial.jpg',
            ],
            'ideal' => [
                'key' => 'ideal',
                'title' => 'Plan Ideal',
                'kicker' => '',
                'promise' => 'Más protección, más red y más tranquilidad. Un equilibrio entre cobertura amplia y una tarifa que se entiende, con beneficios que se sienten en el día a día.',
                'audience' => 'Todo lo del Plan Inicial, más consulta con especialistas y cobertura si tienes un accidente. Para cuando quieres un respaldo más completo. Hasta los 85 años.',
                'accent' => '#2dd4bf',
                'accent_rgb' => '45, 212, 191',
                'cover' => 'image/storefront/plan-ideal.jpg',
            ],
            'especial' => [
                'key' => 'especial',
                'title' => 'Plan Especial',
                'kicker' => '',
                'promise' => 'La protección más completa de la línea básica: topes más altos, más beneficios y la certeza de tener a Tu Dr En Casa cuando el momento lo pide.',
                'audience' => 'Nuestra cobertura más amplia: todo lo del Plan Ideal, más protección ante emergencias médicas. Tranquilidad total para tu familia. Hasta los 85 años.',
                'accent' => '#fbbf24',
                'accent_rgb' => '251, 191, 36',
                'cover' => 'image/storefront/plan-especial.jpg',
            ],
        ];
    }
}
