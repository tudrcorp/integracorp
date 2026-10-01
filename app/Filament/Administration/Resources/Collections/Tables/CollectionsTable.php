<?php

namespace App\Filament\Administration\Resources\Collections\Tables;

use App\Http\Controllers\LogController;
use App\Mail\MailAvisoDeCobro;
use App\Models\Collection;
use App\Support\Collections\CollectionAdjuster;
use App\Support\Collections\CollectionAdjustmentAccess;
use App\Support\Collections\CollectionDueDate;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CollectionsTable
{
    public const STATUS_OVERDUE = CollectionDueDate::STATUS_OVERDUE;

    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Cuotas de cobranza')
            ->description('Una fila por cuota. Las cuotas por pagar cuya fecha ya pasó se marcan como vencidas. Use los filtros para acotar por estado, fecha de próximo pago o plan.')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with([
                    'plan:id,description',
                    'agent:id,name',
                    'coverage:id,price',
                ])
                ->withCount('adjustments'))
            ->defaultSort('created_at', 'desc')
            ->searchPlaceholder('Buscar por afiliado, cédula, aviso o afiliación')
            ->searchDebounce('350ms')
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->columns([
                TextColumn::make('affiliate_full_name')
                    ->label('Afiliado')
                    ->weight('semibold')
                    ->wrap()
                    ->description(fn (Collection $record): string => collect([
                        filled($record->affiliate_ci_rif) ? 'C.I./R.I.F. '.$record->affiliate_ci_rif : null,
                        $record->affiliation_code,
                    ])->filter()->implode(' · '))
                    ->sortable()
                    ->searchable(['affiliate_full_name', 'affiliate_ci_rif', 'affiliation_code']),
                TextColumn::make('collection_invoice_number')
                    ->label('Nro. de aviso')
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->iconColor('gray')
                    ->description(fn (Collection $record): ?string => filled($record->include_date) ? 'Emitido el '.$record->include_date : null)
                    ->sortable()
                    ->searchable(),
                TextColumn::make('code_agency')
                    ->label('Agencia / agente')
                    ->description(fn (Collection $record): string => $record->agent?->name ?: 'Sin agente')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('plan.description')
                    ->label('Plan')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'PLAN INICIAL' => 'azul',
                        'PLAN IDEAL' => 'azulOscuro',
                        'PLAN ESPECIAL' => 'verde',
                        default => 'gray',
                    })
                    ->description(fn (Collection $record): ?string => filled($record->payment_frequency) ? 'Pago '.Str::lower((string) $record->payment_frequency) : null)
                    ->placeholder('Sin plan')
                    ->sortable(),
                TextColumn::make('total_amount')
                    ->label('Monto')
                    ->money('USD')
                    ->weight('semibold')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('next_payment_date')
                    ->label('Próximo pago')
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->iconColor('gray')
                    ->tooltip('Para corregir la fecha o el estado use «Ajustar cuota» en el menú de la fila.')
                    ->description(fn (Collection $record): ?string => ($record->adjustments_count ?? 0) > 0
                        ? 'Ajustada a mano ('.$record->adjustments_count.')'
                        : null)
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('filter_next_payment_date', $direction === 'desc' ? 'desc' : 'asc'))
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->state(fn (Collection $record): string => self::displayStatus($record))
                    ->color(fn (string $state): string => match ($state) {
                        'PAGADO' => 'success',
                        'POR PAGAR' => 'warning',
                        self::STATUS_OVERDUE, 'ANULADO', 'CANCELADO' => 'danger',
                        default => 'gray',
                    })
                    ->icon(fn (string $state): Heroicon => match ($state) {
                        'PAGADO' => Heroicon::CheckCircle,
                        self::STATUS_OVERDUE => Heroicon::ExclamationTriangle,
                        'ANULADO', 'CANCELADO' => Heroicon::XCircle,
                        default => Heroicon::Clock,
                    })
                    ->description(fn (Collection $record): ?string => self::dueLabel($record))
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('status', $direction)),
                TextColumn::make('affiliate_phone')
                    ->label('Contacto')
                    ->icon(Heroicon::OutlinedPhone)
                    ->iconColor('gray')
                    ->description(fn (Collection $record): ?string => $record->affiliate_email)
                    ->placeholder('Sin teléfono')
                    ->searchable(['affiliate_phone', 'affiliate_email', 'affiliate_contact'])
                    ->toggleable(),
                TextColumn::make('affiliate_contact')
                    ->label('Persona de contacto')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('affiliate_status')
                    ->label('Estatus afiliación')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'ACTIVA', 'ACTIVO' => 'success',
                        'INACTIVA', 'EXCLUIDA', 'EXCLUIDO' => 'danger',
                        default => 'gray',
                    })
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('quote_number')
                    ->label('Cotización')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => self::isCorporate($state) ? 'Corporativa' : 'Individual')
                    ->color(fn (?string $state): string => self::isCorporate($state) ? 'verdeOpaco' : 'primary')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('persons')
                    ->label('Población')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('coverage.price')
                    ->label('Cobertura')
                    ->money('USD')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('payment_method')
                    ->label('Método de pago')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('reference')
                    ->label('Referencia')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'POR PAGAR' => 'Por pagar',
                        self::STATUS_OVERDUE => 'Vencidas (por pagar con fecha pasada)',
                        'PAGADO' => 'Pagado',
                        'ANULADO' => 'Anulado',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        null, '' => $query,
                        self::STATUS_OVERDUE => $query->where('status', 'POR PAGAR')->whereDate('filter_next_payment_date', '<', CarbonImmutable::today()),
                        default => $query->where('status', $data['value']),
                    })
                    ->placeholder('Todos los estados')
                    ->label('Estado'),
                Filter::make('with_adjustments')
                    ->label('Solo cuotas con ajustes manuales')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereHas('adjustments')),
                Filter::make('filter_next_payment_date')
                    ->label('Próximo pago')
                    ->schema([
                        DatePicker::make('desde')
                            ->label('Próximo pago desde')
                            ->format('Y-m-d'),
                        DatePicker::make('hasta')
                            ->label('Próximo pago hasta')
                            ->format('Y-m-d'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['desde'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('filter_next_payment_date', '>=', $date),
                            )
                            ->when(
                                $data['hasta'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('filter_next_payment_date', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['desde'] ?? null) {
                            $indicators['desde'] = 'Próximo pago desde '.Carbon::parse($data['desde'])->format('d/m/Y');
                        }
                        if ($data['hasta'] ?? null) {
                            $indicators['hasta'] = 'Próximo pago hasta '.Carbon::parse($data['hasta'])->format('d/m/Y');
                        }

                        return $indicators;
                    }),
                SelectFilter::make('payment_frequency')
                    ->options([
                        'ANUAL' => 'Anual',
                        'SEMESTRAL' => 'Semestral',
                        'TRIMESTRAL' => 'Trimestral',
                        'MENSUAL' => 'Mensual',
                    ])
                    ->placeholder('Todas')
                    ->label('Frecuencia de pago'),
                SelectFilter::make('plan_id')
                    ->relationship('plan', 'description')
                    ->placeholder('Todos los planes')
                    ->label('Plan'),
                SelectFilter::make('payment_method')
                    ->options([
                        'EFECTIVO US$' => 'EFECTIVO US$',
                        'ZELLE' => 'ZELLE',
                        'PAGO MOVIL VES' => 'PAGO MOVIL VES',
                        'TRANSFERENCIA VES' => 'TRANSFERENCIA VES',
                    ])
                    ->placeholder('Todos')
                    ->label('Método de pago'),
                SelectFilter::make('bank')
                    ->options([
                        'CHASE BANK' => 'CHASE BANK',
                        'BANK OF AMERICA' => 'BANK OF AMERICA',
                        'BANESCO, S.A-US$' => 'BANESCO, S.A - US$',
                        'BANCAMIGA - US$' => 'BANCAMIGA - US$',
                        'BANCAMIGA - VES' => 'BANCAMIGA - VES',
                        'BANCO DE VENEZUELA - US$' => 'BANCO DE VENEZUELA - US$',
                        'BANCO DE VENEZUELA - VES' => 'BANCO DE VENEZUELA - VES',
                    ])
                    ->placeholder('Todos')
                    ->label('Banco'),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(3)
            ->emptyStateIcon(Heroicon::OutlinedClipboardDocumentList)
            ->emptyStateHeading('No hay cuotas con estos filtros')
            ->emptyStateDescription('Pruebe quitando filtros o ampliando el rango de fechas de próximo pago.')
            ->recordActions([
                ActionGroup::make([

                    /**ENVIO MANUAL DEL AVISO DE COBRO */
                    Action::make('send_email')
                        ->label('Enviar Aviso de Cobro')
                        ->icon('heroicon-c-arrow-uturn-right')
                        ->modalHeading('Envío manual de aviso de cobro')
                        ->color('azul')
                        ->form([
                            Grid::make(2)
                                ->schema([
                                    TextInput::make('email')
                                        ->helperText('Si deja este campo en blanco, se enviara el aviso de cobro al correo del afiliado registrado.')
                                        ->email()
                                        ->maxLength(255)
                                        ->label('Email')
                                        ->placeholder('ejemplo@gmail.com'),
                                    TextInput::make('phone')
                                        ->helperText('Si deja este campo en blanco, se enviara el aviso de cobro al teléfono del afiliado registrado.')
                                        ->maxLength(255)
                                        ->label('Teléfono')
                                        ->tel()
                                        ->placeholder('04127869087'),
                                ]),
                        ])
                        ->action(function (Collection $record, array $data) {

                            try {

                                $name_pdf = 'ADP-'.$record->collection_invoice_number.'.pdf';

                                if ($data['email'] == null) {
                                    Mail::to($record->affiliate_email)->send(new MailAvisoDeCobro($name_pdf));
                                }

                                if ($data['email'] != null) {
                                    Mail::to($data['email'])->send(new MailAvisoDeCobro($name_pdf));
                                }

                                /**
                                 * TODO
                                 * Debo agregar la logica para el envio del aviso de cobro por whatsapp
                                 * ---------------------------------------------------------------------
                                 */

                                /**
                                 * Notificacion al usuario
                                 *--------------------------------------------------------------------
                                 */
                                Notification::make()
                                    ->title('ENVIADO')
                                    ->body('Aviso de cobro enviado correctamente')
                                    ->icon('heroicon-s-check-circle')
                                    ->iconColor('success')
                                    ->success()
                                    ->send();

                                /**
                                 * LOG
                                 */
                                LogController::log(Auth::user()->id, 'Envió de aviso de cobro', 'Modulo Cobranza', 'DESCARGAR');
                            } catch (\Throwable $th) {
                                LogController::log(Auth::user()->id, 'EXCEPTION', 'agents.IndividualQuoteResource.action.enit', $th->getMessage());
                                Notification::make()
                                    ->title('ERROR')
                                    ->body($th->getMessage())
                                    ->icon('heroicon-s-x-circle')
                                    ->iconColor('danger')
                                    ->danger()
                                    ->send();
                            }
                        })
                        ->hidden(fn (Collection $record) => $record->status == 'PAGADO'),

                    /**DESCARGAR PDF */
                    Action::make('download_pdf')
                        ->label('Descargar PDF')
                        ->icon('heroicon-s-arrow-down-on-square-stack')
                        ->color('verde')
                        ->action(function (Collection $record) {

                            try {
                                /**
                                 * Descargar el documento asociado a la cotizacion
                                 * ruta: storage/
                                 */
                                $path = public_path('storage/avisoDeCobro/ADP-'.$record->collection_invoice_number.'.pdf');

                                return response()->download($path);
                                /**
                                 * LOG
                                 */
                                LogController::log(Auth::user()->id, 'Descarga de documento', 'Modulo Cotizacion Individual', 'DESCARGAR');
                            } catch (\Throwable $th) {
                                LogController::log(Auth::user()->id, 'EXCEPTION', 'agents.IndividualQuoteResource.action.enit', $th->getMessage());
                                Notification::make()
                                    ->title('ERROR')
                                    ->body($th->getMessage())
                                    ->icon('heroicon-s-x-circle')
                                    ->iconColor('danger')
                                    ->danger()
                                    ->send();
                            }
                        }),

                    /**REGENERAR PDF */
                    Action::make('regenerate_pdf')
                        ->label('Regenerar PDF')
                        ->icon('heroicon-s-arrow-down-on-square-stack')
                        ->color('warning')
                        ->modalHeading('Aviso de cobro')
                        ->modalDescription('Se regenera el PDF al abrir. Luego puede previsualizarlo y enviarlo por correo al afiliado.')
                        ->modalIcon('heroicon-o-document-arrow-down')
                        ->modalWidth(Width::SevenExtraLarge)
                        ->modalContent(function (Collection $record): ViewContract {
                            return View::make('filament.administration.collections.aviso-cobro-preview-modal', [
                                'collection' => $record,
                            ]);
                        })
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Cerrar')
                        ->action(fn () => null),

                    self::adjustAction(),
                    self::adjustmentHistoryAction(),

                ])->icon('heroicon-c-ellipsis-vertical')->color('azulOscuro'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    self::markAsPaidBulkAction(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Corrige fecha y estado de una cuota con motivo y bitácora ({@see CollectionAdjuster}).
     * Reemplaza la edición directa de la fecha en la tabla y por SQL.
     */
    public static function adjustAction(): Action
    {
        return Action::make('adjust_collection')
            ->label('Ajustar cuota')
            ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
            ->color('warning')
            ->visible(fn (): bool => CollectionAdjustmentAccess::userCan(Auth::user()))
            ->modalHeading(fn (Collection $record): string => 'Ajustar cuota '.($record->collection_invoice_number ?: '#'.$record->getKey()))
            ->modalDescription(fn (Collection $record): string => trim(($record->affiliate_full_name ?? '').' · '.($record->affiliation_code ?? ''), ' ·')
                .'. Corrija la fecha de próximo pago o el estado. La expiración, los días y los filtros se recalculan solos, y el cambio queda registrado con su motivo.')
            ->modalIcon(Heroicon::OutlinedAdjustmentsHorizontal)
            ->modalWidth(Width::TwoExtraLarge)
            ->modalSubmitActionLabel('Guardar ajuste')
            ->fillForm(fn (Collection $record): array => [
                'date' => CollectionDueDate::of($record)?->format('Y-m-d'),
                'status' => array_key_exists(Str::upper((string) $record->status), CollectionAdjuster::statusOptions())
                    ? Str::upper((string) $record->status)
                    : null,
                'expected_updated_at' => (string) $record->getRawOriginal('updated_at'),
            ])
            ->schema([
                Hidden::make('expected_updated_at'),
                Grid::make(2)->schema([
                    DatePicker::make('date')
                        ->label('Fecha de próximo pago')
                        ->helperText('La expiración queda igual a esta fecha.')
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->closeOnDateSelection()
                        ->required()
                        ->live(),
                    Select::make('status')
                        ->label('Estado')
                        ->options(CollectionAdjuster::statusOptions())
                        ->helperText('«Pagado» aquí es un ajuste: no crea venta, recibo ni comisión.')
                        ->native(false)
                        ->required()
                        ->live(),
                ]),
                Checkbox::make('confirm_unusual_date')
                    ->label('Confirmo que la fecha es correcta aunque está lejos de hoy')
                    ->visible(fn (Get $get): bool => CollectionAdjuster::isUnusualDate(CollectionDueDate::parse($get('date'))))
                    ->accepted()
                    ->validationMessages(['accepted' => 'Confirme que la fecha es correcta o corríjala.']),
                Textarea::make('reason')
                    ->label('Motivo del ajuste')
                    ->placeholder('Ej.: pago verificado en el estado de cuenta del 12/03/2025; era data vieja sin registrar.')
                    ->required()
                    ->minLength(CollectionAdjuster::MIN_REASON_LENGTH)
                    ->maxLength(1000)
                    ->rows(3)
                    ->validationMessages([
                        'required' => 'Explique el motivo del ajuste.',
                        'min' => 'El motivo debe tener al menos '.CollectionAdjuster::MIN_REASON_LENGTH.' caracteres.',
                    ]),
                Placeholder::make('preview')
                    ->label('Vista previa del cambio')
                    ->content(fn (Get $get, Collection $record): HtmlString => self::adjustmentPreview($record, $get('date'), $get('status'))),
            ])
            ->action(function (Collection $record, array $data, Action $action): void {
                try {
                    CollectionAdjuster::adjust($record, $data, Auth::user());
                } catch (InvalidArgumentException $exception) {
                    Notification::make()
                        ->title('No se guardó el ajuste')
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();

                    $action->halt();

                    return;
                }

                Notification::make()
                    ->title('Cuota ajustada')
                    ->body('El cambio quedó registrado en el historial de la cuota.')
                    ->success()
                    ->send();
            });
    }

    public static function adjustmentHistoryAction(): Action
    {
        return Action::make('adjustment_history')
            ->label('Historial de ajustes')
            ->icon(Heroicon::OutlinedClock)
            ->color('gray')
            ->visible(fn (Collection $record): bool => ($record->adjustments_count ?? 0) > 0)
            ->modalHeading(fn (Collection $record): string => 'Historial de ajustes · '.($record->collection_invoice_number ?: '#'.$record->getKey()))
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalContent(fn (Collection $record): ViewContract => View::make('filament.administration.collections.adjustment-history', [
                'adjustments' => $record->adjustments()->limit(100)->get(),
                'labels' => self::adjustmentFieldLabels(),
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Cerrar');
    }

    public static function markAsPaidBulkAction(): BulkAction
    {
        return BulkAction::make('mark_paid_historical')
            ->label('Marcar como pagadas (data histórica)')
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('success')
            ->visible(fn (): bool => CollectionAdjustmentAccess::userCan(Auth::user()))
            ->modalHeading('Marcar cuotas como pagadas')
            ->modalDescription(fn (EloquentCollection $records): string => 'Se marcarán '.$records->count().' '.($records->count() === 1 ? 'cuota' : 'cuotas')
                .' como pagadas por ajuste de data histórica. No se crea venta, recibo ni comisión, y cada cuota queda en su historial con este motivo. '
                .'Solo se aceptan cuotas «por pagar»; máximo '.CollectionAdjuster::MAX_BULK.' por vez. Si alguna no cumple, no se marca ninguna.')
            ->modalIcon(Heroicon::OutlinedCheckBadge)
            ->modalSubmitActionLabel('Marcar como pagadas')
            ->schema([
                Textarea::make('reason')
                    ->label('Motivo del ajuste')
                    ->placeholder('Ej.: cuotas de 2024 pagadas en efectivo, verificadas con el cliente; data vieja sin registrar.')
                    ->required()
                    ->minLength(CollectionAdjuster::MIN_REASON_LENGTH)
                    ->maxLength(1000)
                    ->rows(3),
            ])
            ->deselectRecordsAfterCompletion()
            ->action(function (EloquentCollection $records, array $data, BulkAction $action): void {
                try {
                    $count = CollectionAdjuster::markManyAsPaid($records, $data['reason'] ?? null, Auth::user());
                } catch (InvalidArgumentException $exception) {
                    Notification::make()
                        ->title('No se marcó ninguna cuota')
                        ->body($exception->getMessage())
                        ->danger()
                        ->persistent()
                        ->send();

                    $action->halt();

                    return;
                }

                Notification::make()
                    ->title('Cuotas marcadas como pagadas')
                    ->body($count.' '.($count === 1 ? 'cuota quedó' : 'cuotas quedaron').' registradas en su historial de ajustes.')
                    ->success()
                    ->send();
            });
    }

    /**
     * @return array<string, string>
     */
    public static function adjustmentFieldLabels(): array
    {
        return [
            'next_payment_date' => 'Próximo pago',
            'expiration_date' => 'Expiración',
            'status' => 'Estado',
        ];
    }

    private static function adjustmentPreview(Collection $record, mixed $date, mixed $status): HtmlString
    {
        $changes = CollectionAdjuster::previewChanges($record, $date, is_string($status) ? $status : null);
        $blocked = is_string($status) && $status !== '' ? CollectionAdjuster::blockingReason($record, $status) : null;
        $labels = self::adjustmentFieldLabels();

        $html = '';

        if ($blocked !== null) {
            $html .= '<div style="margin-bottom:8px;padding:8px 10px;border-radius:10px;background:rgba(220,38,38,.1);color:#dc2626;font-size:.85rem;font-weight:600;">'.e($blocked).'</div>';
        }

        if ($changes === []) {
            return new HtmlString($html.'<span class="text-gray-600 dark:text-gray-300" style="font-size:.85rem;">Sin cambios todavía.</span>');
        }

        foreach ($changes as $field => $change) {
            $html .= '<div style="display:flex;flex-wrap:wrap;gap:6px;align-items:baseline;font-size:.85rem;padding:2px 0;">'
                .'<span class="text-gray-600 dark:text-gray-300" style="min-width:110px;font-weight:600;">'.e($labels[$field] ?? $field).'</span>'
                .'<span style="text-decoration:line-through;opacity:.7;">'.e($change['before'] ?? '—').'</span>'
                .'<span>→</span>'
                .'<span class="text-gray-900 dark:text-white" style="font-weight:700;">'.e($change['after'] ?? '—').'</span>'
                .'</div>';
        }

        return new HtmlString($html);
    }

    /**
     * Estado para mostrar: una cuota POR PAGAR con la fecha de próximo pago ya pasada
     * se muestra como VENCIDO. No cambia el estado guardado. Mismo cálculo que
     * «Cobranza por mes» ({@see CollectionDueDate}).
     */
    public static function displayStatus(Collection $record, ?CarbonImmutable $today = null): string
    {
        return CollectionDueDate::displayStatus($record, $today);
    }

    public static function dueLabel(Collection $record, ?CarbonImmutable $today = null): ?string
    {
        $label = CollectionDueDate::daysLabel($record, $today);

        return $label === 'Sin fecha' ? null : $label;
    }

    public static function isCorporate(?string $type): bool
    {
        return str_contains(Str::upper(Str::ascii((string) $type)), 'CORPORATIVA');
    }
}
