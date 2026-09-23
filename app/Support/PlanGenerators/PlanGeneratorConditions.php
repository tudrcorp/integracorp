<?php

declare(strict_types=1);

namespace App\Support\PlanGenerators;

use App\Models\PlanGenerator;
use Closure;
use Filament\Forms\Components\Textarea;

/**
 * Condiciones que el analista pega o escribe en una cotización derivada.
 *
 * Es un solo texto, no una lista de filas: los saltos de línea, las viñetas y
 * el orden del pegado se conservan debajo del total grupal y en el PDF.
 */
final class PlanGeneratorConditions
{
    /**
     * Conserva el texto y sus saltos de línea. Solo recorta el blanco de los
     * extremos del bloque.
     */
    public static function normalize(mixed $raw): string
    {
        if (is_array($raw)) {
            $raw = self::joinLegacyList($raw);
        }

        if (! is_string($raw) && ! is_numeric($raw)) {
            return '';
        }

        $text = str_replace(["\r\n", "\r"], "\n", (string) $raw);

        return trim($text);
    }

    /**
     * Valor para el cuadro de texto. Un registro viejo guardado como lista
     * JSON se muestra como líneas, para no perder lo ya escrito.
     */
    public static function formState(mixed $raw): string
    {
        return self::fromLegacyStorage($raw);
    }

    /**
     * Convierte el JSON de la lista anterior en texto con un renglón por ítem.
     */
    public static function fromLegacyStorage(mixed $raw): string
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $raw = $decoded;
            }
        }

        if (is_array($raw)) {
            return self::normalize(self::joinLegacyList($raw));
        }

        return self::normalize($raw);
    }

    public static function field(bool $onlyOnDerivedRecord = false): Textarea
    {
        $field = Textarea::make('conditions')
            ->label('Condiciones')
            ->helperText('Pegue el texto con el formato que ya tenga. Los saltos de línea y las viñetas se conservan debajo del total grupal y en el PDF.')
            ->placeholder("Ej:\n* Cotización válida por 15 días.\n* Tarifas en dólares, no incluyen IVA.")
            ->rows(10)
            ->autosize()
            ->required()
            ->rule(static function (): Closure {
                return static function (string $attribute, mixed $value, Closure $fail): void {
                    if (self::normalize($value) === '') {
                        $fail('Escriba las condiciones.');
                    }
                };
            })
            ->validationMessages([
                'required' => 'Escriba las condiciones.',
            ])
            ->columnSpanFull();

        if ($onlyOnDerivedRecord) {
            $visible = fn (?PlanGenerator $record): bool => (bool) $record?->isDerivedQuotation();

            $field->visible($visible)->dehydrated($visible);
        }

        return $field;
    }

    /**
     * @param  array<mixed>  $raw
     */
    private static function joinLegacyList(array $raw): string
    {
        $lines = [];

        foreach (self::textsFrom($raw) as $text) {
            $text = str_replace(["\r\n", "\r"], "\n", $text);

            if (trim($text) === '') {
                continue;
            }

            $lines[] = $text;
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<mixed>  $raw
     * @return list<string>
     */
    private static function textsFrom(array $raw): array
    {
        $texts = [];

        foreach ($raw as $item) {
            if (is_string($item) || is_numeric($item)) {
                $texts[] = (string) $item;

                continue;
            }

            if (! is_array($item)) {
                continue;
            }

            if (array_key_exists('text', $item)) {
                $texts[] = (string) $item['text'];

                continue;
            }

            foreach ($item as $nested) {
                if (is_array($nested) && array_key_exists('text', $nested)) {
                    $texts[] = (string) $nested['text'];
                }
            }
        }

        return $texts;
    }
}
