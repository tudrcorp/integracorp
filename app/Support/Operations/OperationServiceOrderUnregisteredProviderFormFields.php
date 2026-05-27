<?php

declare(strict_types=1);

namespace App\Support\Operations;

use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

final class OperationServiceOrderUnregisteredProviderFormFields
{
    public const WIZARD_STEP_CLASS = 'fi-unregistered-provider-wizard-step';

    /**
     * @return array<int, mixed>
     */
    public static function wizardStepSchema(): array
    {
        return [
            Placeholder::make('unregistered_wizard_step_hint')
                ->hiddenLabel()
                ->content(new HtmlString(
                    '<p class="text-sm text-gray-600 dark:text-gray-300">'
                    .'Complete los datos del proveedor. Solo nombre/razón social y C.I./R.I.F. son obligatorios.'
                    .'</p>'
                ))
                ->columnSpanFull(),

            Section::make('Tipo de proveedor')
                ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                ->schema([
                    ToggleButtons::make('unregistered_provider_type')
                        ->label('¿Proveedor jurídico o natural?')
                        ->options([
                            'juridico' => 'Jurídico',
                            'natural' => 'Natural',
                        ])
                        ->icons([
                            'juridico' => Heroicon::OutlinedBuildingOffice2,
                            'natural' => Heroicon::OutlinedUser,
                        ])
                        ->inline()
                        ->required()
                        ->live()
                        ->columnSpanFull(),
                ])
                ->columnSpanFull(),

            Section::make('Datos del proveedor')
                ->icon(Heroicon::OutlinedIdentification)
                ->extraAttributes(['class' => self::WIZARD_STEP_CLASS])
                ->visible(fn (Get $get): bool => filled($get('unregistered_provider_type')))
                ->schema([
                    Grid::make(1)
                        ->schema(self::registrationFields())
                        ->columnSpanFull(),
                ])
                ->columnSpanFull(),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    public static function registerToggle(): Toggle
    {
        return Toggle::make('register_unregistered_provider')
            ->label('Proveedor No Convenido')
            ->helperText('Si activa esta opción, el asistente abrirá un paso adicional para registrar el proveedor.')
            ->live()
            ->columnSpanFull()
            ->afterStateUpdated(function (mixed $state, Set $set): void {
                if (! $state) {
                    self::clearUnregisteredFields($set);

                    return;
                }

                $set('doctor_nurse_id', null);
                $set('supplier_id', null);
            });
    }

    /**
     * @return array<int, mixed>
     */
    public static function inlineRegistrationSchema(): array
    {
        return [
            Section::make('Registro de proveedor no convenido')
                ->icon(Heroicon::OutlinedUserPlus)
                ->iconColor('warning')
                ->visible(fn (Get $get): bool => (bool) $get('register_unregistered_provider'))
                ->schema(self::wizardStepSchema())
                ->columnSpanFull(),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    public static function registrationFields(): array
    {
        return [
            TextInput::make('unregistered_name')
                ->label('Nombre / Razón social')
                ->required()
                ->maxLength(255)
                ->placeholder('Nombre comercial o razón social')
                ->prefixIcon(Heroicon::OutlinedBuildingStorefront)
                ->columnSpanFull(),

            TextInput::make('unregistered_rif')
                ->label('C.I. / R.I.F.')
                ->required()
                ->maxLength(30)
                ->placeholder('V-12345678 o J-12345678-9')
                ->prefixIcon(Heroicon::OutlinedIdentification)
                ->columnSpanFull(),

            TextInput::make('unregistered_phone')
                ->label('Número de teléfono')
                ->tel()
                ->maxLength(30)
                ->placeholder('04141234567')
                ->prefixIcon(Heroicon::OutlinedPhone)
                ->columnSpanFull(),

            TextInput::make('unregistered_correo_principal')
                ->label('Correo electrónico')
                ->email()
                ->maxLength(255)
                ->placeholder('correo@dominio.com')
                ->prefixIcon(Heroicon::OutlinedEnvelope)
                ->columnSpanFull(),

            TextInput::make('unregistered_ubicacion_principal')
                ->label('Dirección')
                ->maxLength(255)
                ->placeholder('Dirección física')
                ->prefixIcon(Heroicon::OutlinedMapPin)
                ->columnSpanFull(),
        ];
    }

    public static function clearUnregisteredFields(Set $set): void
    {
        foreach ([
            'unregistered_provider_type',
            'unregistered_name',
            'unregistered_rif',
            'unregistered_phone',
            'unregistered_correo_principal',
            'unregistered_ubicacion_principal',
        ] as $field) {
            $set($field, null);
        }
    }
}
