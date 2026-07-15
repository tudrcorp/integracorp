<?php

declare(strict_types=1);

namespace App\Filament\Business\Pages\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

final class SystemNotificationRecipientSettingsForm
{
    private const SHELL_CLASS = 'can-settings-shell rounded-[1.75rem] border border-slate-200/85 bg-gradient-to-br from-white via-slate-50/90 to-white p-3 shadow-[0_24px_60px_-26px_rgba(15,23,42,0.2)] ring-1 ring-slate-200/55 dark:border-white/10 dark:from-slate-900/95 dark:via-slate-950/95 dark:to-slate-900/95 dark:ring-white/10 dark:shadow-[0_24px_60px_-24px_rgba(0,0,0,0.55)]';

    private const PANEL_CLASS = 'can-settings-panel rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    private const INNER_CLASS = 'can-settings-inner rounded-[1.25rem] border border-slate-200/80 bg-white/80 p-4 shadow-inner dark:border-white/10 dark:bg-white/5 sm:p-5';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(1)
                    ->extraAttributes(['class' => self::SHELL_CLASS])
                    ->schema([
                        self::activationSection(),
                        Grid::make()
                            ->columns(['default' => 1, 'xl' => 2])
                            ->schema([
                                self::emailSection(),
                                self::phoneSection(),
                            ]),
                    ]),
            ]);
    }

    private static function activationSection(): Section
    {
        return Section::make('Estado de la tarea')
            ->description('Active o pause esta notificación / tarea programada sin eliminar los destinatarios configurados.')
            ->icon(Heroicon::OutlinedPower)
            ->extraAttributes(['class' => self::PANEL_CLASS.' can-settings-panel--status'])
            ->schema([
                Grid::make(1)
                    ->extraAttributes(['class' => self::INNER_CLASS])
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Tarea activa')
                            ->helperText('Desactive para pausar la ejecución o el envío asociado a este tipo de notificación.')
                            ->inline(false)
                            ->onColor('success')
                            ->offColor('danger')
                            ->live(),
                    ]),
            ]);
    }

    private static function emailSection(): Section
    {
        return Section::make('Correos electrónicos')
            ->description('Destinatarios por email para esta notificación o su copia de control interno.')
            ->icon(Heroicon::OutlinedEnvelope)
            ->extraAttributes(['class' => self::PANEL_CLASS.' can-settings-panel--email'])
            ->schema([
                Grid::make(1)
                    ->extraAttributes(['class' => self::INNER_CLASS])
                    ->schema([
                        Repeater::make('notification_emails')
                            ->label('Destinatarios por correo')
                            ->hiddenLabel()
                            ->addActionLabel('Agregar correo')
                            ->defaultItems(0)
                            ->reorderable(false)
                            ->collapsible()
                            ->cloneable()
                            ->live()
                            ->itemLabel(fn (array $state): string => filled($state['email'] ?? null)
                                ? (string) $state['email']
                                : 'Nuevo correo')
                            ->schema([
                                TextInput::make('email')
                                    ->label('Correo electrónico')
                                    ->email()
                                    ->required()
                                    ->maxLength(255)
                                    ->prefixIcon(Heroicon::OutlinedAtSymbol)
                                    ->placeholder('analista@empresa.com')
                                    ->autocomplete('off'),
                            ])
                            ->helperText('Puede agregar tantos correos como necesite. Se eliminan duplicados al guardar.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    private static function phoneSection(): Section
    {
        return Section::make('Teléfonos WhatsApp')
            ->description('Números móviles para esta notificación o su copia de control interno.')
            ->icon(Heroicon::OutlinedDevicePhoneMobile)
            ->extraAttributes(['class' => self::PANEL_CLASS.' can-settings-panel--phone'])
            ->schema([
                Grid::make(1)
                    ->extraAttributes(['class' => self::INNER_CLASS])
                    ->schema([
                        Repeater::make('notification_phones')
                            ->label('Destinatarios por WhatsApp')
                            ->hiddenLabel()
                            ->addActionLabel('Agregar teléfono')
                            ->defaultItems(0)
                            ->reorderable(false)
                            ->collapsible()
                            ->cloneable()
                            ->live()
                            ->itemLabel(fn (array $state): string => filled($state['phone'] ?? null)
                                ? (string) $state['phone']
                                : 'Nuevo teléfono')
                            ->schema([
                                TextInput::make('phone')
                                    ->label('Teléfono móvil')
                                    ->tel()
                                    ->required()
                                    ->maxLength(30)
                                    ->prefixIcon(Heroicon::OutlinedPhone)
                                    ->placeholder('04141234567')
                                    ->helperText('Formato nacional sin espacios. Ej: 04127015390')
                                    ->autocomplete('off'),
                            ])
                            ->helperText('El mensaje se envía de forma asíncrona con la imagen corporativa de INTEGRACORP.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
