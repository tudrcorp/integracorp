<?php

declare(strict_types=1);

namespace App\Filament\Business\Pages\Schemas;

use App\Enums\TelegramDeliveryMode;
use App\Enums\TelegramRecipientStatus;
use App\Models\SystemNotificationRecipientSetting;
use App\Models\TelegramRecipient;
use App\Support\InternalNotifications\InternalContactResolver;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

final class SystemNotificationRecipientSettingsForm
{
    private const PANEL_CLASS = 'can-panel';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                self::activationSection(),
                Grid::make()
                    ->columns(['default' => 1, '2xl' => 2])
                    ->extraAttributes(['class' => 'can-recipients-grid'])
                    ->columnSpanFull()
                    ->visible(fn (mixed $livewire): bool => self::activeKeyUsesEmails($livewire) || self::activeKeyUsesPhones($livewire))
                    ->schema([
                        self::emailSection(),
                        self::phoneSection(),
                    ]),
                self::telegramSection(),
            ]);
    }

    /**
     * Estado arriba y a la vista: es lo primero que se busca al abrir un aviso.
     * Sin ->live(): la cabecera lee el interruptor en el navegador y se guarda con el resto.
     */
    private static function activationSection(): Section
    {
        return Section::make('Estado de la tarea')
            ->description('Pause el envío sin perder los destinatarios. Se aplica al guardar.')
            ->icon(Heroicon::OutlinedPower)
            ->compact()
            ->columnSpanFull()
            ->extraAttributes(['class' => self::PANEL_CLASS.' can-settings-panel--status'])
            ->schema([
                Toggle::make('is_active')
                    ->label('Tarea activa')
                    ->helperText('Apagado: no se ejecuta ni se envía nada de este aviso hasta que lo vuelva a encender.')
                    ->onColor('success')
                    ->offColor('danger'),
            ]);
    }

    private static function emailSection(): Section
    {
        return Section::make('Correos electrónicos')
            ->description('Destinatarios por email para esta notificación o su copia de control interno.')
            ->icon(Heroicon::OutlinedEnvelope)
            ->compact()
            ->extraAttributes(['class' => self::PANEL_CLASS.' can-settings-panel--email'])
            ->visible(fn (mixed $livewire): bool => self::activeKeyUsesEmails($livewire))
            ->schema([
                Repeater::make('notification_emails')
                    ->label('Destinatarios por correo')
                    ->hiddenLabel()
                    ->addActionLabel('Agregar correo')
                    ->defaultItems(0)
                    ->reorderable(false)
                    ->compact()
                    ->table(fn (mixed $livewire): array => self::activeKeyUsesCopyModes($livewire)
                        ? [
                            TableColumn::make('Correo electrónico')->markAsRequired(),
                            TableColumn::make('Tipo de copia')->width('11rem'),
                        ]
                        : [TableColumn::make('Correo electrónico')->markAsRequired()])
                    ->schema([
                        TextInput::make('email')
                            ->label('Correo electrónico')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->prefixIcon(Heroicon::OutlinedAtSymbol)
                            ->placeholder('analista@empresa.com')
                            ->autocomplete('off'),
                        Select::make('copy_mode')
                            ->label('Tipo de copia')
                            ->options([
                                SystemNotificationRecipientSetting::EMAIL_MODE_CC => 'CC · copia visible',
                                SystemNotificationRecipientSetting::EMAIL_MODE_BCC => 'CCO · copia oculta',
                            ])
                            ->default(SystemNotificationRecipientSetting::EMAIL_MODE_CC)
                            ->required(fn (mixed $livewire): bool => self::activeKeyUsesCopyModes($livewire))
                            ->selectablePlaceholder(false)
                            ->native()
                            ->visible(fn (mixed $livewire): bool => self::activeKeyUsesCopyModes($livewire)),
                    ])
                    ->helperText(fn (mixed $livewire): string => self::activeKeyUsesCopyModes($livewire)
                        ? 'Cada correo testigo recibe el mismo correo que el afiliado o el agente. CC: el destinatario ve este correo · CCO: nadie más lo ve. Los duplicados se eliminan al guardar.'
                        : 'Agregue tantos correos como necesite. Los duplicados se eliminan al guardar.')
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Las notificaciones solo por WhatsApp no muestran la sección de correos.
     */
    private static function activeKeyUsesEmails(mixed $livewire): bool
    {
        if (! is_object($livewire) || ! method_exists($livewire, 'getActiveNotificationKeyEnum')) {
            return true;
        }

        return $livewire->getActiveNotificationKeyEnum()->usesEmailRecipients();
    }

    /**
     * Las copias testigo de un envío ajeno (recibos, avisos de cobro) marcan cada correo como CC o CCO.
     */
    private static function activeKeyUsesCopyModes(mixed $livewire): bool
    {
        if (! is_object($livewire) || ! method_exists($livewire, 'getActiveNotificationKeyEnum')) {
            return false;
        }

        return $livewire->getActiveNotificationKeyEnum()->supportsEmailCopyModes();
    }

    private static function phoneSection(): Section
    {
        return Section::make('Teléfonos WhatsApp')
            ->description('Números móviles para esta notificación o su copia de control interno.')
            ->icon(Heroicon::OutlinedDevicePhoneMobile)
            ->compact()
            ->extraAttributes(['class' => self::PANEL_CLASS.' can-settings-panel--phone'])
            ->visible(fn (mixed $livewire): bool => self::activeKeyUsesPhones($livewire))
            ->schema([
                Repeater::make('notification_phones')
                    ->label('Destinatarios por WhatsApp')
                    ->hiddenLabel()
                    ->addActionLabel('Agregar teléfono')
                    ->defaultItems(0)
                    ->reorderable(false)
                    ->compact()
                    ->table([
                        TableColumn::make('Teléfono móvil')->markAsRequired(),
                    ])
                    ->schema([
                        TextInput::make('phone')
                            ->label('Teléfono móvil')
                            ->tel()
                            ->required()
                            ->maxLength(30)
                            ->prefixIcon(Heroicon::OutlinedPhone)
                            ->placeholder('04141234567')
                            ->autocomplete('off'),
                    ])
                    ->helperText('Formato nacional sin espacios (ej.: 04127015390). El mensaje sale en segundo plano con la imagen corporativa de INTEGRACORP.')
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Destino por Telegram: solo aparece en los avisos que ya se reparten con el
     * nuevo sistema (SystemNotificationKey::supportsTelegram()).
     */
    private static function telegramSection(): Section
    {
        return Section::make('Telegram')
            ->description('Telegram reemplaza al WhatsApp: los teléfonos de arriba con Telegram vinculado lo reciben allí y el resto sigue por WhatsApp. Los correos y los adjuntos (respaldos, Excel) no cambian.')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->compact()
            ->extraAttributes(['class' => self::PANEL_CLASS.' can-settings-panel--telegram'])
            ->visible(fn (mixed $livewire): bool => self::activeKeySupportsTelegram($livewire))
            ->columnSpanFull()
            ->schema([
                Grid::make(['default' => 1, 'lg' => 2])
                    ->extraAttributes(['class' => 'can-telegram-grid'])
                    ->schema([
                        Select::make('telegram_mode')
                            ->label('Enviar por Telegram a')
                            ->options(fn (mixed $livewire): array => self::modeOptions($livewire))
                            ->default(TelegramDeliveryMode::Off->value)
                            ->selectablePlaceholder(false)
                            ->native(false)
                            ->required()
                            ->live()
                            ->helperText(fn (mixed $livewire): string => is_object($livewire) && method_exists($livewire, 'getActiveNotificationKeyEnum')
                                ? $livewire->getActiveNotificationKeyEnum()->telegramModeHelp()
                                : ''),
                        Select::make('telegram_group_ids')
                            ->label('Grupos de Telegram')
                            ->multiple()
                            ->options(fn (): array => self::groupOptions())
                            ->searchable()
                            ->native(false)
                            ->visible(fn (Get $get, mixed $livewire): bool => self::activeKeyAllowsGroups($livewire)
                                && (TelegramDeliveryMode::tryFrom((string) $get('telegram_mode')) ?? TelegramDeliveryMode::Off)->includesGroups())
                            ->required(fn (Get $get): bool => (TelegramDeliveryMode::tryFrom((string) $get('telegram_mode')) ?? TelegramDeliveryMode::Off)->includesGroups())
                            ->validationMessages(['required' => 'Elija al menos un grupo aprobado, o cambie el destino a «Persona».'])
                            ->helperText('Solo aparecen los grupos aprobados en CONFIGURACIÓN → Telegram. Para sumar uno, agregue el bot al grupo en Telegram y apruébelo allí.'),
                        Select::make('telegram_recipient_ids')
                            ->label('Personas de Telegram')
                            ->multiple()
                            ->options(fn (): array => self::peopleOptions())
                            ->searchable()
                            ->native(false)
                            ->live()
                            ->placeholder('Elija personas vinculadas…')
                            ->visible(fn (Get $get, mixed $livewire): bool => ! self::activeKeyIsPersonTargeted($livewire)
                                && (TelegramDeliveryMode::tryFrom((string) $get('telegram_mode')) ?? TelegramDeliveryMode::Off)->includesPeople())
                            ->helperText('Además de los contactos de arriba que ya estén vinculados, el aviso les llega por Telegram a estas personas. Solo aparecen las que ya conectaron su Telegram.'),
                        Placeholder::make('telegram_audience_preview')
                            ->label('Lo recibirán por Telegram')
                            ->columnSpanFull()
                            ->visible(fn (Get $get, mixed $livewire): bool => ! self::activeKeyIsPersonTargeted($livewire)
                                && (TelegramDeliveryMode::tryFrom((string) $get('telegram_mode')) ?? TelegramDeliveryMode::Off) !== TelegramDeliveryMode::Off)
                            ->content(fn (Get $get): HtmlString => self::audiencePreview($get)),
                        Placeholder::make('telegram_status_hint')
                            ->hiddenLabel()
                            ->columnSpanFull()
                            ->content(fn (): HtmlString => self::telegramStatusHint()),
                    ]),
            ]);
    }

    /**
     * @return array<string, string>
     */
    private static function modeOptions(mixed $livewire): array
    {
        $options = [];

        foreach (TelegramDeliveryMode::cases() as $mode) {
            if ($mode->includesGroups() && ! self::activeKeyAllowsGroups($livewire)) {
                continue;
            }

            $options[$mode->value] = $mode === TelegramDeliveryMode::Person && is_object($livewire) && method_exists($livewire, 'getActiveNotificationKeyEnum')
                ? $livewire->getActiveNotificationKeyEnum()->telegramPersonLabel()
                : $mode->label();
        }

        return $options;
    }

    /**
     * @return array<int, string>
     */
    private static function groupOptions(): array
    {
        return TelegramRecipient::query()
            ->groups()
            ->where('status', TelegramRecipientStatus::Active->value)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * Personas vinculadas que hoy pueden recibir, para elegirlas a mano.
     *
     * @return array<int, string>
     */
    private static function peopleOptions(): array
    {
        return InternalContactResolver::linkedPeople()
            ->sortBy(fn (TelegramRecipient $recipient): string => mb_strtolower($recipient->name))
            ->mapWithKeys(fn (TelegramRecipient $recipient): array => [
                (int) $recipient->getKey() => $recipient->name.' · '.$recipient->type->label().(filled($recipient->telegram_username) ? ' (@'.$recipient->telegram_username.')' : ''),
            ])
            ->all();
    }

    /**
     * Quién recibirá el aviso por Telegram con lo que está en pantalla (aunque no se haya guardado):
     * contactos de arriba ya vinculados, personas elegidas y grupos.
     */
    private static function audiencePreview(Get $get): HtmlString
    {
        $mode = TelegramDeliveryMode::tryFrom((string) $get('telegram_mode')) ?? TelegramDeliveryMode::Off;
        $people = InternalContactResolver::linkedPeople();
        $names = [];

        if ($mode->includesPeople()) {
            $emails = collect((array) $get('notification_emails'))->pluck('email')->filter()->map(fn (mixed $email): string => mb_strtolower(trim((string) $email)))->all();
            $phoneKeys = collect((array) $get('notification_phones'))->pluck('phone')->filter()->map(fn (mixed $phone): string => InternalContactResolver::phoneKey((string) $phone))->filter()->all();
            $chosen = array_map('intval', (array) $get('telegram_recipient_ids'));

            foreach ($people as $recipient) {
                $recipientEmails = array_map(fn (mixed $email): string => mb_strtolower(trim((string) $email)), array_filter([$recipient->email, $recipient->user?->email]));
                $recipientPhones = array_map(fn (mixed $phone): string => InternalContactResolver::phoneKey((string) $phone), array_filter([$recipient->phone, $recipient->user?->phone]));

                if (in_array((int) $recipient->getKey(), $chosen, true)
                    || array_intersect($recipientEmails, $emails) !== []
                    || array_intersect($recipientPhones, $phoneKeys) !== []) {
                    $names[] = e($recipient->name);
                }
            }
        }

        if ($mode->includesGroups()) {
            $groupIds = array_map('intval', (array) $get('telegram_group_ids'));
            $groups = TelegramRecipient::query()->groups()->whereKey($groupIds)->where('status', TelegramRecipientStatus::Active->value)->orderBy('name')->pluck('name')->all();

            foreach ($groups as $group) {
                $names[] = 'Grupo «'.e((string) $group).'»';
            }
        }

        if ($names === []) {
            return new HtmlString('<span class="text-sm text-amber-600 dark:text-amber-400">Nadie todavía: ningún contacto de arriba tiene Telegram vinculado y no hay personas ni grupos elegidos. El aviso sale por WhatsApp como siempre.</span>');
        }

        return new HtmlString('<span class="text-sm text-slate-700 dark:text-slate-200">'.implode(' · ', array_unique($names)).'</span>'
            .'<span class="mt-1 block text-xs text-slate-500 dark:text-slate-400">Vista previa con lo que está en pantalla. Guarde para aplicarlo.</span>');
    }

    private static function activeKeyIsPersonTargeted(mixed $livewire): bool
    {
        return is_object($livewire) && method_exists($livewire, 'getActiveNotificationKeyEnum')
            && $livewire->getActiveNotificationKeyEnum()->isPersonTargeted();
    }

    private static function telegramStatusHint(): HtmlString
    {
        $configured = (bool) config('telegram.enabled', true) && filled(config('telegram.bot_token'));

        if (! $configured) {
            return new HtmlString('<span class="text-sm text-amber-600 dark:text-amber-400">Telegram está apagado o sin token en este servidor: mientras tanto el aviso sale por WhatsApp y correo aunque elija Telegram.</span>');
        }

        return new HtmlString('<span class="text-sm text-slate-500 dark:text-slate-400">Si Telegram falla, el aviso no se pierde: sale por WhatsApp (o por correo si no hay teléfono). Cada envío queda en la bitácora de CONFIGURACIÓN → Telegram.</span>');
    }

    private static function activeKeyUsesPhones(mixed $livewire): bool
    {
        if (! is_object($livewire) || ! method_exists($livewire, 'getActiveNotificationKeyEnum')) {
            return true;
        }

        return $livewire->getActiveNotificationKeyEnum()->usesPhoneRecipients();
    }

    private static function activeKeySupportsTelegram(mixed $livewire): bool
    {
        if (! is_object($livewire) || ! method_exists($livewire, 'getActiveNotificationKeyEnum')) {
            return false;
        }

        return $livewire->getActiveNotificationKeyEnum()->supportsTelegram();
    }

    private static function activeKeyAllowsGroups(mixed $livewire): bool
    {
        if (! is_object($livewire) || ! method_exists($livewire, 'getActiveNotificationKeyEnum')) {
            return true;
        }

        return $livewire->getActiveNotificationKeyEnum()->allowsTelegramGroups();
    }
}
