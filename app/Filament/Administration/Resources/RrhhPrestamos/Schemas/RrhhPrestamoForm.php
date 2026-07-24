<?php

namespace App\Filament\Administration\Resources\RrhhPrestamos\Schemas;

use App\Models\RrhhColaborador;
use App\Support\Rrhh\RrhhPrestamoCuotaCalculo;
use Closure;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class RrhhPrestamoForm
{
    private const SECTION_CARD = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    private const INNER_CARD = 'rounded-2xl border border-slate-200/80 bg-white/80 p-4 shadow-inner dark:border-white/10 dark:bg-white/5 sm:p-5';

    private const TABS_CONTAINER = 'rounded-[1.75rem] border border-slate-200/85 bg-gradient-to-br from-white via-slate-50/90 to-white p-2 shadow-[0_24px_60px_-26px_rgba(15,23,42,0.2)] ring-1 ring-slate-200/55 dark:border-white/10 dark:from-slate-900/95 dark:via-slate-950/95 dark:to-slate-900/95 dark:ring-white/10 dark:shadow-[0_24px_60px_-24px_rgba(0,0,0,0.55)]';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('rrhhPrestamoFormTabs')
                    ->columnSpanFull()
                    ->extraAttributes([
                        'class' => self::TABS_CONTAINER,
                    ])
                    ->tabs([
                        Tab::make('Información principal')
                            ->icon('heroicon-o-credit-card')
                            ->schema([
                                Fieldset::make('Colaborador y descripción')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Fieldset::make('Identificación del préstamo')
                                            ->extraAttributes(['class' => self::INNER_CARD])
                                            ->schema([
                                                Select::make('colaborador_id')
                                                    ->label('Colaborador')
                                                    ->relationship('colaborador', 'fullName', fn ($query) => $query->orderBy('fullName'))
                                                    ->searchable()
                                                    ->preload()
                                                    ->native(false)
                                                    ->required()
                                                    ->live()
                                                    ->placeholder('Seleccione colaborador')
                                                    ->prefixIcon('heroicon-m-user')
                                                    ->helperText('Persona a la que se asigna el préstamo.')
                                                    ->afterStateUpdated(function (mixed $state, Get $get, Set $set): void {
                                                        self::sincronizarMontoCuotaDesdePorcentaje($state, $get('interes'), $set);
                                                    })
                                                    ->columnSpanFull(),
                                                Textarea::make('descripcion')
                                                    ->label('Descripción')
                                                    ->required()
                                                    ->rows(3)
                                                    ->placeholder('Motivo o detalle del préstamo.')
                                                    ->helperText('Describe el propósito del préstamo.')
                                                    ->columnSpanFull(),
                                            ])
                                            ->columns(1)
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(1)
                                    ->columnSpanFull(),
                                Fieldset::make('Condiciones del préstamo')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Fieldset::make('Monto, descuento y cuotas')
                                            ->extraAttributes(['class' => self::INNER_CARD])
                                            ->schema([
                                                TextInput::make('monto')
                                                    ->label('Monto del préstamo')
                                                    ->required()
                                                    ->numeric()
                                                    ->minValue(0.01)
                                                    ->step(0.01)
                                                    ->default(0.0)
                                                    ->live(onBlur: true)
                                                    ->prefix('US$')
                                                    ->placeholder('0.00')
                                                    ->helperText('Total exacto que debe recuperarse con la suma de las cuotas.')
                                                    ->rules([
                                                        fn (Get $get): Closure => self::reglaCuotasCuadranExacto($get),
                                                    ]),
                                                TextInput::make('interes')
                                                    ->label('Porcentaje de descuento')
                                                    ->required()
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->maxValue(100)
                                                    ->step(0.01)
                                                    ->default(0.0)
                                                    ->live(onBlur: true)
                                                    ->suffix('%')
                                                    ->placeholder('0.00')
                                                    ->prefixIcon('heroicon-m-receipt-percent')
                                                    ->helperText('Porcentaje sobre el sueldo base del colaborador para estimar el monto de cada cuota.')
                                                    ->afterStateUpdated(function (mixed $state, Get $get, Set $set): void {
                                                        self::sincronizarMontoCuotaDesdePorcentaje($get('colaborador_id'), $state, $set);
                                                    })
                                                    ->rules([
                                                        fn (Get $get): Closure => self::reglaCuotasCuadranExacto($get),
                                                    ]),
                                                TextInput::make('nro_cuotas')
                                                    ->label('Número de cuotas')
                                                    ->required()
                                                    ->numeric()
                                                    ->minValue(1)
                                                    ->integer()
                                                    ->live(onBlur: true)
                                                    ->placeholder('Ej: 5')
                                                    ->prefixIcon('heroicon-m-calendar-days')
                                                    ->helperText('Cantidad de descuentos programados. Su producto por el monto de cuota debe igualar el préstamo.')
                                                    ->rules([
                                                        fn (Get $get): Closure => self::reglaCuotasCuadranExacto($get),
                                                    ]),
                                                TextInput::make('monto_cuota')
                                                    ->label('Monto de cada descuento')
                                                    ->required()
                                                    ->numeric()
                                                    ->minValue(0.01)
                                                    ->step(0.01)
                                                    ->live(onBlur: true)
                                                    ->prefix('US$')
                                                    ->placeholder('0.00')
                                                    ->helperText('Monto a descontar por cuota. Puede ajustarse manualmente tras estimar con el porcentaje.')
                                                    ->rules([
                                                        fn (Get $get): Closure => self::reglaCuotasCuadranExacto($get),
                                                    ]),
                                                Select::make('status')
                                                    ->label('Estado')
                                                    ->options([
                                                        'activo' => 'Activo',
                                                        'pagado' => 'Pagado',
                                                        'cancelado' => 'Cancelado',
                                                    ])
                                                    ->default('activo')
                                                    ->required()
                                                    ->native(false)
                                                    ->searchable()
                                                    ->preload()
                                                    ->placeholder('Seleccione estado')
                                                    ->prefixIcon('heroicon-m-signal'),
                                                Placeholder::make('validacion_cuotas')
                                                    ->label('Validación del cálculo')
                                                    ->content(function (Get $get): HtmlString {
                                                        $mensaje = RrhhPrestamoCuotaCalculo::resumenValidacion(
                                                            $get('monto'),
                                                            $get('nro_cuotas'),
                                                            $get('monto_cuota'),
                                                        );

                                                        $ok = RrhhPrestamoCuotaCalculo::cuadraExacto(
                                                            $get('monto'),
                                                            $get('nro_cuotas'),
                                                            $get('monto_cuota'),
                                                        );

                                                        $clase = $ok
                                                            ? 'rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-950/40 dark:text-emerald-200'
                                                            : 'rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-500/30 dark:bg-rose-950/40 dark:text-rose-200';

                                                        return new HtmlString('<div class="'.$clase.'">'.e($mensaje).'</div>');
                                                    })
                                                    ->columnSpanFull(),
                                            ])
                                            ->columns(2)
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(1)
                                    ->columnSpanFull(),
                            ]),
                    ]),
                Hidden::make('created_by')
                    ->default(fn () => Auth::id())
                    ->dehydrated()
                    ->hiddenOn('edit'),
            ]);
    }

    private static function sincronizarMontoCuotaDesdePorcentaje(mixed $colaboradorId, mixed $porcentaje, Set $set): void
    {
        if (blank($colaboradorId) || blank($porcentaje) || (float) $porcentaje <= 0) {
            return;
        }

        $sueldo = (float) (RrhhColaborador::query()->whereKey($colaboradorId)->value('sueldo') ?? 0);

        if ($sueldo <= 0) {
            return;
        }

        $set('monto_cuota', RrhhPrestamoCuotaCalculo::montoCuotaDesdePorcentaje($sueldo, $porcentaje));
    }

    private static function reglaCuotasCuadranExacto(Get $get): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($get): void {
            $monto = $get('monto');
            $nroCuotas = $get('nro_cuotas');
            $montoCuota = $get('monto_cuota');

            if (blank($monto) || blank($nroCuotas) || blank($montoCuota)) {
                return;
            }

            if (! RrhhPrestamoCuotaCalculo::cuadraExacto($monto, $nroCuotas, $montoCuota)) {
                $fail(RrhhPrestamoCuotaCalculo::mensajeError($monto, $nroCuotas, $montoCuota));
            }
        };
    }
}
