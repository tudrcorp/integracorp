<?php

declare(strict_types=1);

namespace App\Filament\Shared\Affiliations;

use App\Support\PlanGenerators\PlanGeneratorPreAffiliationSession;
use App\Support\Upgrades\UpgradeBillingChoice;
use Closure;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

/**
 * «¿Cómo se cobrará el upgrade?» al afiliar desde una cotización del generador con upgrades.
 * Obligatorio. Sugiere la frecuencia de la afiliación si la cotización la ofreció repartida
 * (si no, Anual) y se vuelve a sugerir al cambiar la frecuencia. Solo admite Anual o la misma
 * frecuencia de la afiliación: cualquier otra bloquea el guardado.
 */
final class UpgradeBillingField
{
    public static function make(string $frequencyField = 'payment_frequency'): ToggleButtons
    {
        return ToggleButtons::make('upgrade_billing_frequency')
            ->label('¿Cómo se cobrará el upgrade?')
            ->helperText(fn (Get $get): string => 'En la cotización el cliente vio: '.self::offeredLabel().'. '
                .'Anual: completo en el primer pago. Para repartirlo en cada cuota, elija la misma frecuencia de la afiliación'
                .(filled($get($frequencyField)) ? ' ('.mb_strtolower((string) $get($frequencyField)).')' : '').'. Se aplica a todos los upgrades de esta afiliación.')
            ->options(UpgradeBillingChoice::options())
            ->default(fn (Get $get): string => PlanGeneratorPreAffiliationSession::suggestedUpgradeBillingFrequency($get($frequencyField)))
            ->inline()
            ->live()
            ->visible(fn (): bool => self::applies())
            ->required(fn (): bool => self::applies())
            ->rules([
                fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $frequencyField): void {
                    $error = UpgradeBillingChoice::error($value, $get($frequencyField));

                    if ($error !== null) {
                        $fail($error);
                    }
                },
            ])
            ->validationMessages(['required' => 'Indique cómo se cobrará el upgrade.'])
            ->columnSpanFull();
    }

    /**
     * Al cambiar la frecuencia de la afiliación: sugiere la forma de cobro que la cotización
     * ofreció para esa frecuencia (o Anual). El analista puede cambiarla.
     */
    public static function syncWithFrequency(Set $set, mixed $frequency): void
    {
        if (self::applies()) {
            $set('upgrade_billing_frequency', PlanGeneratorPreAffiliationSession::suggestedUpgradeBillingFrequency($frequency));
        }
    }

    public static function applies(): bool
    {
        return PlanGeneratorPreAffiliationSession::isActive() && PlanGeneratorPreAffiliationSession::hasUpgrades();
    }

    private static function offeredLabel(): string
    {
        return implode(', ', array_map(
            fn (string $choice): string => mb_strtolower(UpgradeBillingChoice::shortLabel($choice)),
            PlanGeneratorPreAffiliationSession::offeredUpgradeBillingFrequencies(),
        ));
    }

    /**
     * Guarda del servidor: valida otra vez y deja la elección lista para guardar (o vacía si la
     * afiliación no trae upgrades: se preguntará al agregar uno).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizeForCreate(array $data): array
    {
        if (! self::applies()) {
            $data['upgrade_billing_frequency'] = null;

            return $data;
        }

        $data['upgrade_billing_frequency'] = UpgradeBillingChoice::assertValid(
            $data['upgrade_billing_frequency'] ?? null,
            $data['payment_frequency'] ?? null,
            'data.upgrade_billing_frequency',
        );

        return $data;
    }
}
