<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\CapitationPlans\Schemas;

use App\Enums\CapitationPaymentFrequency;
use App\Models\Benefit;
use App\Models\CapitationPlan;
use App\Support\Business\CapitationPlans\CapitationProviders;
use App\Support\Operations\OutsourcingMedicalDepartment;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

final class CapitationPlanForm
{
    public const DOCUMENT_DIRECTORY = 'capitation-plans';

    public const MAX_DOCUMENTS = 10;

    public const MAX_DOCUMENT_KB = 10 * 1024;

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Proveedor')
                ->description('Solo aparecen los proveedores de la red Outsourcing (jurídicos y naturales).')
                ->icon(Heroicon::OutlinedBuildingOffice2)
                ->columnSpanFull()
                ->schema([
                    Select::make('provider_key')
                        ->label('Proveedor')
                        ->placeholder('Buscar por nombre o RIF')
                        ->options(fn (): array => CapitationProviders::search())
                        ->getSearchResultsUsing(fn (string $search): array => CapitationProviders::search($search))
                        ->getOptionLabelUsing(fn (mixed $value): ?string => CapitationProviders::label($value))
                        ->searchable()
                        ->required()
                        ->live()
                        ->native(false)
                        ->helperText(fn (): ?HtmlString => CapitationProviders::exists() ? null : new HtmlString(
                            '<span class="font-medium text-amber-700 dark:text-amber-300">Todavía no hay proveedores en la red Outsourcing. Actívelo en Operaciones, en la ficha del proveedor: «Pertenece al Departamento Médico Outsourcing».</span>'
                        ))
                        ->validationMessages(['required' => 'Elija el proveedor al que pertenece el plan.']),
                    Placeholder::make('provider_reference')
                        ->hiddenLabel()
                        ->visible(fn (Get $get): bool => filled($get('provider_key')))
                        ->content(fn (Get $get): HtmlString => self::providerReference($get('provider_key'))),
                ]),
            Section::make('Plan')
                ->icon(Heroicon::OutlinedClipboardDocumentList)
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    TextInput::make('name')
                        ->label('Nombre del plan')
                        ->placeholder('Ej.: Plan Atención Primaria Zona Oriente')
                        ->required()
                        ->maxLength(150),
                    ToggleButtons::make('status')
                        ->label('Estado')
                        ->options([CapitationPlan::STATUS_ACTIVE => 'Activo', CapitationPlan::STATUS_INACTIVE => 'Inactivo'])
                        ->colors([CapitationPlan::STATUS_ACTIVE => 'success', CapitationPlan::STATUS_INACTIVE => 'gray'])
                        ->default(CapitationPlan::STATUS_ACTIVE)
                        ->inline()
                        ->required(),
                    TextInput::make('cost_per_affiliate_usd')
                        ->label('Costo por afiliado')
                        ->prefix('US$')
                        ->numeric()
                        ->minValue(0.01)
                        ->maxValue(99999999.99)
                        ->step(0.01)
                        ->required()
                        ->live(onBlur: true),
                    ToggleButtons::make('payment_frequency')
                        ->label('Frecuencia de pago')
                        ->options(CapitationPaymentFrequency::options())
                        ->inline()
                        ->required()
                        ->live()
                        ->validationMessages(['required' => 'Elija la frecuencia de pago.']),
                    Placeholder::make('monthly_equivalent')
                        ->hiddenLabel()
                        ->columnSpanFull()
                        ->visible(fn (Get $get): bool => is_numeric($get('cost_per_affiliate_usd')) && CapitationPaymentFrequency::tryFrom((string) $get('payment_frequency')) !== null)
                        ->content(fn (Get $get): string => self::equivalence($get('cost_per_affiliate_usd'), $get('payment_frequency'))),
                    DatePicker::make('valid_from')
                        ->label('Vigente desde')
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->closeOnDateSelection(),
                    DatePicker::make('valid_until')
                        ->label('Vigente hasta')
                        ->helperText('Opcional. Déjelo vacío si el plan no tiene fecha de fin.')
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->closeOnDateSelection()
                        ->afterOrEqual('valid_from')
                        ->validationMessages(['after_or_equal' => 'La vigencia «hasta» no puede ser anterior a «desde».']),
                ]),
            Section::make('Beneficios y servicios')
                ->description('Elíjalos del catálogo de Beneficios o escriba uno que no exista. Arrastre para ordenarlos.')
                ->icon(Heroicon::OutlinedSparkles)
                ->columnSpanFull()
                ->schema([
                    Repeater::make('benefits')
                        ->hiddenLabel()
                        ->table([
                            TableColumn::make('Del catálogo')->width('42%'),
                            TableColumn::make('O escriba el beneficio')->width('33%'),
                            TableColumn::make('Nota')->width('25%'),
                        ])
                        ->compact()
                        ->addActionLabel('Agregar beneficio o servicio')
                        ->defaultItems(1)
                        ->minItems(1)
                        ->maxItems(100)
                        ->reorderable()
                        ->schema([
                            Select::make('benefit_id')
                                ->label('Del catálogo')
                                ->placeholder('Buscar beneficio')
                                ->options(fn (): array => self::catalogOptions())
                                ->getOptionLabelUsing(fn (mixed $value): ?string => self::catalogOptions()[(int) $value]
                                    ?? Benefit::query()->whereKey((int) $value)->value('description'))
                                ->searchable()
                                ->live()
                                ->afterStateUpdated(function (mixed $state, Set $set): void {
                                    if (filled($state)) {
                                        $set('custom_name', null);
                                    }
                                })
                                ->native(false),
                            /** En tabla cada campo es una celda: se deshabilita en lugar de ocultarse para no correr columnas. */
                            TextInput::make('custom_name')
                                ->label('O escriba el beneficio')
                                ->placeholder(fn (Get $get): string => filled($get('benefit_id')) ? '— del catálogo —' : 'Ej.: Consulta a domicilio')
                                ->maxLength(255)
                                ->disabled(fn (Get $get): bool => filled($get('benefit_id')))
                                ->required(fn (Get $get): bool => blank($get('benefit_id')))
                                ->validationMessages(['required' => 'Elija uno del catálogo o escríbalo.']),
                            TextInput::make('note')
                                ->label('Nota')
                                ->placeholder('Ej.: hasta 2 por año')
                                ->maxLength(255),
                        ]),
                ]),
            Section::make('Negociación')
                ->icon(Heroicon::OutlinedDocumentCheck)
                ->columnSpanFull()
                ->schema([
                    Textarea::make('negotiation_type')
                        ->label('Tipo de negociación')
                        ->placeholder('Ej.: tarifa fija por afiliado asignado, revisión semestral, incluye traslados urbanos…')
                        ->rows(4)
                        ->required()
                        ->minLength(10)
                        ->maxLength(5000)
                        ->validationMessages(['required' => 'Describa el tipo de negociación.', 'min' => 'Describa la negociación con al menos 10 caracteres.', 'minLength' => 'Describa la negociación con al menos 10 caracteres.']),
                    FileUpload::make('documents')
                        ->label('Documentos de respaldo')
                        ->helperText('PDF, imágenes o Word. Hasta '.self::MAX_DOCUMENTS.' archivos de 10 MB cada uno. Se guardan en privado.')
                        ->multiple()
                        ->maxFiles(self::MAX_DOCUMENTS)
                        ->maxSize(self::MAX_DOCUMENT_KB)
                        ->disk('local')
                        ->directory(self::DOCUMENT_DIRECTORY)
                        ->visibility('private')
                        ->acceptedFileTypes([
                            'application/pdf',
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                            'application/msword',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        ])
                        ->storeFileNamesIn('document_names')
                        ->openable()
                        ->downloadable()
                        ->reorderable()
                        ->panelLayout('grid'),
                ]),
        ]);
    }

    /**
     * @return array<int, string>
     */
    public static function catalogOptions(): array
    {
        return once(fn (): array => Benefit::query()
            ->where('status', 'ACTIVO')
            ->orderBy('description')
            ->get(['id', 'description', 'code'])
            ->mapWithKeys(fn (Benefit $benefit): array => [(int) $benefit->id => trim((string) $benefit->description).(filled($benefit->code) ? ' · '.$benefit->code : '')])
            ->all());
    }

    private static function providerReference(mixed $key): HtmlString
    {
        try {
            $parsed = CapitationProviders::parse($key);
        } catch (\InvalidArgumentException) {
            return new HtmlString('');
        }

        $provider = CapitationProviders::find($parsed['type'], $parsed['id']);

        if ($provider === null) {
            return new HtmlString('');
        }

        $plans = CapitationPlan::query()->forProvider($parsed['type'], $parsed['id'])->where('status', CapitationPlan::STATUS_ACTIVE)->count();
        $warning = CapitationProviders::isOutsourcing($provider) ? '' : '<br><span class="font-medium text-amber-700 dark:text-amber-300">Este proveedor ya no está en la red Outsourcing.</span>';

        return new HtmlString(
            '<p class="rounded-lg bg-sky-50 px-3 py-2 text-sm text-sky-800 ring-1 ring-sky-600/20 dark:bg-sky-500/10 dark:text-sky-200 dark:ring-sky-400/30">'
            .'Tarifa de referencia en su ficha: <strong>'.e(OutsourcingMedicalDepartment::summary($provider)).'</strong> · '
            .($plans === 1 ? '1 plan activo' : $plans.' planes activos').' registrados.'
            .$warning
            .'</p>'
        );
    }

    private static function equivalence(mixed $cost, mixed $frequency): string
    {
        $frequency = CapitationPaymentFrequency::tryFrom((string) $frequency);

        if ($frequency === null || ! is_numeric($cost)) {
            return '';
        }

        $amount = 'US$ '.number_format((float) $cost, 2, ',', '.');

        return $frequency === CapitationPaymentFrequency::Monthly
            ? $amount.' por afiliado cada mes.'
            : $amount.' por afiliado, '.mb_strtolower($frequency->label()).' ≈ US$ '.number_format($frequency->monthlyEquivalent((float) $cost), 2, ',', '.').' por mes.';
    }
}
