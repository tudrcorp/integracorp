<?php

declare(strict_types=1);

namespace App\Support;

final class RunReportMessageFormatter
{
    public static function executionTimestamp(): string
    {
        return '📅 '.now()->timezone(config('app.timezone'))->format('d/m/Y H:i');
    }

    /**
     * @return list<string>
     */
    public static function titleLines(string $emoji, string $title): array
    {
        return [
            "{$emoji} *{$title}*",
            self::executionTimestamp(),
            '',
        ];
    }

    /**
     * @param  list<string>  $bullets
     * @return list<string>
     */
    public static function bulletSection(string $heading, array $bullets): array
    {
        if ($bullets === []) {
            return [];
        }

        $lines = ["ℹ️ *{$heading}*"];

        foreach ($bullets as $bullet) {
            $lines[] = '• '.$bullet;
        }

        $lines[] = '';

        return $lines;
    }

    /**
     * @return list<string>
     */
    public static function criticalFailureLines(bool $criticalFailure, ?string $criticalMessage): array
    {
        if (! $criticalFailure) {
            return [];
        }

        return [
            '⚠️ *La ejecución terminó con error crítico.*',
            $criticalMessage ?? 'Error no especificado.',
            '',
        ];
    }

    /**
     * @param  array<string, int|float|string>  $details
     * @return list<string>
     */
    public static function configurationSection(string $heading, array $details): array
    {
        if ($details === []) {
            return [];
        }

        $lines = ["⚙️ *{$heading}*"];

        foreach ($details as $label => $value) {
            $lines[] = '• '.$label.': '.$value;
        }

        $lines[] = '';

        return $lines;
    }

    public static function truncateForWhatsAppCaption(string $text, int $maxLength = 900): string
    {
        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        return mb_substr($text, 0, $maxLength - 1).'…';
    }
}
