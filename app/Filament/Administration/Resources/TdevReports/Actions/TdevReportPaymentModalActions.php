<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\TdevReports\Actions;

use App\Enums\FormaPago;
use App\Enums\StatusPago;
use App\Models\TdevReport;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Width;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

final class TdevReportPaymentModalActions
{
    /**
     * Misma clase CSS que Helpdesk (theme.css `.fi-helpdesk-ios-section`).
     */
    public const IOS_SECTION_CLASS = 'fi-helpdesk-ios-section';

    public const IOS_SUCCESS_BTN = 'aviso-btn-ios-success shrink-0 inline-flex min-w-[7.5rem] items-center justify-center gap-2 rounded-full px-5 py-2.5 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    public const IOS_GRAY_BTN = 'ticket-btn-ios-gray shrink-0 inline-flex min-w-[7.5rem] items-center justify-center gap-2 rounded-full px-5 py-2.5 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    public static function makeRegistrarPagoAction(): Action
    {
        return Action::make('registrarPagoTdev')
            ->label('Registrar pago')
            ->icon('heroicon-m-document-currency-dollar')
            ->color('success')
            ->slideOver()
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalHeading('Comprobante y datos de pago')
            ->modalDescription(fn (TdevReport $record): string => 'Voucher '.$record->vaucher.' · '.$record->pasajero)
            ->modalSubmitActionLabel('Guardar pago')
            ->modalSubmitAction(
                fn (Action $action): Action => $action
                    ->extraAttributes([
                        'class' => self::IOS_SUCCESS_BTN,
                    ])
            )
            ->modalCancelAction(
                fn (Action $action): Action => $action
                    ->label('Cancelar')
                    ->extraAttributes([
                        'class' => self::IOS_GRAY_BTN,
                    ])
            )
            ->fillForm(fn (TdevReport $record): array => [
                'comprobante_pago' => $record->comprobante_pago_path,
                'forma_pago' => $record->forma_pago?->value ?? $record->getRawOriginal('forma_pago'),
                'entidad_bancaria_receptora' => $record->entidad_bancaria_receptora,
                'estatus_pago' => $record->estatus_pago?->value ?? $record->getRawOriginal('estatus_pago'),
                'referencia_bancaria_pago_vaucher_credito' => $record->referencia_bancaria_pago_vaucher_credito,
                'tasa_bcv' => $record->tasa_bcv,
                'monto_abonado_en_cuenta_vaucher_credito' => $record->monto_abonado_en_cuenta_vaucher_credito,
                'fecha_pago_vaucher_credito' => self::parseDateForPicker($record->fecha_pago_vaucher_credito),
            ])
            ->form(fn (TdevReport $record): array => [
                Section::make('Comprobante de pago')
                    ->description('Adjunte imagen o PDF del comprobante. Si ya existe uno, puede conservarlo o subir uno nuevo.')
                    ->icon('heroicon-m-paper-clip')
                    ->schema([
                        FileUpload::make('comprobante_pago')
                            ->label('Archivo del comprobante')
                            ->disk('public')
                            ->directory('tdev-reports/'.$record->getKey().'/comprobantes')
                            ->visibility('public')
                            ->maxSize(5120)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf'])
                            ->imagePreviewHeight('160')
                            ->panelLayout('grid')
                            ->downloadable()
                            ->openable()
                            ->required(fn (): bool => blank($record->comprobante_pago_path))
                            ->helperText('Hasta 5 MB. JPG, PNG, WebP, GIF o PDF.')
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull()
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
                    ]),
                Section::make('Datos del pago')
                    ->description('Actualice forma de pago, banco, referencia, tasa BCV, monto abonado y fecha.')
                    ->icon('heroicon-m-banknotes')
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2])
                            ->schema([
                                Select::make('forma_pago')
                                    ->label('Forma de pago')
                                    ->prefixIcon('heroicon-m-credit-card')
                                    ->options(FormaPago::options())
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->required(),
                                Select::make('estatus_pago')
                                    ->label('Estatus de pago')
                                    ->prefixIcon('heroicon-m-check-badge')
                                    ->options(StatusPago::options())
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->nullable(),
                                TextInput::make('entidad_bancaria_receptora')
                                    ->label('Entidad bancaria receptora')
                                    ->prefixIcon('heroicon-m-building-library')
                                    ->maxLength(255),
                                TextInput::make('referencia_bancaria_pago_vaucher_credito')
                                    ->label('Referencia bancaria (pago voucher crédito)')
                                    ->prefixIcon('heroicon-m-hashtag')
                                    ->maxLength(255),
                                TextInput::make('tasa_bcv')
                                    ->label('Tasa BCV')
                                    ->prefixIcon('heroicon-m-chart-bar')
                                    ->numeric()
                                    ->default(0),
                                TextInput::make('monto_abonado_en_cuenta_vaucher_credito')
                                    ->label('Monto abonado (cuenta voucher crédito)')
                                    ->prefixIcon('heroicon-m-currency-dollar')
                                    ->numeric()
                                    ->prefix('VES')
                                    ->nullable(),
                                DatePicker::make('fecha_pago_vaucher_credito')
                                    ->label('Fecha pago voucher crédito')
                                    ->prefixIcon('heroicon-m-calendar-days')
                                    ->native(false)
                                    ->displayFormat('d/m/Y')
                                    ->nullable()
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull()
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
                    ]),
            ])
            ->successNotification(null)
            ->action(function (TdevReport $record, array $data): void {
                $path = $data['comprobante_pago'] ?? null;
                if (is_array($path)) {
                    $path = $path[0] ?? null;
                }
                if ($path === null || $path === '') {
                    $path = $record->comprobante_pago_path;
                }
                if ($path === null || $path === '') {
                    Notification::make()
                        ->title('Comprobante requerido')
                        ->body('Debe adjuntar el comprobante de pago.')
                        ->warning()
                        ->send();

                    return;
                }

                if (! is_string($path)) {
                    Notification::make()
                        ->title('Comprobante inválido')
                        ->body('No se pudo guardar la ruta del archivo.')
                        ->danger()
                        ->send();

                    return;
                }

                $previous = $record->comprobante_pago_path;
                $previousStr = is_string($previous) ? $previous : null;
                $comprobanteNuevoOCambiado = self::comprobanteFueNuevoOCambiado($previousStr, $path);

                if (is_string($previous) && $previous !== '' && $previous !== $path) {
                    Storage::disk('public')->delete($previous);
                }

                $fecha = $data['fecha_pago_vaucher_credito'] ?? null;
                $fechaStr = null;
                if ($fecha !== null && $fecha !== '') {
                    try {
                        $fechaStr = Carbon::parse($fecha)->toDateString();
                    } catch (\Throwable) {
                        $fechaStr = is_string($fecha) ? $fecha : null;
                    }
                }

                $formEstatusPago = $data['estatus_pago'] ?? null;
                $formEstatusPago = is_string($formEstatusPago) && $formEstatusPago !== '' ? $formEstatusPago : null;

                $estatusPago = self::resolveEstatusPagoAfterComprobanteUpload(
                    $previousStr,
                    $path,
                    $formEstatusPago,
                );

                $record->update([
                    'comprobante_pago_path' => $path,
                    'forma_pago' => $data['forma_pago'] ?? null,
                    'entidad_bancaria_receptora' => $data['entidad_bancaria_receptora'] ?? null,
                    'estatus_pago' => $estatusPago,
                    'referencia_bancaria_pago_vaucher_credito' => $data['referencia_bancaria_pago_vaucher_credito'] ?? null,
                    'tasa_bcv' => $data['tasa_bcv'] ?? 0,
                    'monto_abonado_en_cuenta_vaucher_credito' => $data['monto_abonado_en_cuenta_vaucher_credito'] ?? null,
                    'fecha_pago_vaucher_credito' => $fechaStr,
                ]);

                $body = 'Se guardó el comprobante y los datos de pago del movimiento #'.$record->getKey().'.';
                if ($comprobanteNuevoOCambiado) {
                    $body .= ' El estatus de pago quedó en Pagado por la carga del comprobante.';
                }

                Notification::make()
                    ->title('Pago registrado')
                    ->body($body)
                    ->success()
                    ->send();
            });
    }

    /**
     * Si el comprobante es la primera carga o un archivo distinto al anterior, el pago pasa a Pagado automáticamente.
     * Si el comprobante no cambió, se respeta el estatus elegido en el formulario.
     */
    public static function resolveEstatusPagoAfterComprobanteUpload(?string $previousComprobantePath, string $newComprobantePath, ?string $formEstatusPago): ?string
    {
        if (self::comprobanteFueNuevoOCambiado($previousComprobantePath, $newComprobantePath)) {
            return StatusPago::Pagado->value;
        }

        return $formEstatusPago;
    }

    public static function comprobanteFueNuevoOCambiado(?string $previousComprobantePath, string $newComprobantePath): bool
    {
        $prev = trim((string) ($previousComprobantePath ?? ''));
        $next = trim($newComprobantePath);

        return $next !== '' && $prev !== $next;
    }

    private static function parseDateForPicker(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
