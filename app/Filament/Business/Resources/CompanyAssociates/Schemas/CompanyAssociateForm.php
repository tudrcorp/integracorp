<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\CompanyAssociates\Schemas;

use App\Models\City;
use App\Models\CompanyAssociate;
use App\Models\State;
use App\Support\Companies\CompanyAssociateRegistrar;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\Rule;

class CompanyAssociateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos del asociado')
                    ->description('Al guardar, el carnet se regenera con estos datos y se reenvía al asociado por correo y WhatsApp.')
                    ->icon(Heroicon::OutlinedUser)
                    ->columnSpanFull()
                    ->columns(['default' => 1, 'lg' => 2])
                    ->schema([
                        TextInput::make('full_name')
                            ->label('Nombre y apellido')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(['default' => 1, 'lg' => 2]),
                        TextInput::make('identity_card')
                            ->label('Documento de identidad')
                            ->required()
                            ->maxLength(20)
                            ->dehydrateStateUsing(fn (?string $state): string => CompanyAssociateRegistrar::normalizeIdentityCard($state))
                            ->rule(fn (?CompanyAssociate $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                                if (CompanyAssociateRegistrar::hasIdentityCardRegisteredOnDate((string) $value, exceptAssociateId: $record?->getKey())) {
                                    $fail('Este documento ya fue registrado hoy en otro asociado.');
                                }
                            }),
                        DatePicker::make('birth_date')
                            ->label('Fecha de nacimiento')
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->maxDate(now()->subDay())
                            ->live(onBlur: true),
                        Select::make('sex')
                            ->label('Sexo')
                            ->required()
                            ->native(false)
                            ->options([
                                'MASCULINO' => 'MASCULINO',
                                'FEMENINO' => 'FEMENINO',
                            ]),
                        TextInput::make('email')
                            ->label('Correo')
                            ->email()
                            ->maxLength(255)
                            ->helperText('Si lo deja vacío, el carnet no se reenvía por correo.'),
                        TextInput::make('phone')
                            ->label('Teléfono')
                            ->required()
                            ->tel()
                            ->maxLength(20)
                            ->helperText('Con prefijo de país. Ejemplo: +584127018390')
                            ->dehydrateStateUsing(fn (?string $state): string => CompanyAssociateRegistrar::normalizeInternationalPhone($state))
                            ->regex('/^\+[1-9]\d{6,14}$/'),
                        DatePicker::make('flight_date')
                            ->label('Fecha de vuelo')
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->helperText('Define la vigencia que se imprime en el carnet.'),
                        TimePicker::make('flight_time')
                            ->label('Hora de vuelo')
                            ->required()
                            ->seconds(false)
                            ->native(false),
                        Select::make('state_id')
                            ->label('Estado')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->live()
                            ->options(fn (): array => State::query()->orderBy('definition')->pluck('definition', 'id')->all())
                            ->afterStateUpdated(fn (Set $set): mixed => $set('city_id', null)),
                        Select::make('city_id')
                            ->label('Ciudad')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->options(function (Get $get): array {
                                $stateId = $get('state_id');

                                if (blank($stateId)) {
                                    return [];
                                }

                                return City::query()
                                    ->where('state_id', $stateId)
                                    ->orderBy('definition')
                                    ->pluck('definition', 'id')
                                    ->all();
                            })
                            ->rule(fn (Get $get): \Illuminate\Validation\Rules\Exists => Rule::exists('cities', 'id')->where(
                                fn ($query) => $query->where('state_id', $get('state_id')),
                            )),
                        Textarea::make('observations')
                            ->label('Observaciones')
                            ->rows(3)
                            ->maxLength(2000)
                            ->columnSpanFull(),
                    ]),
                Section::make('Contacto de emergencia')
                    ->icon(Heroicon::OutlinedPhone)
                    ->columnSpanFull()
                    ->columns(['default' => 1, 'lg' => 3])
                    ->schema([
                        TextInput::make('contact_full_name')
                            ->label('Nombre y apellido')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('contact_phone')
                            ->label('Teléfono')
                            ->required()
                            ->tel()
                            ->maxLength(30),
                        TextInput::make('contact_email')
                            ->label('Correo')
                            ->required()
                            ->email()
                            ->maxLength(255),
                    ]),
            ]);
    }
}
