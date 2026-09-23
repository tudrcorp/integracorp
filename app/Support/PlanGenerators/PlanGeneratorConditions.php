<?php

declare(strict_types=1);

namespace App\Support\PlanGenerators;

use App\Models\PlanGenerator;
use Closure;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;

/**
 * Condiciones que el analista escribe a mano en una cotización derivada.
 *
 * La lista es ordenada y puede tener una sola condición o varias. Se muestra
 * debajo del total grupal. El registro base no las pide.
 */
final class PlanGeneratorConditions
{
    public const MAX_ITEMS = 20;

    public const MAX_LENGTH = 500;

    /**
     * Deja solo texto útil, en el orden en que lo escribió el analista.
     *
     * Acepta la lista ya guardada, el estado deshidratado del Repeater
     * (`['texto', ...]`) y el estado vivo (`uuid => ['text' => 'texto']`).
     *
     * @return list<string>
     */
    public static function normalize(mixed $raw): array
    {
        $items = [];

        foreach (self::textsFrom($raw) as $text) {
            $text = trim((string) preg_replace('/\s+/u', ' ', $text));

            if ($text === '') {
                continue;
            }

            if (mb_strlen($text) > self::MAX_LENGTH) {
                $text = rtrim(mb_substr($text, 0, self::MAX_LENGTH));
            }

            $items[] = $text;

            if (count($items) >= self::MAX_ITEMS) {
                break;
            }
        }

        return $items;
    }

    /**
     * Una fila en blanco para que el analista empiece a escribir.
     *
     * @param  list<string>  $conditions
     * @return list<string>
     */
    public static function formState(array $conditions): array
    {
        return $conditions === [] ? [''] : $conditions;
    }

    public static function field(bool $onlyOnDerivedRecord = false): Repeater
    {
        $field = Repeater::make('conditions')
            ->label('Condiciones')
            ->helperText('Escríbalas a mano. Puede agregar una o varias. Aparecen debajo del total grupal en la cotización y en el PDF.')
            ->addActionLabel('Agregar condición')
            ->reorderable()
            ->minItems(1)
            ->maxItems(self::MAX_ITEMS)
            ->defaultItems(1)
            ->simple(
                Textarea::make('text')
                    ->hiddenLabel()
                    ->placeholder('Ej: Cotización válida por 15 días. Tarifas en dólares, no incluyen IVA.')
                    ->rows(2)
                    ->maxLength(self::MAX_LENGTH)
                    ->required()
                    ->rule(static function (): Closure {
                        return static function (string $attribute, mixed $value, Closure $fail): void {
                            if (trim((string) $value) === '') {
                                $fail('Escriba la condición.');
                            }
                        };
                    })
                    ->validationMessages([
                        'required' => 'Escriba la condición.',
                        'max' => 'La condición no puede superar los '.self::MAX_LENGTH.' caracteres.',
                    ]),
            )
            ->validationMessages([
                'min' => 'Agregue al menos una condición.',
                'max' => 'Puede registrar hasta '.self::MAX_ITEMS.' condiciones.',
            ])
            ->columnSpanFull();

        if ($onlyOnDerivedRecord) {
            $visible = fn (?PlanGenerator $record): bool => (bool) $record?->isDerivedQuotation();

            $field->visible($visible)->dehydrated($visible);
        }

        return $field;
    }

    /**
     * @return list<string>
     */
    private static function textsFrom(mixed $raw): array
    {
        if (is_string($raw) || is_numeric($raw)) {
            return [(string) $raw];
        }

        if (! is_array($raw)) {
            return [];
        }

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
