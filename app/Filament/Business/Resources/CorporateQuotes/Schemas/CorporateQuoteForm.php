<?php

namespace App\Filament\Business\Resources\CorporateQuotes\Schemas;

use App\Http\Controllers\UtilsController;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\AgeRange;
use App\Models\CorporateQuote;
use App\Models\Plan;
use Filament\Forms\Components\Checkbox;
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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class CorporateQuoteForm
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

    private static function normalizeQuotePlanType(?string $type): string
    {
        return $type === 'DRESS-TAILOR' ? 'DRESS-TAILOR' : 'BASICO';
    }

    private static function syncQuoteTypeFromCategoryCheckbox(Set $set, string $selectedType): void
    {
        $normalizedType = self::normalizeQuotePlanType($selectedType);

        $set('quote_type_basico', $normalizedType === 'BASICO');
        $set('quote_type_dress_tylor', $normalizedType === 'DRESS-TAILOR');
        $set('type', $normalizedType);
        $set('plan', null);
        $set('details_quote', []);
        $set('details_quote_multiple', []);
    }

    /**
     * @return Collection<int|string, string>
     */
    private static function planOptionsForQuoteType(?string $type): Collection
    {
        $plans = Plan::query()
            ->where('type', self::normalizeQuotePlanType($type))
            ->where('status', 'ACTIVO')
            ->orderBy('description')
            ->pluck('description', 'id');

        return $plans->put('CM', 'COTIZACIÓN MULTIPLE');
    }

    /**
     * @return array<int|string, string>
     */
    private static function planDescriptionsForQuoteType(?string $type): array
    {
        $descriptions = Plan::query()
            ->where('type', self::normalizeQuotePlanType($type))
            ->where('status', 'ACTIVO')
            ->withCount('ageRanges')
            ->orderBy('description')
            ->get()
            ->mapWithKeys(fn (Plan $plan): array => [
                $plan->id => $plan->age_ranges_count.' rango(s) de edad disponible(s).',
            ])
            ->all();

        $descriptions['CM'] = 'Seleccione más de dos (2) planes.';

        return $descriptions;
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('corporateQuoteFormTabs')
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
                                                Checkbox::make('quote_type_basico')
                                                    ->label('Básico')
                                                    ->dehydrated(false)
                                                    ->default(true)
                                                    ->live()
                                                    ->afterStateHydrated(function (Checkbox $component, mixed $state, Get $get): void {
                                                        $component->state(self::normalizeQuotePlanType($get('type')) === 'BASICO');
                                                    })
                                                    ->afterStateUpdated(function (?bool $state, Set $set): void {
                                                        self::syncQuoteTypeFromCategoryCheckbox($set, $state ? 'BASICO' : 'DRESS-TAILOR');
                                                    }),
                                                Checkbox::make('quote_type_dress_tylor')
                                                    ->label('Dress Tylor')
                                                    ->dehydrated(false)
                                                    ->default(false)
                                                    ->live()
                                                    ->afterStateHydrated(function (Checkbox $component, mixed $state, Get $get): void {
                                                        $component->state(self::normalizeQuotePlanType($get('type')) === 'DRESS-TAILOR');
                                                    })
                                                    ->afterStateUpdated(function (?bool $state, Set $set): void {
                                                        self::syncQuoteTypeFromCategoryCheckbox($set, $state ? 'DRESS-TAILOR' : 'BASICO');
                                                    }),
                                                Hidden::make('type')
                                                    ->default('BASICO')
                                                    ->dehydrated(),
                                            ])->columnSpanFull(),
                                        Grid::make(4)
                                            ->schema([
                                                TextInput::make('code')
                                                    ->label('Nro. de cotización')
                                                    ->prefixIcon('heroicon-m-clipboard-document-check')
                                                    ->default(function () {
                                                        if (CorporateQuote::max('id') == null) {
                                                            $parte_entera = 0;
                                                        } else {
                                                            $parte_entera = CorporateQuote::max('id');
                                                        }

                                                        return 'COT-CORP-000'.$parte_entera + 1;
                                                    })
                                                    ->disabled()
                                                    ->dehydrated()
                                                    ->maxLength(255),

                                            ])->columnSpanFull(),
                                        Grid::make(4)
                                            ->schema([
                                                TextInput::make('full_name')
                                                    ->label('Nombre de la Empresa')
                                                    ->prefixIcon('heroicon-m-user')
                                                    ->required()
                                                    ->validationMessages([
                                                        'required' => 'Campo requerido',
                                                    ])
                                                    ->maxLength(255)
                                                    ->afterStateUpdatedJs(<<<'JS'
                                                    $set('full_name', $state.toUpperCase());
                                                JS),

                                                Select::make('country_code')
                                                    ->label('Código de país')
                                                    ->options(UtilsController::getCountries())
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
                                            ->options(fn (Get $get): array => self::planOptionsForQuoteType($get('type'))->all())
                                            ->descriptions(fn (Get $get): array => self::planDescriptionsForQuoteType($get('type'))),
                                    ]),
                            ]),
                        Tab::make('RANGO DE EDAD')
                            ->icon(Heroicon::OutlinedUsers)
                            ->schema([
                                Section::make('age_range')
                                    ->heading('¡Listo para el último paso! 🏁')
                                    // ->description(new HtmlString(Blade::render(<<<BLADE
                                    //         <div class="fi-section-header-description">
                                    //             Por favor, selecciona el rango de edades de los beneficiarios. Al hacerlo, habrás finalizado la configuración principal de la cotización y estarás a un clic de generar el resultado final.
                                    //             <br>
                                    //             ¡Gracias por tu gran trabajo!
                                    //         </div>
                                    //     BLADE))
                                    // )
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
                                                TextInput::make('total_persons')
                                                    ->label(false)
                                                    ->placeholder('Cantidad de personas')
                                                    ->numeric()
                                                    ->minValue(1)
                                                    ->validationMessages([
                                                        'min' => 'La cantidad de personas debe ser como mínimo una (1) persona.',
                                                    ]),
                                                Hidden::make('plan_id'),
                                            ])
                                            ->columns(4),

                                        Repeater::make('details_quote_multiple')
                                            ->label('Indique los planes, la edad y número de personas:')
                                            ->addActionLabel('Añadir plan')
                                            ->rules(self::requireQuoteDetailsRule())
                                            ->visible(fn (Get $get): bool => $get('plan') === 'CM')
                                            ->schema([
                                                Select::make('plan_id')
                                                    ->label('Seleccione el plan:')
                                                    ->live()
                                                    ->options(function (Get $get): array {
                                                        return Plan::query()
                                                            ->where('type', self::normalizeQuotePlanType($get('type')))
                                                            ->where('status', 'ACTIVO')
                                                            ->orderBy('description')
                                                            ->pluck('description', 'id')
                                                            ->all();
                                                    }),
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
                                            ])->columns(3),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
