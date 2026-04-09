<?php

namespace App\Filament\Business\Resources\AffiliationCorporates\RelationManagers;

use App\Http\Controllers\AffiliateCorporateController;
use App\Models\AffiliateCorporate;
use App\Models\AfilliationCorporatePlan;
use App\Models\AgeRange;
use App\Models\Plan;
use App\Support\FilamentDateDisplay;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CorporateAffiliatesRelationManager extends RelationManager
{
    protected static string $relationship = 'corporateAffiliates';

    protected static ?string $title = 'Afiliados';

    protected static string|BackedEnum|null $icon = 'heroicon-s-user-group';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos personales')
                    ->description('Identificación y datos básicos del familiar o colaborador.')
                    ->icon(Heroicon::User)
                    ->schema([
                        Grid::make(12)
                            ->schema([
                                TextInput::make('first_name')
                                    ->required()
                                    ->label('Nombre')
                                    ->maxLength(255)
                                    ->columnSpan(['default' => 12, 'lg' => 6]),
                                TextInput::make('last_name')
                                    ->label('Apellido')
                                    ->maxLength(255)
                                    ->columnSpan(['default' => 12, 'lg' => 6]),
                                TextInput::make('nro_identificacion')
                                    ->label('Número de identificación')
                                    ->required()
                                    ->numeric()
                                    ->columnSpan(['default' => 12, 'lg' => 4]),
                                Select::make('sex')
                                    ->label('Género')
                                    ->required()
                                    ->options([
                                        'MASCULINO' => 'Masculino',
                                        'FEMENINO' => 'Femenino',
                                    ])
                                    ->native(false)
                                    ->columnSpan(['default' => 12, 'lg' => 4]),
                                DatePicker::make('birth_date')
                                    ->label('Fecha de nacimiento')
                                    ->required()
                                    ->live()
                                    ->displayFormat('d/m/Y')
                                    ->format('d/m/Y')
                                    ->maxDate(now())
                                    ->afterStateUpdated(function (Set $set, ?string $state): void {
                                        if (blank($state)) {
                                            return;
                                        }
                                        try {
                                            $set('age', (int) Carbon::createFromFormat('d/m/Y', $state)->diffInYears(now()));
                                        } catch (\Throwable) {
                                            // formato inválido momentáneo al escribir
                                        }
                                    })
                                    ->columnSpan(['default' => 12, 'lg' => 4]),
                                TextInput::make('age')
                                    ->label('Edad')
                                    ->required()
                                    ->live()
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated()
                                    ->columnSpan(['default' => 12, 'lg' => 3]),
                            ]),
                    ])
                    ->compact()
                    ->columnSpanFull(),

                Section::make('Contacto')
                    ->description('Teléfono y correo para comunicación.')
                    ->icon(Heroicon::Phone)
                    ->schema([
                        Grid::make(12)
                            ->schema([
                                TextInput::make('phone')
                                    ->label('Teléfono')
                                    ->tel()
                                    ->columnSpan(['default' => 12, 'md' => 6]),
                                TextInput::make('email')
                                    ->label('Correo electrónico')
                                    ->required()
                                    ->email()
                                    ->columnSpan(['default' => 12, 'md' => 6]),
                            ]),
                    ])
                    ->compact()
                    ->columnSpanFull(),

                Section::make('Salud y empresa')
                    ->description('Condición médica declarada, antigüedad y cargo.')
                    ->icon(Heroicon::Heart)
                    ->schema([
                        Grid::make(12)
                            ->schema([
                                TextInput::make('condition_medical')
                                    ->label('Condición médica')
                                    ->columnSpanFull(),
                                DatePicker::make('initial_date')
                                    ->label('Fecha de ingreso a la empresa')
                                    ->displayFormat('d/m/Y')
                                    ->format('d/m/Y')
                                    ->columnSpan(['default' => 12, 'md' => 6]),
                                TextInput::make('position_company')
                                    ->label('Cargo en la empresa')
                                    ->maxLength(255)
                                    ->columnSpan(['default' => 12, 'md' => 6]),
                            ]),
                    ])
                    ->compact()
                    ->columnSpanFull(),

                Section::make('Emergencia y dirección')
                    ->description('Contacto de emergencia y domicilio.')
                    ->icon(Heroicon::MapPin)
                    ->schema([
                        Grid::make(12)
                            ->schema([
                                TextInput::make('full_name_emergency')
                                    ->label('Contacto de emergencia (nombre)')
                                    ->columnSpan(['default' => 12, 'md' => 6]),
                                TextInput::make('phone_emergency')
                                    ->label('Teléfono de emergencia')
                                    ->tel()
                                    ->columnSpan(['default' => 12, 'md' => 6]),
                                Textarea::make('address')
                                    ->label('Dirección')
                                    ->columnSpanFull()
                                    ->required()
                                    ->rows(3)
                                    ->autosize(),
                            ]),
                    ])
                    ->compact()
                    ->columnSpanFull(),

                Section::make('Plan de afiliación')
                    ->description('Plan, rango de edad, cobertura y tarifa según la afiliación corporativa.')
                    ->icon(Heroicon::ClipboardDocumentCheck)
                    ->schema([
                        Fieldset::make('Cobertura y pago')
                            ->schema([
                                Select::make('plan_id')
                                    ->options(fn () => Plan::query()->orderBy('description')->pluck('description', 'id'))
                                    ->label('Plan')
                                    ->required()
                                    ->live()
                                    ->validationMessages([
                                        'required' => 'Campo obligatorio',
                                    ])
                                    ->preload()
                                    ->searchable()
                                    ->placeholder('Seleccione un plan'),
                                Select::make('age_range_id')
                                    ->label('Rango de edad')
                                    ->options(function (Get $get): array {
                                        $planId = $get('plan_id');
                                        if (blank($planId)) {
                                            return [];
                                        }

                                        return AgeRange::query()
                                            ->where('plan_id', (int) $planId)
                                            ->orderBy('range')
                                            ->pluck('range', 'id')
                                            ->all();
                                    })
                                    ->searchable()
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Campo obligatorio',
                                    ])
                                    ->prefixIcon(Heroicon::ChartBar)
                                    ->preload(),
                                Select::make('coverage_id')
                                    ->label('Cobertura')
                                    ->options(function (Get $get): array {
                                        if (blank($get('age_range_id')) || (int) $get('age_range_id') === 1) {
                                            return [];
                                        }
                                        $ageRange = AgeRange::query()
                                            ->where('plan_id', $get('plan_id'))
                                            ->where('id', $get('age_range_id'))
                                            ->with('fees')
                                            ->first();

                                        if (! $ageRange || $ageRange->fees->isEmpty()) {
                                            return [];
                                        }

                                        return $ageRange->fees->pluck('coverage', 'coverage_id')->all();
                                    })
                                    ->searchable()
                                    ->prefixIcon(Heroicon::ShieldCheck)
                                    ->preload(),
                                TextInput::make('fee')
                                    ->label('Tarifa anual')
                                    ->live(onBlur: true)
                                    ->required()
                                    ->numeric()
                                    ->prefix('US$')
                                    ->validationMessages([
                                        'required' => 'Campo obligatorio',
                                    ])
                                    ->prefixIcon(Heroicon::CurrencyDollar),
                                TextInput::make('payment_frequency')
                                    ->label('Frecuencia de pago')
                                    ->live()
                                    ->prefixIcon(Heroicon::CalendarDays)
                                    ->disabled()
                                    ->dehydrated()
                                    ->default(fn () => $this->getOwnerRecord()->payment_frequency),
                                Hidden::make('created_by')->default(Auth::user()->name),
                            ]),
                    ])
                    ->compact()
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Afiliados corporativos')
            ->description('Empleados o familiares vinculados a esta afiliación. Use la búsqueda y el gestor de columnas para ajustar la vista.')
            ->recordTitleAttribute('first_name')
            ->columns([
                TextColumn::make('plan.description')
                    ->label('Plan')
                    ->weight(FontWeight::SemiBold)
                    ->icon(Heroicon::ClipboardDocumentList)
                    ->badge()
                    ->color('success')
                    ->searchable(),
                TextColumn::make('coverage.price')
                    ->label('Cobertura')
                    ->numeric(decimalPlaces: 2)
                    ->suffix(' US$')
                    ->icon(Heroicon::Banknotes),
                TextColumn::make('fee')
                    ->label('Tarifa anual')
                    ->numeric(decimalPlaces: 2)
                    ->suffix(' US$')
                    ->icon(Heroicon::CurrencyDollar),
                TextColumn::make('first_name')
                    ->label('Nombre')
                    ->searchable()
                    ->weight(FontWeight::Medium),
                TextColumn::make('last_name')
                    ->label('Apellido')
                    ->searchable(),
                TextColumn::make('birth_date')
                    ->label('Fecha de nacimiento')
                    ->formatStateUsing(fn (mixed $state): ?string => FilamentDateDisplay::toDmy($state))
                    ->searchable(),
                TextColumn::make('nro_identificacion')
                    ->label('C.I.')
                    ->copyable()
                    ->copyMessage('Copiado'),
                TextColumn::make('email')
                    ->label('Correo')
                    ->icon(Heroicon::Envelope)
                    ->copyable()
                    ->limit(28)
                    ->tooltip(fn (AffiliateCorporate $record): ?string => strlen((string) $record->email) > 28 ? $record->email : null),
                TextColumn::make('age')
                    ->label('Edad')
                    ->searchable()
                    ->alignCenter(),
                TextColumn::make('sex')
                    ->label('Sexo')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->icon(Heroicon::Phone)
                    ->copyable(),
                TextColumn::make('condition_medical')
                    ->label('Condición médica')
                    ->wrap()
                    ->limit(40)
                    ->tooltip(fn (AffiliateCorporate $record): ?string => strlen((string) ($record->condition_medical ?? '')) > 40 ? $record->condition_medical : null),
                TextColumn::make('initial_date')
                    ->label('Ingreso empresa')
                    ->formatStateUsing(fn (mixed $state): ?string => FilamentDateDisplay::toDmy($state)),
                TextColumn::make('position_company')
                    ->label('Cargo'),
                TextColumn::make('address')
                    ->label('Dirección')
                    ->wrap()
                    ->limit(36)
                    ->tooltip(fn (AffiliateCorporate $record): ?string => strlen((string) ($record->address ?? '')) > 36 ? $record->address : null),
                TextColumn::make('full_name_emergency')
                    ->label('Emergencia (nombre)')
                    ->wrap(),
                TextColumn::make('phone_emergency')
                    ->label('Emergencia (tel.)')
                    ->copyable(),
                TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->color(function (string $state): string {
                        return match ($state) {
                            'PRE-AFILIADO' => 'info',
                            'ACTIVO' => 'success',
                            'EXCLUIDO' => 'danger',
                            'INACTIVO' => 'danger',
                            default => 'gray',
                        };
                    }),
                TextColumn::make('vaucherIls')
                    ->label('Voucher ILS')
                    ->badge()
                    ->color('warning')
                    ->searchable()
                    ->default(fn ($record) => $record->vaucherIls == null ? '—' : $record->vaucherIls),
                TextColumn::make('dateInit')
                    ->label('Inicio ILS')
                    ->badge()
                    ->color('warning')
                    ->searchable()
                    ->default(fn ($record) => $record->dateInit == null ? '—' : $record->dateInit),
                TextColumn::make('dateEnd')
                    ->label('Fin ILS')
                    ->badge()
                    ->color('warning')
                    ->searchable()
                    ->default(fn ($record) => $record->DateEnd == null ? '—' : $record->DateEnd),
                TextColumn::make('numberDays')
                    ->label('Días cobertura')
                    ->suffix(' días')
                    ->badge()
                    ->color('warning')
                    ->searchable()
                    ->default(fn ($record) => $record->numberDays == null ? '0' : (string) abs((int) $record->numberDays)),
                IconColumn::make('document_ils')
                    ->alignment(Alignment::Center)
                    ->label('Comprobante')
                    ->icon(fn (AffiliateCorporate $record): string => $record->document_ils != null
                        ? 'heroicon-o-check-circle'
                        : 'heroicon-o-x-circle')
                    ->color(fn (AffiliateCorporate $record): string => $record->document_ils != null
                        ? 'success'
                        : 'danger')
                    ->url(fn (AffiliateCorporate $record): ?string => $record->document_ils
                        ? asset('storage/'.$record->document_ils)
                        : null)
                    ->openUrlInNewTab(),

            ])
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([10, 25, 50])
            ->headerActions([
                CreateAction::make()
                    ->label('Crear afiliado')
                    ->color('success')
                    ->createAnother(false)
                    ->icon(Heroicon::Plus)
                    ->modalWidth(Width::SevenExtraLarge)
                    ->modalHeading('Nuevo afiliado corporativo')
                    ->modalDescription('Complete los datos del familiar o colaborador y el plan. Los campos marcados como obligatorios deben completarse antes de guardar.')
                    ->before(function (array $data, CreateAction $action) {
                        $plans = $this->getOwnerRecord()->affiliationCorporatePlans->pluck('plan_id')->toArray();
                        if (! in_array($data['plan_id'], $plans)) {
                            Notification::make()
                                ->title('Plan no permitido')
                                ->danger()
                                ->icon(Heroicon::ExclamationCircle)
                                ->body('El plan seleccionado no está en la lista de planes de esta afiliación corporativa. Elija un plan autorizado.')
                                ->send();

                            $action->halt();
                        }
                    })
                    ->using(function (array $data) {

                        $addAffiliate = AffiliateCorporateController::addAffiliate($data, $this->getOwnerRecord());

                        if ($addAffiliate) {
                            Notification::make()
                                ->title('Afiliado creado')
                                ->success()
                                ->icon(Heroicon::CheckCircle)
                                ->body('El afiliado se registró correctamente.')
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Error al crear')
                                ->danger()
                                ->icon(Heroicon::ExclamationCircle)
                                ->body('No se pudo crear el afiliado. Intente de nuevo o contacte a soporte.')
                                ->send();
                        }
                    })
                    ->hidden(fn () => ! in_array('SUPERADMIN', Auth::user()?->departament ?? [])),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()
                        ->label('Editar')
                        ->color('warning')
                        ->icon(Heroicon::PencilSquare)
                        ->modalWidth(Width::SevenExtraLarge)
                        ->modalHeading('Editar afiliado')
                        ->modalDescription('Actualice los datos del afiliado. No se ocultan campos: revise cada sección.'),
                    Action::make('upload_info_ils')
                        ->label('Voucher ILS')
                        ->color('info')
                        ->icon(Heroicon::Ticket)
                        ->requiresConfirmation()
                        ->modalWidth(Width::TwoExtraLarge)
                        ->modalHeading('Activar cobertura ILS')
                        ->modalDescription('Ingrese voucher, vigencia y adjunte el comprobante. Campos obligatorios marcados con validación.')
                        ->form([
                            Section::make('Datos del voucher')
                                ->description('Vigencia del beneficio ILS y documento de respaldo.')
                                ->icon(Heroicon::Ticket)
                                ->schema([
                                    Grid::make(2)->schema([
                                        TextInput::make('vaucherIls')
                                            ->label('Voucher ILS')
                                            ->required(),
                                    ]),
                                    Grid::make(3)->schema([
                                        DatePicker::make('dateInit')
                                            ->label('Desde')
                                            ->format('d/m/Y')
                                            ->displayFormat('d/m/Y')
                                            ->required(),
                                        DatePicker::make('dateEnd')
                                            ->label('Hasta')
                                            ->live()
                                            ->format('d/m/Y')
                                            ->displayFormat('d/m/Y')
                                            ->afterStateUpdated(function (Set $set, $state, Get $get): void {
                                                $init = $get('dateInit');
                                                if (blank($init) || blank($state)) {
                                                    return;
                                                }
                                                try {
                                                    $fecha1 = Carbon::createFromFormat('d/m/Y', $init);
                                                    $fecha2 = Carbon::createFromFormat('d/m/Y', $state);
                                                    $set('numberDays', abs($fecha2->diffInDays($fecha1)));
                                                } catch (\Throwable) {
                                                }
                                            })
                                            ->required(),
                                        TextInput::make('numberDays')
                                            ->label('Días de vigencia')
                                            ->disabled()
                                            ->dehydrated()
                                            ->required(),

                                    ]),
                                    Grid::make(1)->schema([
                                        FileUpload::make('document_ils')
                                            ->label('Documento / comprobante ILS')
                                            ->required()
                                            ->downloadable()
                                            ->openable(),
                                    ]),
                                ]),
                        ])
                        ->action(function (AffiliateCorporate $record, array $data): void {

                            try {

                                $fecha1 = Carbon::createFromFormat('d/m/Y', $data['dateInit']);
                                $fecha2 = Carbon::createFromFormat('d/m/Y', $data['dateEnd']);

                                $record->update([
                                    'vaucherIls' => $data['vaucherIls'],
                                    'dateInit' => $data['dateInit'],
                                    'dateEnd' => $data['dateEnd'],
                                    'numberDays' => abs($fecha2->diffInDays($fecha1)),
                                    'document_ils' => $data['document_ils'],
                                ]);

                                Notification::make()
                                    ->success()
                                    ->title('Voucher ILS activado')
                                    ->send();
                            } catch (\Throwable $th) {
                                Log::error($th->getMessage());
                                Notification::make()
                                    ->danger()
                                    ->title('Error')
                                    ->body('No se pudo activar el voucher ILS. Intente de nuevo.')
                                    ->send();
                            }
                        })
                        ->hidden(function (AffiliateCorporate $record): bool {
                            if ($record->vaucherIls != null) {
                                return true;
                            }

                            return false;
                        }),
                    Action::make('changet_status')
                        ->label('Dar de baja')
                        ->icon(Heroicon::Trash)
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Dar de baja al afiliado')
                        ->modalDescription('Esta acción cambia el estado del afiliado según las reglas del sistema.')
                        ->action(function (AffiliateCorporate $record): void {
                            try {
                                AffiliateCorporateController::clearAffiliate($record, $this->getOwnerRecord());
                                Notification::make()
                                    ->success()
                                    ->title('Afiliado dado de baja')
                                    ->body('Se actualizaron montos, población del plan y totales de la afiliación.')
                                    ->send();
                            } catch (\Throwable $th) {
                                Log::error($th);
                                Notification::make()
                                    ->danger()
                                    ->title('Error')
                                    ->body($th->getMessage())
                                    ->send();
                            }
                        }),
                ])->hidden(fn ($record) => $record->status == 'INACTIVO' || $record->status == 'EXCLUIDO' || Auth::user()->is_business_admin != 1),

            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('asigned_vaucher_ils')
                        ->modalHeading('Asignar voucher ILS')
                        ->modalDescription('Se aplicará la misma información a todos los registros seleccionados.')
                        ->requiresConfirmation()
                        ->modalWidth(Width::TwoExtraLarge)
                        ->color('info')
                        ->icon(Heroicon::Ticket)
                        ->form([
                            Section::make('Datos del voucher')
                                ->description('Vigencia y comprobante para los afiliados seleccionados.')
                                ->icon(Heroicon::Ticket)
                                ->schema([
                                    Grid::make(2)->schema([
                                        TextInput::make('vaucherIls')
                                            ->label('Voucher ILS')
                                            ->required(),
                                    ]),
                                    Grid::make(3)->schema([
                                        DatePicker::make('dateInit')
                                            ->label('Desde')
                                            ->format('d/m/Y')
                                            ->displayFormat('d/m/Y')
                                            ->required(),
                                        DatePicker::make('dateEnd')
                                            ->label('Hasta')
                                            ->live()
                                            ->format('d/m/Y')
                                            ->displayFormat('d/m/Y')
                                            ->afterStateUpdated(function (Set $set, $state, Get $get): void {
                                                $init = $get('dateInit');
                                                if (blank($init) || blank($state)) {
                                                    return;
                                                }
                                                try {
                                                    $fecha1 = Carbon::createFromFormat('d/m/Y', $init);
                                                    $fecha2 = Carbon::createFromFormat('d/m/Y', $state);
                                                    $set('numberDays', abs($fecha2->diffInDays($fecha1)));
                                                } catch (\Throwable) {
                                                }
                                            })
                                            ->required(),
                                        TextInput::make('numberDays')
                                            ->label('Días de vigencia')
                                            ->disabled()
                                            ->dehydrated()
                                            ->required(),

                                    ]),
                                    Grid::make(1)->schema([
                                        FileUpload::make('document_ils')
                                            ->label('Documento / comprobante ILS')
                                            ->required()
                                            ->downloadable()
                                            ->openable(),
                                    ]),
                                ]),
                        ])
                        ->action(function (Collection $records, array $data): void {

                            try {

                                foreach ($records as $record) {

                                    $fecha1 = Carbon::createFromFormat('d/m/Y', $data['dateInit']);
                                    $fecha2 = Carbon::createFromFormat('d/m/Y', $data['dateEnd']);

                                    $record->update([
                                        'vaucherIls' => $data['vaucherIls'],
                                        'dateInit' => $data['dateInit'],
                                        'dateEnd' => $data['dateEnd'],
                                        'numberDays' => abs($fecha2->diffInDays($fecha1)),
                                        'document_ils' => $data['document_ils'],
                                    ]);
                                }

                                Notification::make()
                                    ->success()
                                    ->title('Voucher ILS asignado')
                                    ->send();
                            } catch (\Throwable $th) {
                                Log::error($th->getMessage());
                                Notification::make()
                                    ->danger()
                                    ->title('Error')
                                    ->body('No se pudo asignar el voucher ILS.')
                                    ->send();
                            }
                        }),
                    BulkAction::make('reassign_plan')
                        ->label('Reasignar plan')
                        ->color('info')
                        ->icon(Heroicon::ArrowPath)
                        ->requiresConfirmation()
                        ->modalWidth(Width::TwoExtraLarge)
                        ->modalHeading('Reasignar plan')
                        ->modalDescription('Seleccione el plan a reasignar al afiliado.')
                        ->form([
                            Select::make('plan_id')
                                ->label('Plan')
                                ->live()
                                ->options(function () {
                                    // Log::info($this->getOwnerRecord()->affiliationCorporatePlans);
                                    $plans = $this->getOwnerRecord()->affiliationCorporatePlans->pluck('plan_id')->toArray();
                                    Log::info($plans);

                                    return Plan::query()->whereIn('id', $plans)->orderBy('description')->pluck('description', 'id');
                                })
                                ->required(),
                            Select::make('age_range_id')
                                ->label('Rango de edad')
                                ->live()
                                ->options(function (Get $get) {
                                    $planId = $get('plan_id');

                                    return AgeRange::query()->where('plan_id', $planId)->orderBy('range')->pluck('range', 'id');
                                })
                                ->required()
                                ->prefixIcon(Heroicon::ChartBar),
                        ])
                        ->action(function (Collection $records, array $data): void {

                            $plans = AfilliationCorporatePlan::where('affiliation_corporate_id', $this->getOwnerRecord()->id)
                                ->where('plan_id', $data['plan_id'])
                                ->where('age_range_id', $data['age_range_id'])
                                ->first();
                            dd($plans);

                            // 1. En la tabla de rango de edades busco los valores enteros del rango de edad seleccionado
                            $ageRange = AgeRange::query()->where('id', $data['age_range_id'])->first();

                            if (! $ageRange) {
                                Notification::make()
                                    ->danger()
                                    ->title('Error')
                                    ->body('No se encontró el rango de edad seleccionado.')
                                    ->send();

                                return;
                            }

                            for ($i = 0; $i < count($records); $i++) {
                                // 2. Comparo el rango de edad del afiliado con el rango de edad seleccionado
                                if ($records[$i]->age >= $ageRange->age_init && $records[$i]->age <= $ageRange->age_end) {
                                    $records[$i]->update([
                                        'plan_id' => $data['plan_id'],
                                        'coverage_id' => $plans->coverage_id,
                                        'fee' => $plans->fee,
                                    ]);
                                }
                            }

                            Notification::make()
                                ->success()
                                ->title('Plan reasignado')
                                ->body('Se reasignó el plan a los afiliados seleccionados.')
                                ->send();

                        }),
                    DeleteBulkAction::make()
                        ->modalHeading('Eliminar registros seleccionados')
                        ->modalDescription('Esta acción no se puede deshacer.')
                        ->requiresConfirmation()
                        ->color('danger')
                        ->icon(Heroicon::Trash),

                ]),
            ])
            ->striped()
            ->poll('5s');
    }
}
