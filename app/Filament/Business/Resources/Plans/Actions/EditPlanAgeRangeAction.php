<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\Plans\Actions;

use App\Filament\Business\Resources\Plans\PlanResource;
use App\Models\AgeRange;
use App\Models\Fee;
use App\Models\Plan;
use App\Models\User;
use App\Support\Plans\PlanAgeRangeEditor;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use RuntimeException;
use Throwable;

/**
 * «Editar rango de edad» de un plan: cambia las edades de un rango existente y
 * lo propaga a todo lo que lo usa (ver {@see PlanAgeRangeEditor}).
 */
final class EditPlanAgeRangeAction
{
    public static function make(string $name = 'editAgeRange'): Action
    {
        return Action::make($name)
            ->label('Editar rango de edad')
            ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
            ->color('gray')
            ->visible(fn (mixed $record): bool => $record instanceof Plan
                && PlanResource::canEdit($record)
                && PlanAgeRangeEditor::rangesFor($record)->isNotEmpty())
            ->modalHeading('Editar rango de edad')
            ->modalDescription('Cambia las edades del rango en todo el sistema: tarifas, afiliaciones, cotizaciones y renovaciones que lo usan. Los precios no cambian. Los PDF ya emitidos conservan el texto anterior; lo que se genere desde ahora sale con el rango nuevo.')
            ->modalIcon(Heroicon::OutlinedAdjustmentsHorizontal)
            ->modalWidth(Width::ThreeExtraLarge)
            ->fillForm(function (Plan $record): array {
                $range = PlanAgeRangeEditor::rangesFor($record)->first();

                return [
                    'age_range_id' => $range?->getKey(),
                    'age_init' => $range?->age_init,
                    'age_end' => $range?->age_end,
                ];
            })
            ->schema([
                Select::make('age_range_id')
                    ->label('Rango a editar')
                    ->options(fn (Plan $record): array => self::rangeOptions($record))
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (mixed $state, Set $set): void {
                        $range = AgeRange::query()->find($state);
                        $set('age_init', $range?->age_init);
                        $set('age_end', $range?->age_end);
                    }),
                Grid::make(2)->schema([
                    TextInput::make('age_init')
                        ->label('Edad inicial')
                        ->helperText('Use 0 para incluir a los recién nacidos (menos de un año).')
                        ->integer()
                        ->minValue(0)
                        ->maxValue(PlanAgeRangeEditor::MAX_AGE)
                        ->required()
                        ->live(onBlur: true),
                    TextInput::make('age_end')
                        ->label('Edad final')
                        ->integer()
                        ->minValue(0)
                        ->maxValue(PlanAgeRangeEditor::MAX_AGE)
                        ->gte('age_init')
                        ->required()
                        ->live(onBlur: true),
                ]),
                Placeholder::make('impact')
                    ->label('Qué va a cambiar')
                    ->content(fn (Get $get, Plan $record): HtmlString => self::impactView($get, $record)),
                Textarea::make('reason')
                    ->label('Motivo del cambio')
                    ->placeholder('Ej.: El contrato con la empresa cubre desde los 0 años; hay recién nacidos sin plan.')
                    ->rows(2)
                    ->required()
                    ->minLength(PlanAgeRangeEditor::REASON_MIN_LENGTH)
                    ->maxLength(1000),
            ])
            ->modalSubmitActionLabel('Guardar en todo el sistema')
            ->action(function (array $data, Plan $record, Action $action): void {
                abort_unless(PlanResource::canEdit($record), 403);
                $user = Auth::user();
                abort_unless($user instanceof User, 403);

                $range = PlanAgeRangeEditor::rangesFor($record)->firstWhere('id', (int) ($data['age_range_id'] ?? 0));
                if (! $range instanceof AgeRange) {
                    Notification::make()->title('No se guardó')->body('Ese rango no pertenece a este plan.')->danger()->send();
                    $action->halt();

                    return;
                }

                try {
                    $result = PlanAgeRangeEditor::apply($record, $range, (int) $data['age_init'], (int) $data['age_end'], (string) ($data['reason'] ?? ''), $user);
                } catch (RuntimeException $exception) {
                    Notification::make()->title('No se guardó')->body($exception->getMessage())->danger()->persistent()->send();
                    $action->halt();

                    return;
                } catch (Throwable $exception) {
                    report($exception);
                    Notification::make()->title('No se guardó')->body('Error inesperado. No se cambió nada; intente de nuevo.')->danger()->send();
                    $action->halt();

                    return;
                }

                Notification::make()
                    ->title('Rango de edad actualizado')
                    ->body('«'.$result['before']['range'].'» ahora es «'.$result['after']['range'].'» en todo el sistema. Textos actualizados: '.array_sum($result['copies']).'. Si tenía cargas de población con personas «sin plan para su edad», pulse «Volver a evaluar» en esas filas.')
                    ->success()
                    ->persistent()
                    ->send();
            });
    }

    /**
     * @return array<int, string>
     */
    private static function rangeOptions(Plan $plan): array
    {
        $prices = Fee::query()->where('plan_id', $plan->getKey())->whereNull('coverage_id')->pluck('price', 'age_range_id');

        return PlanAgeRangeEditor::rangesFor($plan)
            ->mapWithKeys(fn (AgeRange $range): array => [(int) $range->getKey() => $range->age_init.' a '.$range->age_end.' años'
                .(isset($prices[$range->getKey()]) ? ' · US$ '.number_format((float) $prices[$range->getKey()], 2, ',', '.') : '')])
            ->all();
    }

    private static function impactView(Get $get, Plan $plan): HtmlString
    {
        $range = PlanAgeRangeEditor::rangesFor($plan)->firstWhere('id', (int) $get('age_range_id'));
        $init = $get('age_init');
        $end = $get('age_end');

        if (! $range instanceof AgeRange || ! is_numeric($init) || ! is_numeric($end)) {
            return new HtmlString('<p class="text-sm text-gray-500 dark:text-gray-400">Elija el rango y escriba las edades.</p>');
        }

        return new HtmlString(view('filament.business.plans.age-range-impact', [
            'range' => $range,
            'label' => PlanAgeRangeEditor::label((int) $init, (int) $end),
            'impact' => PlanAgeRangeEditor::impact($plan, $range, (int) $init, (int) $end),
        ])->render());
    }
}
