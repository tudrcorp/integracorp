<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\AffiliationCorporates\RelationManagers;

use App\Http\Controllers\AffiliateCorporateController;
use App\Models\AffiliateCorporate;
use App\Models\AffiliationCorporate;
use App\Models\AfilliationCorporatePlan;
use App\Models\AgeRange;
use App\Models\BusinessLine;
use App\Models\BusinessUnit;
use App\Models\Plan;
use App\Support\AffiliateVaucherIlsRemainingDays;
use App\Support\AffiliationCorporates\CorporateAffiliatePlanSynchronizer;
use App\Support\AffiliationCorporates\CorporateAffiliateRelationship;
use App\Support\AffiliationCorporates\CorporateAffiliateVoucherIlsUpdater;
use App\Support\Filament\BusinessFilamentActionAccess;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
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
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColumnGroup;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
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
                    ->description('Identificación, parentesco y datos básicos del familiar o colaborador.')
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
                                    ->columnSpan(['default' => 12, 'lg' => 6]),
                                Select::make('relationship')
                                    ->label('Parentesco')
                                    ->required()
                                    ->native(false)
                                    ->options(CorporateAffiliateRelationship::options())
                                    ->default(CorporateAffiliateRelationship::DEFAULT)
                                    ->placeholder('Seleccione el parentesco')
                                    ->validationMessages([
                                        'required' => 'Campo obligatorio',
                                    ])
                                    ->columnSpan(['default' => 12, 'lg' => 6]),
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
            ->description('Empleados o familiares vinculados a esta afiliación. La unidad y línea en verde coinciden con la afiliación; en ámbar están pendientes de sincronizar.')
            ->recordTitleAttribute('first_name')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with([
                    'plan',
                    'coverage',
                    'businessLine:id,definition',
                    'businessUnit:id,definition',
                ])
                ->orderBy('last_name')
                ->orderBy('first_name'))
            ->emptyStateHeading('Sin afiliados corporativos')
            ->emptyStateDescription('Agregue un afiliado con el botón superior o importe la población desde la cotización.')
            ->emptyStateIcon(Heroicon::UserGroup)
            ->striped()
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([10, 25, 50])
            ->columns([
                TextColumn::make('status')
                    ->label('Estatus')
                    ->icon(Heroicon::Signal)
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'PRE-AFILIADO' => 'info',
                        'ACTIVO' => 'success',
                        'EXCLUIDO', 'INACTIVO' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('first_name')
                    ->label('Nombre')
                    ->icon(Heroicon::User)
                    ->weight(FontWeight::SemiBold)
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->extraCellAttributes(['class' => 'min-w-32']),
                TextColumn::make('last_name')
                    ->label('Apellido')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('nro_identificacion')
                    ->label('C.I.')
                    ->icon(Heroicon::Identification)
                    ->fontFamily(FontFamily::Mono)
                    ->searchable()
                    ->copyable()
                    ->copyMessage('C.I. copiada'),
                TextColumn::make('age')
                    ->label('Edad')
                    ->alignCenter()
                    ->sortable(),
                TextColumn::make('sex')
                    ->label('Sexo')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),
                TextColumn::make('relationship')
                    ->label('Parentesco')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (?string $state): ?string => CorporateAffiliateRelationship::label($state))
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('plan.description')
                    ->label('Plan')
                    ->weight(FontWeight::SemiBold)
                    ->icon(Heroicon::ClipboardDocumentList)
                    ->badge()
                    ->color('success')
                    ->searchable()
                    ->limit(24)
                    ->tooltip(fn (AffiliateCorporate $record): ?string => strlen((string) ($record->plan?->description ?? '')) > 24
                        ? $record->plan?->description
                        : null),
                TextColumn::make('business_unit_id')
                    ->label('Unidad de negocio')
                    ->icon(Heroicon::BuildingOffice2)
                    ->formatStateUsing(fn (AffiliateCorporate $record): string => filled($record->businessUnit?->definition)
                        ? (string) $record->businessUnit->definition
                        : '—')
                    ->description(fn (AffiliateCorporate $record): ?string => filled($record->business_unit_id)
                        ? 'ID: '.$record->business_unit_id
                        : 'Sin asignar')
                    ->badge()
                    ->color(fn (AffiliateCorporate $record): string => $this->businessContextBadgeColor(
                        $record->business_unit_id,
                        $this->getOwnerRecord()->business_unit_id,
                    ))
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('businessUnit', fn (Builder $unitQuery): Builder => $unitQuery->where('definition', 'like', "%{$search}%"));
                    })
                    ->sortable(),
                TextColumn::make('business_line_id')
                    ->label('Línea de servicio')
                    ->icon(Heroicon::QueueList)
                    ->formatStateUsing(fn (AffiliateCorporate $record): string => filled($record->businessLine?->definition)
                        ? (string) $record->businessLine->definition
                        : '—')
                    ->description(fn (AffiliateCorporate $record): ?string => filled($record->business_line_id)
                        ? 'ID: '.$record->business_line_id
                        : 'Sin asignar')
                    ->badge()
                    ->color(fn (AffiliateCorporate $record): string => $this->businessContextBadgeColor(
                        $record->business_line_id,
                        $this->getOwnerRecord()->business_line_id,
                    ))
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('businessLine', fn (Builder $lineQuery): Builder => $lineQuery->where('definition', 'like', "%{$search}%"));
                    })
                    ->sortable(),
                IconColumn::make('sync_status')
                    ->label('Sync')
                    ->alignment(Alignment::Center)
                    ->getStateUsing(fn (AffiliateCorporate $record): bool => $this->affiliateIsFullySynced($record))
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-exclamation-triangle')
                    ->trueColor('success')
                    ->falseColor('warning')
                    ->tooltip(fn (AffiliateCorporate $record): string => $this->syncPendingSummary($record)),
                TextColumn::make('coverage.price')
                    ->label('Cobertura')
                    ->numeric(decimalPlaces: 2)
                    ->suffix(' US$')
                    ->icon(Heroicon::Banknotes)
                    ->toggleable(),
                TextColumn::make('fee')
                    ->label('Tarifa anual')
                    ->numeric(decimalPlaces: 2)
                    ->suffix(' US$')
                    ->icon(Heroicon::CurrencyDollar)
                    ->sortable(),
                ColumnGroup::make('Voucher ILS', [
                    TextColumn::make('vaucherIls')
                        ->label('Código')
                        ->icon(Heroicon::Ticket)
                        ->badge()
                        ->color('info')
                        ->placeholder('—')
                        ->searchable()
                        ->sortable(),
                    TextColumn::make('ils_status')
                        ->label('Estado ILS')
                        ->badge()
                        ->state(fn (AffiliateCorporate $record): string => $this->affiliateHasVoucherIls($record) ? 'Cargado' : 'Pendiente')
                        ->color(fn (AffiliateCorporate $record): string => $this->affiliateHasVoucherIls($record) ? 'success' : 'warning'),
                    TextColumn::make('dateInit')
                        ->label('Desde')
                        ->formatStateUsing(fn (mixed $state): ?string => FilamentDateDisplay::toDmy($state))
                        ->placeholder('—')
                        ->toggleable(),
                    TextColumn::make('dateEnd')
                        ->label('Hasta')
                        ->formatStateUsing(fn (mixed $state): ?string => FilamentDateDisplay::toDmy($state))
                        ->placeholder('—')
                        ->toggleable(),
                    TextColumn::make('numberDays')
                        ->label('Días restantes')
                        ->suffix(' días')
                        ->badge()
                        ->color('warning')
                        ->getStateUsing(function (AffiliateCorporate $record): string {
                            if ($record->dateEnd === null) {
                                return '—';
                            }

                            $days = AffiliateVaucherIlsRemainingDays::remainingDaysUntilEnd($record->dateEnd);

                            return $days === null ? '—' : (string) $days;
                        })
                        ->toggleable(),
                    TextColumn::make('vigencia_ils')
                        ->label('Vigencia total')
                        ->suffix(' días')
                        ->badge()
                        ->color('gray')
                        ->state(fn (AffiliateCorporate $record): string => filled($record->numberDays) ? (string) $record->numberDays : '—')
                        ->toggleable(isToggledHiddenByDefault: true),
                    IconColumn::make('has_document_ils')
                        ->alignment(Alignment::Center)
                        ->label('Comprobante')
                        ->getStateUsing(fn (AffiliateCorporate $record): bool => filled($record->document_ils))
                        ->boolean()
                        ->trueIcon('heroicon-o-check-circle')
                        ->falseIcon('heroicon-o-x-circle')
                        ->trueColor('success')
                        ->falseColor('danger')
                        ->url(fn (AffiliateCorporate $record): ?string => filled($record->document_ils)
                            ? asset('storage/'.$record->document_ils)
                            : null)
                        ->openUrlInNewTab()
                        ->toggleable(),
                    ImageColumn::make('document_ils')
                        ->label('Voucher')
                        ->disk('public')
                        ->square()
                        ->toggleable(isToggledHiddenByDefault: true),
                ]),
                TextColumn::make('birth_date')
                    ->label('Nacimiento')
                    ->icon(Heroicon::CalendarDays)
                    ->formatStateUsing(fn (mixed $state): ?string => FilamentDateDisplay::toDmy($state))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('email')
                    ->label('Correo')
                    ->icon(Heroicon::Envelope)
                    ->copyable()
                    ->limit(28)
                    ->tooltip(fn (AffiliateCorporate $record): ?string => strlen((string) ($record->email ?? '')) > 28 ? $record->email : null)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->icon(Heroicon::Phone)
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('condition_medical')
                    ->label('Condición médica')
                    ->wrap()
                    ->limit(40)
                    ->tooltip(fn (AffiliateCorporate $record): ?string => strlen((string) ($record->condition_medical ?? '')) > 40 ? $record->condition_medical : null)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('initial_date')
                    ->label('Ingreso empresa')
                    ->formatStateUsing(fn (mixed $state): ?string => FilamentDateDisplay::toDmy($state))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('position_company')
                    ->label('Cargo')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('address')
                    ->label('Dirección')
                    ->icon(Heroicon::MapPin)
                    ->wrap()
                    ->lineClamp(2)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('full_name_emergency')
                    ->label('Emergencia (nombre)')
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('phone_emergency')
                    ->label('Emergencia (tel.)')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estatus')
                    ->options([
                        'PRE-AFILIADO' => 'Pre-afiliado',
                        'ACTIVO' => 'Activo',
                        'INACTIVO' => 'Inactivo',
                        'EXCLUIDO' => 'Excluido',
                    ])
                    ->native(false),
                TernaryFilter::make('has_voucher_ils')
                    ->label('Voucher ILS')
                    ->placeholder('Todos')
                    ->trueLabel('Cargado')
                    ->falseLabel('Pendiente')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->where(function (Builder $query): void {
                            $query->whereNotNull('vaucherIls')
                                ->orWhereNotNull('document_ils');
                        }),
                        false: fn (Builder $query): Builder => $query->where(function (Builder $query): void {
                            $query->whereNull('vaucherIls')
                                ->whereNull('document_ils');
                        }),
                    ),
                SelectFilter::make('business_unit_id')
                    ->label('Unidad de negocio')
                    ->options(fn (): array => BusinessUnit::query()->orderBy('definition')->pluck('definition', 'id')->all())
                    ->searchable()
                    ->preload()
                    ->native(false),
                SelectFilter::make('business_line_id')
                    ->label('Línea de servicio')
                    ->options(fn (): array => BusinessLine::query()->orderBy('definition')->pluck('definition', 'id')->all())
                    ->searchable()
                    ->preload()
                    ->native(false),
                Filter::make('business_context_sync')
                    ->label('Sincronización')
                    ->form([
                        Select::make('value')
                            ->label('Estado')
                            ->options([
                                'synced' => 'Unidad y línea sincronizadas',
                                'pending' => 'Pendiente de sincronizar',
                            ])
                            ->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if (blank($value)) {
                            return $query;
                        }

                        $owner = $this->getOwnerRecord()->fresh();

                        return match ($value) {
                            'synced' => $query
                                ->where('business_unit_id', $owner?->business_unit_id)
                                ->where('business_line_id', $owner?->business_line_id),
                            'pending' => $query->where(function (Builder $pendingQuery) use ($owner): void {
                                $pendingQuery
                                    ->whereNull('business_unit_id')
                                    ->orWhereNull('business_line_id')
                                    ->orWhere('business_unit_id', '!=', $owner?->business_unit_id)
                                    ->orWhere('business_line_id', '!=', $owner?->business_line_id);
                            }),
                            default => $query,
                        };
                    })
                    ->indicateUsing(function (array $data): array {
                        return match ($data['value'] ?? null) {
                            'synced' => ['synced' => 'Unidad y línea sincronizadas'],
                            'pending' => ['pending' => 'Unidad o línea pendientes'],
                            default => [],
                        };
                    }),
            ])
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
                    ->visible(fn (): bool => BusinessFilamentActionAccess::userCan(
                        BusinessFilamentActionPermissionRegistry::CREATE_CORPORATE_AFFILIATE,
                    )),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('sync_with_affiliation')
                        ->label('Sincronizar con la afiliación')
                        ->icon(Heroicon::ArrowPathRoundedSquare)
                        ->color('info')
                        ->requiresConfirmation()
                        ->modalWidth(Width::TwoExtraLarge)
                        ->modalIcon(Heroicon::ArrowPathRoundedSquare)
                        ->modalHeading('Sincronizar afiliado con la afiliación')
                        ->modalDescription(fn (AffiliateCorporate $record): string => self::syncPreviewMessage($this->getOwnerRecord(), $record))
                        ->modalSubmitActionLabel('Sincronizar')
                        ->action(function (AffiliateCorporate $record): void {
                            $this->runAffiliateSync([$record]);
                        }),
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
                                                $days = CorporateAffiliateVoucherIlsUpdater::calculateNumberDays($get('dateInit'), $state);
                                                $set('numberDays', $days ?? 0);
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
                                            ->disk('public')
                                            ->directory('vauches')
                                            ->required()
                                            ->downloadable()
                                            ->openable(),
                                    ]),
                                ]),
                        ])
                        ->action(function (AffiliateCorporate $record, array $data): void {

                            try {
                                CorporateAffiliateVoucherIlsUpdater::save($record, $data);

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
                                                $days = CorporateAffiliateVoucherIlsUpdater::calculateNumberDays($get('dateInit'), $state);
                                                $set('numberDays', $days ?? 0);
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
                                            ->disk('public')
                                            ->directory('vauches')
                                            ->required()
                                            ->downloadable()
                                            ->openable(),
                                    ]),
                                ]),
                        ])
                        ->action(function (Collection $records, array $data): void {

                            try {
                                foreach ($records as $record) {
                                    CorporateAffiliateVoucherIlsUpdater::save($record, $data);
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
                    BulkAction::make('sync_with_affiliation_bulk')
                        ->label('Sincronizar con la afiliación')
                        ->icon(Heroicon::ArrowPathRoundedSquare)
                        ->color('info')
                        ->requiresConfirmation()
                        ->modalWidth(Width::TwoExtraLarge)
                        ->modalIcon(Heroicon::ArrowPathRoundedSquare)
                        ->modalHeading('Sincronizar afiliados con la afiliación')
                        ->modalDescription('A cada afiliado se le asigna el plan contratado por la empresa según su edad, con la cobertura y la tarifa de ese rango, más la unidad de negocio y la línea de servicio de la afiliación. No cambia el estatus de nadie.')
                        ->modalSubmitActionLabel('Sincronizar')
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records): void {
                            $this->runAffiliateSync($records);
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

                            if (! $plans instanceof AfilliationCorporatePlan) {
                                Notification::make()
                                    ->danger()
                                    ->title('Error')
                                    ->body('La afiliación no tiene contratado ese plan para el rango de edad seleccionado.')
                                    ->send();

                                return;
                            }

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

                            $reassigned = 0;
                            $outOfRange = [];

                            foreach ($records as $record) {
                                // 2. Comparo el rango de edad del afiliado con el rango de edad seleccionado
                                if ((int) $record->age >= (int) $ageRange->age_init && (int) $record->age <= (int) $ageRange->age_end) {
                                    $record->update([
                                        'plan_id' => $data['plan_id'],
                                        'coverage_id' => $plans->coverage_id,
                                        'fee' => $plans->fee,
                                    ]);
                                    $reassigned++;

                                    continue;
                                }

                                $outOfRange[] = CorporateAffiliatePlanSynchronizer::labelFor($record);
                            }

                            $body = $reassigned.' '.($reassigned === 1 ? 'afiliado reasignado' : 'afiliados reasignados').'.';

                            if ($outOfRange !== []) {
                                $body .= ' Fuera del rango de edad seleccionado: '.implode(', ', $outOfRange).'.';
                            }

                            Notification::make()
                                ->{$outOfRange === [] ? 'success' : 'warning'}()
                                ->title('Plan reasignado')
                                ->body($body)
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
            ->poll('5s');
    }

    private function affiliateHasVoucherIls(AffiliateCorporate $record): bool
    {
        return filled($record->vaucherIls) || filled($record->document_ils);
    }

    /**
     * @param  iterable<int, AffiliateCorporate>  $affiliates
     */
    private function runAffiliateSync(iterable $affiliates): void
    {
        $owner = $this->getOwnerRecord();

        try {
            $result = CorporateAffiliatePlanSynchronizer::sync($owner, $affiliates);
        } catch (\Throwable $throwable) {
            Log::error('NEGOCIOS-AFILIACIONES-CORPORATIVAS: Error al sincronizar afiliados con la afiliación.', [
                'affiliation_corporate_id' => $owner->getKey(),
                'error' => $throwable->getMessage(),
            ]);

            Notification::make()
                ->danger()
                ->title('No se pudo sincronizar')
                ->body($throwable->getMessage())
                ->send();

            return;
        }

        $body = [];

        if ($result['updated'] > 0) {
            $body[] = $result['updated'].' '.($result['updated'] === 1 ? 'afiliado actualizado' : 'afiliados actualizados').'.';
        }

        if ($result['unchanged'] > 0) {
            $body[] = $result['unchanged'].' ya '.($result['unchanged'] === 1 ? 'estaba' : 'estaban').' al día.';
        }

        foreach ($result['skipped'] as $skipped) {
            $body[] = $skipped['name'].': '.CorporateAffiliatePlanSynchronizer::reasonLabel($skipped['reason']).'.';
        }

        $notification = Notification::make()
            ->title($result['updated'] > 0 ? 'Afiliados sincronizados' : 'Sin cambios que aplicar')
            ->body(implode(' ', $body) !== '' ? implode(' ', $body) : 'No hubo afiliados que sincronizar.');

        $result['skipped'] === [] && $result['updated'] > 0
            ? $notification->success()
            : ($result['skipped'] !== [] ? $notification->warning() : $notification->info());

        $notification->send();
    }

    private static function syncPreviewMessage(AffiliationCorporate $owner, AffiliateCorporate $record): string
    {
        $resolution = CorporateAffiliatePlanSynchronizer::resolvePlanRowForAffiliate($owner, $record);
        $planRow = $resolution['row'];

        if ($planRow === null) {
            return 'No se puede sincronizar a '.CorporateAffiliatePlanSynchronizer::labelFor($record).': '
                .CorporateAffiliatePlanSynchronizer::reasonLabel((string) $resolution['reason']).'.';
        }

        $planRow->loadMissing(['plan', 'ageRange', 'coverage']);

        $plan = (string) ($planRow->plan?->description ?? 'Plan de la afiliación');
        $rango = (string) ($planRow->ageRange?->range ?? '');
        $cobertura = $planRow->coverage?->price !== null
            ? number_format((float) $planRow->coverage->price, 2, ',', '.').' US$'
            : 'sin cobertura';

        return 'Se asignará el plan «'.$plan.'»'
            .($rango !== '' ? ' (rango '.$rango.' años)' : '')
            .', cobertura '.$cobertura
            .' y tarifa anual '.number_format((float) $planRow->fee, 2, ',', '.').' US$'
            .', junto con la unidad de negocio y la línea de servicio de la afiliación. El estatus del afiliado no cambia.';
    }

    private function affiliateIsFullySynced(AffiliateCorporate $record): bool
    {
        $owner = $this->getOwnerRecord()->fresh();

        if ($owner === null) {
            return false;
        }

        return CorporateAffiliatePlanSynchronizer::isSynced($owner, $record);
    }

    private function syncPendingSummary(AffiliateCorporate $record): string
    {
        $owner = $this->getOwnerRecord()->fresh();

        if ($owner === null) {
            return 'Pendiente de sincronizar con la afiliación';
        }

        $pending = [];

        if (! CorporateAffiliatePlanSynchronizer::businessContextIsSynced($owner, $record)) {
            $pending[] = 'unidad y línea';
        }

        $resolution = CorporateAffiliatePlanSynchronizer::resolvePlanRowForAffiliate($owner, $record);
        $planRow = $resolution['row'];

        if ($planRow === null) {
            $pending[] = CorporateAffiliatePlanSynchronizer::reasonLabel((string) $resolution['reason']);
        } elseif (! CorporateAffiliatePlanSynchronizer::isSynced($owner, $record, $planRow)) {
            if ((int) $record->plan_id !== (int) $planRow->plan_id) {
                $pending[] = 'plan';
            }

            if ((int) $record->coverage_id !== (int) $planRow->coverage_id) {
                $pending[] = 'cobertura';
            }

            if (abs((float) $record->fee - (float) $planRow->fee) >= 0.01) {
                $pending[] = 'tarifa';
            }
        }

        $pending = array_values(array_unique($pending));

        return $pending === []
            ? 'Plan, cobertura, tarifa, unidad y línea coinciden con la afiliación'
            : 'Pendiente de sincronizar: '.implode(', ', $pending);
    }

    private function affiliateBusinessContextIsSynced(AffiliateCorporate $record): bool
    {
        $owner = $this->getOwnerRecord()->fresh();

        if ($owner === null) {
            return false;
        }

        if (blank($owner->business_unit_id) || blank($owner->business_line_id)) {
            return blank($record->business_unit_id) && blank($record->business_line_id);
        }

        return (int) $record->business_unit_id === (int) $owner->business_unit_id
            && (int) $record->business_line_id === (int) $owner->business_line_id;
    }

    private function businessContextBadgeColor(mixed $affiliateValue, mixed $ownerValue): string
    {
        $owner = $this->getOwnerRecord()->fresh();

        if (blank($affiliateValue)) {
            return 'gray';
        }

        if ($owner === null || blank($ownerValue)) {
            return 'info';
        }

        return (int) $affiliateValue === (int) $ownerValue ? 'success' : 'warning';
    }
}
