<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\Upgrades\Schemas;

use App\Models\Upgrade;
use App\Support\Upgrades\UpgradeStructureValidator;
use Closure;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class UpgradeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos del upgrade')
                    ->description('El nombre es el que verán los analistas al asignarlo y el que sale en los avisos de cobro.')
                    ->icon(Heroicon::Sparkles)
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre')
                            ->placeholder('Ej.: ODONTOLOGÍA AMPLIADA')
                            ->helperText('Se guarda en mayúsculas.')
                            ->required()
                            ->maxLength(UpgradeStructureValidator::MAX_NAME_LENGTH)
                            ->validationMessages(['required' => 'Indique el nombre del upgrade.'])
                            ->columnSpan(2),
                        ToggleButtons::make('status')
                            ->label('Estatus')
                            ->options([
                                Upgrade::STATUS_ACTIVE => 'Activo',
                                Upgrade::STATUS_INACTIVE => 'Inactivo',
                            ])
                            ->colors([
                                Upgrade::STATUS_ACTIVE => 'success',
                                Upgrade::STATUS_INACTIVE => 'danger',
                            ])
                            ->icons([
                                Upgrade::STATUS_ACTIVE => Heroicon::CheckCircle,
                                Upgrade::STATUS_INACTIVE => Heroicon::NoSymbol,
                            ])
                            ->default(Upgrade::STATUS_ACTIVE)
                            ->inline()
                            ->required()
                            ->helperText('Inactivo: no se puede asignar a nadie más. Quien ya lo tiene lo conserva.'),
                        Textarea::make('description')
                            ->label('Descripción')
                            ->placeholder('Qué agrega este upgrade al plan del afiliado.')
                            ->rows(2)
                            ->maxLength(2000)
                            ->columnSpanFull(),
                        FileUpload::make('image_path')
                            ->label('Imagen de la tarjeta')
                            ->helperText('Se ve en la tarjeta del upgrade al cotizar. Vertical (3:4), JPG, PNG o WebP de hasta 2 MB. Sin imagen, la tarjeta usa un fondo de color.')
                            ->image()
                            ->disk('public')
                            ->directory(Upgrade::IMAGE_DIRECTORY)
                            ->visibility('public')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize(2048)
                            ->imageCropAspectRatio('3:4')
                            ->imagePreviewHeight('180')
                            ->columnSpanFull(),
                    ]),

                Section::make('Servicios incluidos')
                    ->description('Lo que incluye el upgrade. Es informativo: no cambia el precio.')
                    ->icon(Heroicon::ListBullet)
                    ->collapsible()
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('services')
                            ->hiddenLabel()
                            ->addActionLabel('Agregar servicio')
                            ->defaultItems(0)
                            ->maxItems(UpgradeStructureValidator::MAX_SERVICES)
                            ->reorderable()
                            ->collapsible()
                            ->itemLabel(fn (array $state): string => filled($state['name'] ?? null) ? (string) $state['name'] : 'Nuevo servicio')
                            ->schema([
                                Hidden::make('id'),
                                TextInput::make('name')
                                    ->label('Servicio')
                                    ->placeholder('Ej.: Limpieza dental semestral')
                                    ->required()
                                    ->distinct()
                                    ->maxLength(UpgradeStructureValidator::MAX_SERVICE_NAME_LENGTH)
                                    ->live(onBlur: true)
                                    ->validationMessages([
                                        'required' => 'Indique el nombre del servicio.',
                                        'distinct' => 'Este servicio está repetido.',
                                    ]),
                                Textarea::make('description')
                                    ->label('Detalle')
                                    ->placeholder('Opcional: condiciones, frecuencia, red donde se presta…')
                                    ->rows(2)
                                    ->maxLength(2000),
                            ])
                            ->columns(2)
                            ->columnSpanFull(),
                    ]),

                Section::make('Coberturas y precios por edad')
                    ->description('Cada cobertura tiene sus rangos de edad y el precio anual en US$ de cada rango. Al asignarlo, el afiliado paga el precio del rango en el que cae su edad. Los rangos de una misma cobertura no pueden solaparse.')
                    ->icon(Heroicon::CurrencyDollar)
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('coverages')
                            ->hiddenLabel()
                            ->addActionLabel('Agregar cobertura')
                            ->defaultItems(1)
                            ->minItems(1)
                            ->maxItems(UpgradeStructureValidator::MAX_COVERAGES)
                            ->reorderable()
                            ->collapsible()
                            ->validationMessages(['min' => 'Agregue al menos una cobertura.'])
                            ->itemLabel(fn (array $state): string => is_numeric($state['amount'] ?? null)
                                ? 'Cobertura '.number_format((float) $state['amount'], 2, ',', '.').' US$'
                                : 'Cobertura sin monto')
                            ->schema([
                                Hidden::make('id'),
                                TextInput::make('amount')
                                    ->label('Monto de la cobertura')
                                    ->helperText('Suma cubierta en US$. Déjelo vacío solo si el upgrade tiene una única cobertura sin monto.')
                                    ->prefix('US$')
                                    ->numeric()
                                    ->inputMode('decimal')
                                    ->minValue(0.01)
                                    ->maxValue(UpgradeStructureValidator::MAX_COVERAGE_AMOUNT)
                                    ->live(onBlur: true)
                                    ->validationMessages(['min' => 'El monto debe ser mayor a 0.']),
                                Repeater::make('ranges')
                                    ->label('Rangos de edad y precio anual')
                                    ->addActionLabel('Agregar rango')
                                    ->defaultItems(1)
                                    ->minItems(1)
                                    ->maxItems(UpgradeStructureValidator::MAX_RANGES_PER_COVERAGE)
                                    ->reorderable(false)
                                    ->table([
                                        TableColumn::make('Edad desde')->width('160px'),
                                        TableColumn::make('Edad hasta')->width('160px'),
                                        TableColumn::make('Precio anual'),
                                    ])
                                    ->validationMessages(['min' => 'Agregue al menos un rango de edad.'])
                                    ->rules([
                                        fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                            $ranges = array_map(fn (mixed $range): array => [
                                                'age_from' => is_numeric($range['age_from'] ?? null) ? (int) $range['age_from'] : null,
                                                'age_to' => is_numeric($range['age_to'] ?? null) ? (int) $range['age_to'] : null,
                                            ], array_values(is_array($value) ? $value : []));

                                            $message = UpgradeStructureValidator::overlapMessage($ranges);

                                            if ($message !== null) {
                                                $fail(mb_strtoupper(mb_substr($message, 0, 1)).mb_substr($message, 1));
                                            }
                                        },
                                    ])
                                    ->schema([
                                        Hidden::make('id'),
                                        TextInput::make('age_from')
                                            ->hiddenLabel()
                                            ->integer()
                                            ->minValue(UpgradeStructureValidator::MIN_AGE)
                                            ->maxValue(UpgradeStructureValidator::MAX_AGE)
                                            ->suffix('años')
                                            ->required()
                                            ->validationMessages(['required' => 'Indique la edad desde.']),
                                        TextInput::make('age_to')
                                            ->hiddenLabel()
                                            ->integer()
                                            ->minValue(UpgradeStructureValidator::MIN_AGE)
                                            ->maxValue(UpgradeStructureValidator::MAX_AGE)
                                            ->gte('age_from')
                                            ->suffix('años')
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'Indique la edad hasta.',
                                                'gte' => 'Debe ser mayor o igual a la edad desde.',
                                            ]),
                                        TextInput::make('price')
                                            ->hiddenLabel()
                                            ->prefix('US$')
                                            ->numeric()
                                            ->inputMode('decimal')
                                            ->minValue(0.01)
                                            ->maxValue(UpgradeStructureValidator::MAX_PRICE)
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'Indique el precio.',
                                                'min' => 'El precio debe ser mayor a 0.',
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ])
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
