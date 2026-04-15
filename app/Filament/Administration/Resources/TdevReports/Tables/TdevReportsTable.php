<?php

namespace App\Filament\Administration\Resources\TdevReports\Tables;

use App\Enums\FormaPago;
use App\Enums\StatusComision;
use App\Enums\StatusPago;
use App\Enums\StatusVaucher;
use App\Filament\Administration\Resources\TdevReports\Actions\TdevReportPaymentModalActions;
use App\Filament\Administration\Resources\TdevReports\Actions\TdevReportProcessNotesModalActions;
use App\Models\TdevReport;
use App\Services\TdevReports\TdevReportCommissionFromPercentageUpdater;
use App\Services\TdevReports\TdevReportVaucherStatusUpdater;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class TdevReportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Movimientos TDEV')
            ->description('Vista resumida por defecto. Use el selector de columnas para ver importes, comisiones y el resto de campos del CSV. La búsqueda prioriza voucher, pasajero, documento y agencia.')
            ->defaultSort('id', 'desc')
            ->defaultPaginationPageOption(25)
            ->paginated([10, 25, 50, 100])
            ->striped()
            ->deferLoading()
            ->emptyStateHeading('Aún no hay filas importadas')
            ->emptyStateDescription('Suba un archivo CSV con el botón «Importar reporte CSV» en la parte superior de la página.')
            ->emptyStateIcon(Heroicon::ArrowUpTray)
            ->columns([
                TextColumn::make('mes')
                    ->label('Mes')
                    ->icon('heroicon-o-calendar-days')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('fecha')
                    ->label('Fecha')
                    ->icon('heroicon-o-calendar-days')
                    ->formatStateUsing(function (?string $state): string {
                        if (blank($state)) {
                            return '—';
                        }
                        try {
                            return Carbon::parse($state)->format('d/m/Y');
                        } catch (\Throwable) {
                            return $state;
                        }
                    })
                    ->sortable()
                    ->searchable(),
                TextColumn::make('vaucher')
                    ->label('Voucher')
                    ->icon('heroicon-o-ticket')
                    ->weight(FontWeight::SemiBold)
                    ->fontFamily(FontFamily::Mono)
                    ->copyable()
                    ->copyMessage('Voucher copiado')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('agencia')
                    ->label('Agencia')
                    ->icon('heroicon-o-building-office')

                    ->sortable()
                    ->searchable(),
                TextColumn::make('agente_emisor')
                    ->label('Agente emisor')
                    ->icon('heroicon-o-user')

                    ->sortable()
                    ->searchable(),
                TextColumn::make('nivel')
                    ->label('Nivel')
                    ->icon('heroicon-o-chart-bar')

                    ->sortable()
                    ->searchable(),
                TextColumn::make('salida')
                    ->label('Salida')
                    ->icon('heroicon-o-arrow-right')

                    ->sortable()
                    ->searchable(),
                TextColumn::make('regreso')
                    ->label('Regreso')
                    ->icon('heroicon-o-arrow-left')

                    ->sortable()
                    ->searchable(),
                TextColumn::make('pasajero')
                    ->label('Pasajero')
                    ->icon('heroicon-o-user')

                    ->sortable()
                    ->searchable(),
                TextColumn::make('nro_documento')
                    ->label('Nro. Documento')
                    ->icon('heroicon-o-identification')

                    ->sortable()
                    ->searchable(),
                TextColumn::make('categoria_del_plan')
                    ->label('Categoría del plan')
                    ->icon('heroicon-o-cube')

                    ->sortable()
                    ->searchable(),
                TextColumn::make('descripcion_del_plan')
                    ->label('Descripción del plan')
                    ->icon('heroicon-o-document-text')

                    ->sortable()
                    ->searchable(),
                TextColumn::make('estatus_vaucher')
                    ->label('Estatus del voucher')
                    ->icon('heroicon-o-check-circle')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => StatusVaucher::labelFromMixed($state))
                    ->color(fn ($state): string => StatusVaucher::filamentColorFromMixed($state))
                    ->sortable()
                    ->searchable()
                    ->tooltip('Clic para cambiar el estatus del voucher')
                    ->action(
                        Action::make('actualizarEstatusVaucherTdev')
                            ->slideOver()
                            ->modalWidth(Width::ThreeExtraLarge)
                            ->modalHeading('Estatus del voucher')
                            ->modalDescription('Seleccione el nuevo estatus. Si elige Anulado, el estatus de pago y el de comisión pasan a Anulado y debe registrar una observación de proceso.')
                            ->icon('heroicon-o-shield-check')
                            ->modalSubmitActionLabel('Guardar')
                            ->modalSubmitAction(
                                fn (Action $action): Action => $action
                                    ->extraAttributes([
                                        'class' => TdevReportPaymentModalActions::IOS_SUCCESS_BTN,
                                    ])
                            )
                            ->modalCancelAction(
                                fn (Action $action): Action => $action
                                    ->label('Cancelar')
                                    ->extraAttributes([
                                        'class' => TdevReportPaymentModalActions::IOS_GRAY_BTN,
                                    ])
                            )
                            ->successNotification(null)
                            ->fillForm(fn (TdevReport $record): array => [
                                'estatus_vaucher' => $record->estatus_vaucher?->value,
                                'observacion_anulacion' => null,
                            ])
                            ->form([
                                Section::make('Estatus')
                                    ->description('El cambio queda registrado en el sistema.')
                                    ->icon('heroicon-m-flag')
                                    ->schema([
                                        Select::make('estatus_vaucher')
                                            ->label('Estatus')
                                            ->options(StatusVaucher::options())
                                            ->required()
                                            ->live()
                                            ->native(false)
                                            ->searchable(),
                                    ])
                                    ->columns(1)
                                    ->columnSpanFull()
                                    ->extraAttributes([
                                        'class' => TdevReportPaymentModalActions::IOS_SECTION_CLASS,
                                    ]),
                                Section::make('Observación por anulación')
                                    ->description('Obligatorio si marca el voucher como Anulado. Se añade al historial de observaciones de proceso con fecha y tu nombre.')
                                    ->icon('heroicon-m-pencil-square')
                                    ->schema([
                                        RichEditor::make('observacion_anulacion')
                                            ->label('Motivo / detalle')
                                            ->placeholder('Indique el motivo de la anulación…')
                                            ->fileAttachments(false)
                                            ->toolbarButtons([
                                                ['bold', 'italic', 'underline', 'strike', 'highlight', 'textColor'],
                                                ['h1', 'h2', 'h3'],
                                                ['alignStart', 'alignCenter', 'alignEnd'],
                                                ['bulletList', 'orderedList', 'blockquote'],
                                                ['link'],
                                                ['undo', 'redo'],
                                            ])
                                            ->extraInputAttributes([
                                                'class' => 'min-h-[12rem]',
                                            ])
                                            ->visible(fn (Get $get): bool => $get('estatus_vaucher') === StatusVaucher::Anulado->value)
                                            ->required(fn (Get $get): bool => $get('estatus_vaucher') === StatusVaucher::Anulado->value)
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(1)
                                    ->columnSpanFull()
                                    ->visible(fn (Get $get): bool => $get('estatus_vaucher') === StatusVaucher::Anulado->value)
                                    ->extraAttributes([
                                        'class' => TdevReportPaymentModalActions::IOS_SECTION_CLASS,
                                    ]),
                            ])
                            ->action(function (TdevReport $record, array $data): void {
                                if (Auth::user() === null) {
                                    Notification::make()
                                        ->title('Sesión requerida')
                                        ->body('Debe iniciar sesión para cambiar el estatus.')
                                        ->danger()
                                        ->send();

                                    return;
                                }

                                $raw = $data['estatus_vaucher'] ?? '';
                                $nuevo = StatusVaucher::tryFrom((string) $raw)
                                    ?? StatusVaucher::fromStored($raw);
                                if ($nuevo === null) {
                                    Notification::make()
                                        ->title('Valor no válido')
                                        ->body('No se reconoce el estatus seleccionado.')
                                        ->danger()
                                        ->send();

                                    return;
                                }

                                $obsHtml = null;
                                if ($nuevo === StatusVaucher::Anulado) {
                                    $obsHtml = (string) ($data['observacion_anulacion'] ?? '');
                                    $plainLength = mb_strlen(trim(html_entity_decode(strip_tags($obsHtml), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
                                    if ($plainLength < 3) {
                                        Notification::make()
                                            ->title('Observación requerida')
                                            ->body('Al anular el voucher debe escribir al menos 3 caracteres en la observación de proceso.')
                                            ->warning()
                                            ->send();

                                        return;
                                    }
                                }

                                TdevReportVaucherStatusUpdater::apply($record, $nuevo, $obsHtml);

                                $body = 'El voucher '.$record->vaucher.' quedó en '.$nuevo->label().'.';
                                if ($nuevo === StatusVaucher::Anulado) {
                                    $body .= ' Pago y comisión quedaron en Anulado.';
                                }

                                Notification::make()
                                    ->title('Estatus actualizado')
                                    ->body($body)
                                    ->success()
                                    ->send();
                            })
                    ),
                TextColumn::make('cupon_de_descuento')
                    ->label('Cupon de descuento')
                    ->icon('heroicon-o-ticket')

                    ->sortable()
                    ->searchable(),
                TextColumn::make('cupon_comision')
                    ->label('Cupon de comisión')
                    ->icon('heroicon-o-ticket')

                    ->searchable(),
                TextColumn::make('cupon_promosion')
                    ->label('Cupon de promoción')
                    ->icon('heroicon-o-ticket')
                    ->searchable(),
                TextColumn::make('porcentaje_cupon')
                    ->label('Porcentaje de cupón')
                    ->searchable(),
                TextColumn::make('precio_upgrade')
                    ->label('Precio de upgrade')
                    ->icon('heroicon-o-currency-dollar')
                    ->searchable(),
                TextColumn::make('monto_pvp_precio_de_venta')
                    ->label('Monto PVP precio de venta')
                    ->icon('heroicon-o-currency-dollar')
                    ->searchable(),
                TextColumn::make('forma_pago')
                    ->label('Forma de pago')
                    ->icon('heroicon-o-credit-card')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => FormaPago::labelFromMixed($state))
                    ->color(fn ($state): string => FormaPago::filamentColorFromMixed($state))
                    ->searchable(),
                TextColumn::make('entidad_bancaria_receptora')
                    ->label('Entidad bancaria receptora')
                    ->icon('heroicon-o-building-office')

                    ->searchable(),
                TextColumn::make('estatus_pago')
                    ->label('Estatus de pago')
                    ->icon('heroicon-o-check-circle')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => StatusPago::labelFromMixed($state))
                    ->color(fn ($state): string => StatusPago::filamentColorFromMixed($state))
                    ->searchable(),
                TextColumn::make('referencia_bancaria_pago_vaucher_credito')
                    ->label('Referencia bancaria pago voucher credito')
                    ->icon('heroicon-o-credit-card')
                    ->searchable(),
                TextColumn::make('tasa_bcv')
                    ->label('Tasa BCV')

                    ->searchable(),
                TextColumn::make('monto_abonado_en_cuenta_vaucher_credito')
                    ->label('Monto abonado en cuenta voucher credito')
                    ->icon('heroicon-o-currency-dollar')

                    ->searchable(),
                TextColumn::make('fecha_pago_vaucher_credito')
                    ->label('Fecha pago voucher credito')
                    ->icon('heroicon-o-calendar-days')

                    ->searchable(),
                TextColumn::make('dias_transcurridos')
                    ->label('Dias transcurridos')
                    ->icon('heroicon-o-clock')

                    ->searchable(),
                TextInputColumn::make('porcentaje_comision')
                    ->label('Porcentaje de comisión')
                    ->type('number')
                    ->step('0.0001')
                    ->rules(['nullable', 'numeric', 'min:0'])
                    ->validationAttribute('porcentaje de comisión')
                    ->prefix('%')
                    ->alignEnd()
                    ->searchable()
                    ->updateStateUsing(
                        fn (mixed $state, TdevReport $record): ?float => TdevReportCommissionFromPercentageUpdater::apply($record, $state)
                    ),
                TextColumn::make('monto_comision')
                    ->label('Monto de comisión')
                    ->icon('heroicon-o-currency-dollar')

                    ->searchable(),
                TextColumn::make('estatus_comision')
                    ->label('Estatus de comisión')
                    ->icon('heroicon-o-check-circle')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => StatusComision::labelFromMixed($state))
                    ->color(fn ($state): string => StatusComision::filamentColorFromMixed($state))
                    ->searchable()
                    ->tooltip('Clic para cambiar el estatus de comisión')
                    ->action(
                        Action::make('actualizarEstatusComisionTdev')
                            ->slideOver()
                            ->modalWidth(Width::ThreeExtraLarge)
                            ->modalHeading('Estatus de comisión')
                            ->modalDescription('Seleccione el nuevo estatus de comisión del movimiento.')
                            ->icon('heroicon-o-banknotes')
                            ->modalSubmitActionLabel('Guardar')
                            ->modalSubmitAction(
                                fn (Action $action): Action => $action
                                    ->extraAttributes([
                                        'class' => TdevReportPaymentModalActions::IOS_SUCCESS_BTN,
                                    ])
                            )
                            ->modalCancelAction(
                                fn (Action $action): Action => $action
                                    ->label('Cancelar')
                                    ->extraAttributes([
                                        'class' => TdevReportPaymentModalActions::IOS_GRAY_BTN,
                                    ])
                            )
                            ->successNotification(null)
                            ->fillForm(fn (TdevReport $record): array => [
                                'estatus_comision' => $record->estatus_comision?->value ?? $record->getRawOriginal('estatus_comision'),
                            ])
                            ->form([
                                Section::make('Estatus')
                                    ->description('El cambio queda registrado en el sistema y en el log de la aplicación.')
                                    ->icon('heroicon-m-flag')
                                    ->schema([
                                        Select::make('estatus_comision')
                                            ->label('Estatus de comisión')
                                            ->options(StatusComision::options())
                                            ->required()
                                            ->native(false)
                                            ->searchable(),
                                    ])
                                    ->columns(1)
                                    ->columnSpanFull()
                                    ->extraAttributes([
                                        'class' => TdevReportPaymentModalActions::IOS_SECTION_CLASS,
                                    ]),
                            ])
                            ->action(function (TdevReport $record, array $data): void {
                                if (Auth::user() === null) {
                                    Notification::make()
                                        ->title('Sesión requerida')
                                        ->body('Debe iniciar sesión para cambiar el estatus.')
                                        ->danger()
                                        ->send();

                                    return;
                                }

                                $raw = $data['estatus_comision'] ?? '';
                                $nuevo = StatusComision::tryFrom((string) $raw)
                                    ?? StatusComision::fromStored($raw);
                                if ($nuevo === null) {
                                    Notification::make()
                                        ->title('Valor no válido')
                                        ->body('No se reconoce el estatus de comisión seleccionado.')
                                        ->danger()
                                        ->send();

                                    return;
                                }

                                TdevReportComisionStatusUpdater::apply($record, $nuevo);

                                Notification::make()
                                    ->title('Estatus actualizado')
                                    ->body('La comisión del voucher '.$record->vaucher.' quedó en '.$nuevo->label().'.')
                                    ->success()
                                    ->send();
                            })
                    ),
                TextColumn::make('fecha_pago_comision')
                    ->label('Fecha de pago de comisión')
                    ->icon('heroicon-o-calendar-days')

                    ->searchable(),
                TextColumn::make('formas_pago_comision')
                    ->label('Formas de pago de comisión')
                    ->icon('heroicon-o-credit-card')

                    ->searchable(),
                TextColumn::make('referencia_bancaria_comision')
                    ->label('Referencia bancaria de comisión')
                    ->icon('heroicon-o-credit-card')

                    ->searchable(),
                TextColumn::make('relacion_comision')
                    ->label('Relación de comisión')
                    ->icon('heroicon-o-link')
                    ->searchable(),

                TextColumn::make('comision_agencia')
                    ->label('Comisión de agencia')
                    ->icon('heroicon-o-currency-dollar')

                    ->searchable(),
                TextColumn::make('comision_agente')
                    ->label('Comisión de agente')
                    ->icon('heroicon-o-currency-dollar')

                    ->searchable(),
                TextColumn::make('comision_subagente')
                    ->label('Comisión de subagente')
                    ->icon('heroicon-o-currency-dollar')

                    ->searchable(),
                TextColumn::make('neto_del_servicio')
                    ->label('Neto del servicio')
                    ->icon('heroicon-o-currency-dollar')

                    ->searchable(),
                TextColumn::make('utilidad_tdev')
                    ->label('Utilidad TDEV')
                    ->icon('heroicon-o-currency-dollar')

                    ->searchable(),
                TextColumn::make('status_report')
                    ->label('Estatus del reporte')
                    ->icon('heroicon-o-check-circle')

                    ->searchable(),
            ])
            ->filters([
                SelectFilter::make('status_report')
                    ->label('Estatus del reporte')
                    ->options(fn (): array => self::distinctOptions('status_report'))
                    ->searchable()
                    ->preload(),
                SelectFilter::make('estatus_vaucher')
                    ->label('Estatus del voucher')
                    ->options(StatusVaucher::options())
                    ->searchable()
                    ->preload(),
                SelectFilter::make('estatus_pago')
                    ->label('Estatus de pago')
                    ->options(StatusPago::options())
                    ->searchable()
                    ->preload(),
                SelectFilter::make('forma_pago')
                    ->label('Forma de pago')
                    ->options(FormaPago::options())
                    ->searchable()
                    ->preload(),
                SelectFilter::make('estatus_comision')
                    ->label('Estatus de comisión')
                    ->options(StatusComision::options())
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ActionGroup::make([
                    TdevReportPaymentModalActions::makeRegistrarPagoAction(),
                    TdevReportProcessNotesModalActions::makeAddProcessObservationAction(),
                    TdevReportProcessNotesModalActions::makeViewProcessObservationsAction(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Eliminar seleccionados'),
                ]),
            ]);
    }

    /**
     * @return array<string, string>
     */
    private static function distinctOptions(string $column): array
    {
        if (! in_array($column, (new TdevReport)->getFillable(), true)) {
            return [];
        }

        return TdevReport::query()
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->distinct()
            ->orderBy($column)
            ->pluck($column, $column)
            ->all();
    }
}
