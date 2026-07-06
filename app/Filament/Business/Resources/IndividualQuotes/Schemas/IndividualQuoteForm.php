<?php

namespace App\Filament\Business\Resources\IndividualQuotes\Schemas;

use App\Http\Controllers\UtilsController;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\AgeRange;
use App\Models\IndividualQuote;
use App\Models\Plan;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class IndividualQuoteForm
{
    private const TABS_CONTAINER = 'rounded-[1.75rem] border border-slate-200/85 bg-gradient-to-br from-white via-slate-50/90 to-white p-2 shadow-[0_24px_60px_-26px_rgba(15,23,42,0.2)] ring-1 ring-slate-200/55 dark:border-white/10 dark:from-slate-900/95 dark:via-slate-950/95 dark:to-slate-900/95 dark:ring-white/10 dark:shadow-[0_24px_60px_-24px_rgba(0,0,0,0.55)]';

    /**
     * @return array<int, \Closure(): \Closure(string, mixed, \Closure): void>
     */
    private static function requireQuoteDetailsRule(): array
    {
        return [
            function (): \Closure {
                return function (string $attribute, mixed $value, \Closure $fail): void {
                    $selectedItems = collect($value ?? [])
                        ->filter(fn (array $item): bool => filled($item['age_range_id'] ?? null));

                    if ($selectedItems->isEmpty()) {
                        $fail('Debe seleccionar al menos un (1) rango de edad para crear la cotización.');

                        return;
                    }

                    $hasInvalidPersonCount = $selectedItems->contains(function (array $item): bool {
                        $totalPersons = $item['total_persons'] ?? null;

                        return ! is_numeric($totalPersons) || (int) $totalPersons < 1;
                    });

                    if ($hasInvalidPersonCount) {
                        $fail('La cantidad de personas debe ser como mínimo una (1) persona.');
                    }
                };
            },
        ];
    }

    /**
     * @return list<array{plan_id: int, age_range_id: null, total_persons: null}>
     */
    private static function emptyQuoteDetailRows(int $planId): array
    {
        $count = AgeRange::query()->where('plan_id', $planId)->count();

        if ($count === 0) {
            return [];
        }

        return array_map(
            fn (): array => [
                'plan_id' => $planId,
                'age_range_id' => null,
                'total_persons' => null,
            ],
            range(0, $count - 1),
        );
    }

    private static function syncQuoteDetailsForPlan(Set $set, mixed $plan): void
    {
        if ($plan === 'CM' || blank($plan)) {
            $set('details_quote', []);

            return;
        }

        $planId = (int) $plan;
        $set('details_quote', self::emptyQuoteDetailRows($planId));
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('individualQuoteFormTabs')
                    ->columnSpanFull()
                    ->persistTab()
                    ->extraAttributes([
                        'class' => self::TABS_CONTAINER,
                    ])
                    ->tabs([
                        Tab::make('SOLICITANTE')
                            ->icon(Heroicon::OutlinedUserCircle)
                            ->schema([
                                Section::make('data_client')
                                    ->heading('¡Bienvenido/a de nuevo! 👋 ')
                                    ->description('Estás a punto de comenzar a crear una nueva cotización, por favor ingresa la información del cliente para personalizarla. ¡Puede ver el avance del proceso en la barra de estatus!')
                                    ->schema([
                                        Grid::make(4)
                                            ->schema([
                                                TextInput::make('code')
                                                    ->label('Nro. de cotización')
                                                    ->prefixIcon('heroicon-m-clipboard-document-check')
                                                    ->default(function () {
                                                        if (IndividualQuote::max('id') == null) {
                                                            $parte_entera = 0;
                                                        } else {
                                                            $parte_entera = IndividualQuote::max('id');
                                                        }

                                                        return 'COT-IND-000'.$parte_entera + 1;
                                                    })
                                                    ->disabled()
                                                    ->dehydrated()
                                                    ->maxLength(255),

                                            ])->columnSpanFull(),
                                        Grid::make(4)
                                            ->schema([
                                                TextInput::make('full_name')
                                                    ->label('Nombre y Apellido')
                                                    ->prefixIcon('heroicon-m-user')
                                                    ->required()
                                                    ->validationMessages([
                                                        'required' => 'Campo requerido',
                                                    ])
                                                    ->afterStateUpdatedJs(<<<'JS'
                                                    $set('full_name', $state.toUpperCase());
                                                JS),

                                                Select::make('country_code')
                                                    ->label('Código de país')
                                                    ->options(fn () => UtilsController::getCountries())
                                                    ->searchable()
                                                    ->default('+58')
                                                    ->live(onBlur: true)
                                                    ->validationMessages([
                                                        'required' => 'Campo Requerido',
                                                    ])
                                                    ->hiddenOn('edit'),
                                                TextInput::make('phone')
                                                    ->prefixIcon('heroicon-s-phone')
                                                    ->tel()
                                                    ->label('Número de teléfono')
                                                    ->validationMessages([
                                                        'required' => 'Campo Requerido',
                                                    ])
                                                    ->live(onBlur: true)
                                                    ->afterStateUpdated(function ($state, callable $set, Get $get) {
                                                        $countryCode = $get('country_code');
                                                        if ($countryCode) {
                                                            $cleanNumber = ltrim(preg_replace('/[^0-9]/', '', $state), '0');
                                                            $set('phone', $countryCode.$cleanNumber);
                                                        }
                                                    }),
                                                TextInput::make('email')
                                                    ->label('Correo Electrónico')
                                                    ->email()
                                                    ->rule('regex:/^[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$/')
                                                    ->validationMessages([
                                                        'required' => 'Campo requerido',
                                                        'email' => 'El correo no es valido',
                                                        'regex' => 'El correo no debe contener mayúsculas, espacios, ñ, ni caracteres especiales no permitidos.',
                                                    ]),
                                            ])->columnSpanFull(),

                                        Fieldset::make('Asociar Agencia y/o Agente')
                                            ->schema([
                                                Select::make('code_agency')
                                                    ->label('Lista de Agencias')
                                                    ->options(Agency::all()->pluck('name_corporative', 'code'))
                                                    ->searchable()
                                                    ->live()
                                                    ->prefixIcon('heroicon-c-building-library')
                                                    ->preload(),
                                                Select::make('agent_id')
                                                    ->label('Agentes')
                                                    ->options(function (Get $get) {
                                                        if ($get('code_agency') == null) {
                                                            return Agent::where('owner_code', 'TDG-100')->pluck('name', 'id');
                                                        }

                                                        return Agent::where('owner_code', $get('code_agency'))->pluck('name', 'id');
                                                    })
                                                    ->searchable()
                                                    ->prefixIcon('fontisto-person')
                                                    ->preload(),
                                            ])->columnSpanFull(),

                                        Hidden::make('created_by')->default(Auth::user()->name),
                                        Hidden::make('status')->default('PRE-APROBADA'),
                                    ])
                                    ->columns(3)
                                    ->columnSpanFull(),

                            ]),
                        Tab::make('PLANES A COTIZAR')
                            ->icon(Heroicon::OutlinedClipboardDocumentList)
                            ->schema([
                                Section::make('plans')
                                    ->heading('¡Sección de planes a cotizar! 🎯')
                                    // ->description('¡Perfecto! selecciona la que mejor se ajuste a las necesidades del cliente y continúa con el proceso.')
                                    ->schema([
                                        Radio::make('plan')
                                            ->columns(3)
                                            ->label('Selecciona el/los planes que desea cotizar:')
                                            ->required()
                                            ->live()
                                            ->afterStateUpdated(function (Set $set, mixed $state): void {
                                                self::syncQuoteDetailsForPlan($set, $state);
                                            })
                                            ->options(function () {
                                                $planesConBeneficios = Plan::query()
                                                    ->where('type', 'BASICO')
                                                    ->where('status', 'ACTIVO')
                                                    ->get()
                                                    ->pluck('description', 'id');

                                                $planesConBeneficios->put('CM', 'COTIZACIÓN MULTIPLE');

                                                return $planesConBeneficios;
                                            })
                                            ->descriptions(function (): array {
                                                $descriptions = Plan::query()
                                                    ->where('type', 'BASICO')
                                                    ->where('status', 'ACTIVO')
                                                    ->withCount('ageRanges')
                                                    ->get()
                                                    ->mapWithKeys(fn (Plan $plan): array => [
                                                        $plan->id => $plan->age_ranges_count.' rango(s) de edad disponible(s).',
                                                    ])
                                                    ->all();

                                                $descriptions['CM'] = 'Seleccione más de dos (2) planes.';

                                                return $descriptions;
                                            }),
                                    ]),
                            ]),
                        Tab::make('RANGO DE EDAD')
                            ->icon(Heroicon::OutlinedUsers)
                            ->schema([
                                Section::make('age_range')
                                    ->heading('¡Listo para el último paso! 🏁')
                                    ->schema([

                                        Repeater::make('details_quote')
                                            ->grid(2)
                                            ->label('Indique edad y número de afiliados al plan:')
                                            ->defaultItems(0)
                                            ->addable(false)
                                            ->deletable(false)
                                            ->reorderable(false)
                                            ->rules(self::requireQuoteDetailsRule())
                                            ->visible(fn (Get $get): bool => filled($get('plan')) && $get('plan') !== 'CM')
                                            ->table([
                                                TableColumn::make('Rango de Edad')->width('300px'),
                                                TableColumn::make('Total de personas'),
                                            ])
                                            ->schema([
                                                Radio::make('age_range_id')
                                                    ->label(false)
                                                    ->inLine()
                                                    ->live()
                                                    ->disableOptionWhen(function ($value, $state, Get $get) {
                                                        return collect($get('../*.age_range_id'))
                                                            ->reject(fn ($id) => $id == $state)
                                                            ->filter()
                                                            ->contains($value);
                                                    })
                                                    ->options(function (Get $get): array {
                                                        $planId = (int) (
                                                            $get('plan_id')
                                                            ?? $get('../../plan')
                                                            ?? 0
                                                        );

                                                        if ($planId === 0) {
                                                            return [];
                                                        }

                                                        return AgeRange::query()
                                                            ->where('plan_id', $planId)
                                                            ->pluck('range', 'id')
                                                            ->all();
                                                    })
                                                    ->columnSpan(4),
                                                Select::make('total_persons')
                                                    ->label(false)
                                                    ->options([
                                                        1 => 1,
                                                        2 => 2,
                                                        3 => 3,
                                                        4 => 4,
                                                        5 => 5,
                                                        6 => 6,
                                                        7 => 7,
                                                        8 => 8,
                                                        9 => 9,
                                                        10 => 10,
                                                    ])
                                                    ->placeholder('Cantidad de personas'),
                                                Hidden::make('plan_id'),
                                            ])
                                            ->columns(4),

                                        Repeater::make('details_quote_multiple')
                                            ->label('Indique los planes, la edad y número de personas:')
                                            ->addActionLabel('Añadir plan')
                                            ->rules(self::requireQuoteDetailsRule())
                                            ->visible(fn (Get $get): bool => $get('plan') === 'CM')
                                            ->schema([
                                                Radio::make('plan_id')
                                                    ->label('Seleccione una opción y añadir plan:')

                                                    ->inLine()
                                                    ->live()
                                                    ->options(function (Get $get) {
                                                        return Plan::where('type', 'BASICO')->where('status', 'ACTIVO')->pluck('description', 'id');
                                                    })->columnSpan(3),
                                                Select::make('age_range_id')
                                                    ->label('Rango de edad')
                                                    ->placeholder('Rango de edad')
                                                    ->options(function (Get $get) {
                                                        return AgeRange::where('plan_id', $get('plan_id'))->pluck('range', 'id');
                                                    })
                                                    ->live()
                                                    ->searchable()
                                                    ->prefixIcon('heroicon-s-globe-europe-africa')
                                                    ->disableOptionWhen(function ($value, $state, Get $get) {
                                                        return collect($get('../*.age_range_id'))
                                                            ->reject(fn ($id) => $id == $state)
                                                            ->filter()
                                                            ->contains($value);
                                                    })
                                                    ->validationMessages([
                                                        'required' => 'Campo Requerido',
                                                    ])
                                                    ->preload(),
                                                TextInput::make('total_persons')
                                                    ->label('Cantidad de personas')
                                                    ->placeholder('Cantidad de personas')
                                                    ->numeric()
                                                    ->minValue(1)
                                                    ->validationMessages([
                                                        'min' => 'La cantidad de personas debe ser como mínimo una (1) persona.',
                                                    ]),
                                            ])->columns(2),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
