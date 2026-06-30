<?php

declare(strict_types=1);

namespace App\Support\GuiaChat;

enum GuiaChatFeedbackType: string
{
    case ServiceSuggestion = 'service_suggestion';
    case GuiaChatBug = 'guia_chat_bug';
    case IntegracorpBug = 'integracorp_bug';

    public function label(): string
    {
        return match ($this) {
            self::ServiceSuggestion => 'Sugerencia de mejora',
            self::GuiaChatBug => 'Falla GUIA-CHAT',
            self::IntegracorpBug => 'Falla INTEGRACORP',
        };
    }

    public function filamentColor(): string
    {
        return match ($this) {
            self::ServiceSuggestion => 'info',
            self::GuiaChatBug => 'warning',
            self::IntegracorpBug => 'danger',
        };
    }

    public function requiresReporterName(): bool
    {
        return match ($this) {
            self::ServiceSuggestion => false,
            self::GuiaChatBug, self::IntegracorpBug => true,
        };
    }

    public static function tryFromString(?string $value): ?self
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::tryFrom($value);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type): array => [$type->value => $type->label()])
            ->all();
    }
}
