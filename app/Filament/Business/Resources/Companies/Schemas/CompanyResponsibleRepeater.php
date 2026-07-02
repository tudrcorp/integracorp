<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\Companies\Schemas;

use App\Models\State;
use App\Models\Zone;
use App\Support\Companies\CompanyResponsibleDays;
use Closure;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Livewire\Component;

class CompanyResponsibleRepeater
{
    /**
     * @param  Closure(Component, Get): (int|null)  $populationResolver
     */
    public static function make(string $name, Closure $populationResolver): Repeater
    {
        $repeater = Repeater::make($name)
            ->label('Responsables')
            ->hiddenLabel()
            ->addActionLabel('Agregar responsable')
            ->collapsible()
            ->cloneable()
            ->live()
            ->defaultItems(1)
            ->itemLabel(fn (array $state): string => filled($state['full_name'] ?? null)
                ? (string) $state['full_name']
                : 'Nuevo responsable')
            ->columns(['default' => 1, 'sm' => 2, 'xl' => 4])
            ->schema(self::itemSchema())
            ->helperText(function (Get $get, Component $livewire) use ($name, $populationResolver): string {
                return CompanyResponsibleDays::helperText(
                    (array) ($get($name) ?? []),
                    $populationResolver($livewire, $get),
                );
            })
            ->rules([
                fn (Get $get, Component $livewire): Closure => function (string $attribute, mixed $value, Closure $fail) use ($populationResolver, $livewire, $get): void {
                    $message = CompanyResponsibleDays::validationMessage(
                        is_array($value) ? $value : [],
                        $populationResolver($livewire, $get),
                    );

                    if ($message !== null) {
                        $fail($message);
                    }
                },
            ])
            ->columnSpanFull();

        return $repeater;
    }

    /**
     * @return array<int, mixed>
     */
    public static function itemSchema(): array
    {
        return [
            TextInput::make('full_name')
                ->label('Nombre y Apellido')
                ->required()
                ->maxLength(255)
                ->prefixIcon(Heroicon::OutlinedUser),
            TextInput::make('identity_card')
                ->label('Cédula de Identidad')
                ->required()
                ->maxLength(20)
                ->prefixIcon(Heroicon::OutlinedIdentification)
                ->placeholder('Ej: V-12345678'),
            TextInput::make('phone')
                ->label('Teléfono')
                ->tel()
                ->maxLength(30)
                ->prefixIcon(Heroicon::OutlinedPhone),
            TextInput::make('email')
                ->label('Correo electrónico')
                ->email()
                ->maxLength(255)
                ->prefixIcon(Heroicon::OutlinedEnvelope),
            TextInput::make('company')
                ->label('Compañía')
                ->maxLength(255)
                ->prefixIcon(Heroicon::OutlinedBuildingOffice2),
            Select::make('state_id')
                ->label('Estado')
                ->options(fn (): array => State::query()->orderBy('definition', 'asc')->pluck('definition', 'id')->all())
                ->searchable()
                ->preload(),
            Select::make('zone_id')
                ->label('Zona')
                ->options(fn (): array => Zone::query()->orderBy('zone', 'asc')->pluck('zone', 'id')->all())
                ->searchable()
                ->preload(),
            TextInput::make('contracted_days')
                ->label('Nro. de Días Contratados')
                ->numeric()
                ->minValue(0)
                ->default(0)
                ->required()
                ->live(onBlur: true)
                ->prefixIcon(Heroicon::OutlinedCalendarDays),
        ];
    }
}
