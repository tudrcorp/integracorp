<?php

declare(strict_types=1);

namespace App\Support\Filament\Administration;

use App\Models\Sale;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\View;
use Illuminate\Support\HtmlString;

final class SaleReciboPagoTestDeliveryForm
{
    /**
     * @return array<int, Tabs>
     */
    public static function unifiedActionSchema(Sale $sale): array
    {
        return [
            Tabs::make('reciboPagoDeliveryTabs')
                ->columnSpanFull()
                ->tabs([
                    Tab::make('Correo')
                        ->icon(Heroicon::OutlinedEnvelope)
                        ->schema(self::emailActionSchema($sale, 'email_')),
                    Tab::make('WhatsApp')
                        ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                        ->schema(self::whatsAppActionSchema($sale, 'whatsapp_')),
                ]),
        ];
    }

    /**
     * @return array<int, Checkbox|Section|Placeholder|TextInput>
     */
    public static function emailActionSchema(Sale $sale, string $fieldPrefix = ''): array
    {
        return [
            self::testModeToggle($fieldPrefix),
            Section::make('Destinatarios de producción')
                ->description('Correos del agente y la agencia.')
                ->visible(fn (Get $get): bool => ! self::isTestMode($get, $fieldPrefix))
                ->schema([
                    self::productionPreviewPlaceholder(
                        'filament.administration.sales.recibo-pago-email-modal',
                        $sale,
                        SaleReciboPagoEmailRecipients::resolve($sale),
                        $fieldPrefix,
                    ),
                ]),
            Section::make('Interfaz de prueba')
                ->description('Envío único a un correo de prueba. No se notifica al agente, agencia ni copias internas.')
                ->visible(fn (Get $get): bool => self::isTestMode($get, $fieldPrefix))
                ->schema([
                    self::testEmailField($fieldPrefix),
                ]),
        ];
    }

    /**
     * @return array<int, Checkbox|Section|Placeholder|TextInput>
     */
    public static function whatsAppActionSchema(Sale $sale, string $fieldPrefix = ''): array
    {
        return [
            self::testModeToggle($fieldPrefix),
            Section::make('Destinatarios de producción')
                ->description('Teléfonos del agente, la agencia y copia a Sol Rodriguez.')
                ->visible(fn (Get $get): bool => ! self::isTestMode($get, $fieldPrefix))
                ->schema([
                    self::productionPreviewPlaceholder(
                        'filament.administration.sales.recibo-pago-whatsapp-modal',
                        $sale,
                        SaleReciboPagoWhatsAppRecipients::resolve($sale),
                        $fieldPrefix,
                    ),
                ]),
            Section::make('Interfaz de prueba')
                ->description('Envío único a un teléfono de prueba. No se notifica al agente, agencia ni contactos internos.')
                ->visible(fn (Get $get): bool => self::isTestMode($get, $fieldPrefix))
                ->schema([
                    self::testPhoneField($fieldPrefix),
                ]),
        ];
    }

    public static function isTestMode(Get $get, string $fieldPrefix = ''): bool
    {
        return (bool) $get($fieldPrefix.'use_test_delivery');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function isTestModeFromData(array $data): bool
    {
        return (bool) ($data['use_test_delivery'] ?? false);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizeEmailFormData(array $data): array
    {
        if (array_key_exists('use_test_delivery', $data)) {
            return $data;
        }

        return [
            'use_test_delivery' => (bool) ($data['email_use_test_delivery'] ?? false),
            'test_email' => $data['email_test_email'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizeWhatsAppFormData(array $data): array
    {
        if (array_key_exists('use_test_delivery', $data)) {
            return $data;
        }

        return [
            'use_test_delivery' => (bool) ($data['whatsapp_use_test_delivery'] ?? false),
            'test_phone' => $data['whatsapp_test_phone'] ?? null,
        ];
    }

    private static function testModeToggle(string $fieldPrefix = ''): Checkbox
    {
        return Checkbox::make($fieldPrefix.'use_test_delivery')
            ->label('Usar interfaz de prueba')
            ->helperText('Active esta opción para validar el servicio con un correo o teléfono de prueba sin afectar destinatarios reales.')
            ->live();
    }

    private static function testEmailField(string $fieldPrefix = ''): TextInput
    {
        return TextInput::make($fieldPrefix.'test_email')
            ->label('Correo de prueba')
            ->email()
            ->required()
            ->maxLength(255)
            ->placeholder('analista@ejemplo.com');
    }

    private static function testPhoneField(string $fieldPrefix = ''): TextInput
    {
        return TextInput::make($fieldPrefix.'test_phone')
            ->label('Teléfono de prueba')
            ->tel()
            ->required()
            ->maxLength(30)
            ->placeholder('04143027250')
            ->helperText('Formato recomendado: 04141234567');
    }

    /**
     * @param  array<string, mixed>  $recipients
     */
    private static function productionPreviewPlaceholder(
        string $view,
        Sale $sale,
        array $recipients,
        string $fieldPrefix = '',
    ): Placeholder {
        return Placeholder::make($fieldPrefix.'production_recipients_preview')
            ->hiddenLabel()
            ->content(fn (): HtmlString => new HtmlString(
                View::make($view, [
                    'sale' => $sale,
                    'recipients' => $recipients,
                ])->render()
            ));
    }
}
