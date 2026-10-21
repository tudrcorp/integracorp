<?php

declare(strict_types=1);

namespace App\Support\ClinicalEntitlements;

use App\Enums\ClinicalQuotaScope;
use App\Enums\ClinicalServiceChannel;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

/**
 * Una fila por beneficio en la tabla de «Uso clínico» del plan: todo lo que el
 * analista decide sobre un beneficio cabe en una línea.
 */
final class PlanClinicalUsageRowSchema
{
    /**
     * @return list<TableColumn>
     */
    public static function columns(): array
    {
        return [
            TableColumn::make('Beneficio')->width('28%'),
            TableColumn::make('¿Aplica?')->width('7rem'),
            TableColumn::make('Servicio clínico')->markAsRequired()->width('15%'),
            TableColumn::make('Servicio macro')->width('22%'),
            TableColumn::make('Cómo se cuenta el cupo')->markAsRequired()->wrapHeader()->width('16%'),
            TableColumn::make('Cantidad')->width('7rem'),
        ];
    }

    /**
     * @param  callable(): ?int  $planId
     * @return array<int, mixed>
     */
    public static function fields(callable $planId): array
    {
        $applies = static fn (Get $get): bool => (bool) $get('applies_clinically');
        $usesMacro = static fn (Get $get): bool => $applies($get)
            && (bool) ClinicalServiceChannel::fromStored($get('channel'))?->usesTelemedicineServiceList();
        $requiresQuota = static fn (Get $get): bool => $applies($get)
            && (ClinicalQuotaScope::fromStored($get('quota_scope'))?->requiresQuota() ?? false);

        return [
            Hidden::make('benefit_id'),
            Hidden::make('benefit_label'),
            Hidden::make('origin'),
            Hidden::make('copied_from'),
            Hidden::make('service_id'),
            Placeholder::make('benefit_summary')
                ->hiddenLabel()
                ->content(fn (Get $get): HtmlString => self::summaryHtml([
                    'benefit_label' => $get('benefit_label'),
                    'origin' => $get('origin'),
                    'copied_from' => $get('copied_from'),
                    'applies_clinically' => $get('applies_clinically'),
                    'channel' => $get('channel'),
                    'telemedicine_service_list_id' => $get('telemedicine_service_list_id'),
                    'quota_scope' => $get('quota_scope'),
                    'quota' => $get('quota'),
                ])),
            Toggle::make('applies_clinically')
                ->hiddenLabel()
                ->default(true)
                ->live()
                ->onIcon('heroicon-m-check')
                ->offIcon('heroicon-m-x-mark')
                ->extraAttributes(['title' => 'Apáguelo si el beneficio no se usa en consulta ni en operaciones (funerario, muerte accidental…).']),
            Select::make('channel')
                ->hiddenLabel()
                ->placeholder('Elegir…')
                ->options(self::channelOptions())
                ->required($applies)
                ->visible($applies)
                ->live()
                ->native(false)
                ->afterStateUpdated(function (mixed $state, Set $set): void {
                    if (! ClinicalServiceChannel::fromStored($state)?->usesTelemedicineServiceList()) {
                        $set('telemedicine_service_list_id', null);
                    }
                }),
            Select::make('telemedicine_service_list_id')
                ->hiddenLabel()
                ->placeholder('Elegir servicio macro…')
                ->options(fn (): array => PlanClinicalUsageWorkbench::serviceMacroNames())
                ->searchable()
                ->required($usesMacro)
                ->visible($usesMacro)
                ->live()
                ->createOptionForm([
                    TextInput::make('name')
                        ->label('Nombre del servicio macro')
                        ->placeholder('Ej: NUTRICIÓN A DOMICILIO')
                        ->helperText('Así lo verá el médico en «Tipo de servicio» de la consulta. Se guarda en mayúsculas.')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('description')
                        ->label('Descripción corta (opcional)')
                        ->maxLength(255),
                ])
                ->createOptionAction(fn (Action $action): Action => $action
                    ->modalHeading('Nuevo servicio macro')
                    ->modalDescription('Se crea en el momento y queda disponible para todos los planes. Si ya existe uno con el mismo nombre, se usa ese y no se duplica. Queda registrado en el historial.')
                    ->modalSubmitActionLabel('Crear y seleccionar')
                    ->modalWidth(Width::Large)
                    ->tooltip('Crear un servicio macro nuevo'))
                ->createOptionUsing(function (array $data, $livewire) use ($planId): ?int {
                    if (! method_exists($livewire, 'clinicalUsageIsUnlocked') || ! $livewire->clinicalUsageIsUnlocked()) {
                        abort(403, 'Debe autorizar esta visita con la clave OTP de un SUPERADMIN.');
                    }

                    $user = Auth::user();
                    abort_unless($user instanceof User, 403);

                    $result = PlanClinicalUsageWorkbench::createServiceMacro(
                        (string) ($data['name'] ?? ''),
                        isset($data['description']) ? (string) $data['description'] : null,
                        $user,
                        $planId(),
                    );

                    Notification::make()
                        ->title($result['created'] ? 'Servicio macro creado' : 'Ese servicio macro ya existía')
                        ->body($result['created']
                            ? '«'.$result['service']->name.'» quedó seleccionado en esta fila y disponible para todos los planes.'
                            : 'Se seleccionó «'.$result['service']->name.'» para no duplicarlo.')
                        ->success()
                        ->send();

                    return (int) $result['service']->id;
                }),
            Select::make('quota_scope')
                ->hiddenLabel()
                ->placeholder('Elegir…')
                ->options(ClinicalQuotaScope::options())
                ->required($applies)
                ->visible($applies)
                ->live()
                ->native(false)
                ->afterStateUpdated(function (mixed $state, Set $set): void {
                    if (! (ClinicalQuotaScope::fromStored($state)?->requiresQuota() ?? false)) {
                        $set('quota', null);
                    }
                }),
            TextInput::make('quota')
                ->hiddenLabel()
                ->placeholder('Ej: 4')
                ->numeric()
                ->integer()
                ->minValue(1)
                ->maxValue(999)
                ->required($requiresQuota)
                ->visible($requiresQuota)
                ->live(onBlur: true)
                ->validationAttribute('cantidad'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function channelOptions(): array
    {
        $options = [];

        foreach (ClinicalServiceChannel::cases() as $case) {
            $options[$case->value] = $case->shortLabel();
        }

        return $options;
    }

    /**
     * Nombre del beneficio con su estado: listo, qué le falta, si es nuevo o
     * si vino copiado de otro plan.
     *
     * @param  array<string, mixed>  $row
     */
    public static function summaryHtml(array $row): HtmlString
    {
        $applies = filter_var($row['applies_clinically'] ?? true, FILTER_VALIDATE_BOOLEAN);

        return new HtmlString(view('filament.business.resources.plans.partials.clinical-usage-row-summary', [
            'label' => (string) ($row['benefit_label'] ?? 'Beneficio'),
            'applies' => $applies,
            'missing' => PlanClinicalUsageWorkbench::rowMissingHint($row),
            'origin' => (string) ($row['origin'] ?? PlanClinicalUsageWorkbench::ORIGIN_PLAN),
            'copiedFrom' => filled($row['copied_from'] ?? null) ? (string) $row['copied_from'] : null,
        ])->render());
    }
}
