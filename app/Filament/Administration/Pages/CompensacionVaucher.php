<?php

declare(strict_types=1);

namespace App\Filament\Administration\Pages;

use App\Enums\FormaPago;
use App\Enums\StatusPago;
use App\Enums\StatusVaucher;
use App\Filament\Administration\Resources\TdevReports\Actions\TdevReportPaymentModalActions;
use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Http\Controllers\LogController;
use App\Models\TdevReport;
use App\Services\TdevReports\TdevReportCommissionFromPercentageUpdater;
use App\Services\TdevReports\TdevReportVaucherStatusUpdater;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Features\SupportFileUploads\WithFileUploads;
use UnitEnum;

class CompensacionVaucher extends Page
{
    use AuthorizesDepartmentNavigation;
    use WithFileUploads;

    protected static string|UnitEnum|null $navigationGroup = 'COMPENSACION TDEV';

    protected static ?string $navigationLabel = 'Compensacion de Vaucher';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.administration.pages.compensacion-vaucher';

    public string $voucherSearch = '';

    public bool $isResultsModalOpen = false;

    /** @var array<int> */
    public array $resultRecordIds = [];

    /** @var array<int, array<string, mixed>> */
    public array $resultRows = [];

    public float $resultTotalMontoPvp = 0.0;

    public float $resultTotalMontoComision = 0.0;

    /** @var array{
     *   comprobante_pago: TemporaryUploadedFile|string|null,
     *   forma_pago: string|null,
     *   estatus_pago: string|null,
     *   entidad_bancaria_receptora: string|null,
     *   referencia_bancaria_pago_vaucher_credito: string|null,
     *   tasa_bcv: float|int|string|null,
     *   monto_abonado_en_cuenta_vaucher_credito: float|int|string|null,
     *   fecha_pago_vaucher_credito: string|null
     * } */
    public array $paymentForm = [
        'comprobante_pago' => null,
        'forma_pago' => null,
        'estatus_pago' => null,
        'entidad_bancaria_receptora' => null,
        'referencia_bancaria_pago_vaucher_credito' => null,
        'tasa_bcv' => null,
        'monto_abonado_en_cuenta_vaucher_credito' => null,
        'fecha_pago_vaucher_credito' => null,
    ];

    /** @var array{estatus_vaucher: string|null, observacion_anulacion: string|null} */
    public array $statusForm = [
        'estatus_vaucher' => null,
        'observacion_anulacion' => null,
    ];

    /** @var array{porcentaje_comision: float|int|string|null} */
    public array $commissionForm = [
        'porcentaje_comision' => null,
    ];

    public function searchVouchers(): void
    {
        $term = trim($this->voucherSearch);
        if ($term === '') {
            $this->auditAction('TDEV_COMPENSACION_SEARCH_EMPTY', [
                'message' => 'Intento de busqueda sin voucher',
            ]);

            Notification::make()
                ->title('Ingrese un voucher')
                ->body('Escriba un número de voucher para iniciar la búsqueda.')
                ->warning()
                ->send();

            return;
        }

        $records = TdevReport::query()
            ->where('vaucher', 'like', '%'.$term.'%')
            ->orderByDesc('id')
            ->get();

        if ($records->isEmpty()) {
            $this->resetResults();
            $this->auditAction('TDEV_COMPENSACION_SEARCH_NO_RESULTS', [
                'term' => $term,
                'results_count' => 0,
            ]);

            Notification::make()
                ->title('Sin resultados')
                ->body('No se encontraron vouchers para el criterio ingresado.')
                ->warning()
                ->send();

            return;
        }

        $this->hydrateResults($records);
        $this->isResultsModalOpen = true;
        $this->auditAction('TDEV_COMPENSACION_SEARCH_RESULTS', [
            'term' => $term,
            'results_count' => $records->count(),
            'record_ids' => $records->pluck('id')->take(20)->all(),
        ]);
    }

    public function closeResultsModal(): void
    {
        $this->isResultsModalOpen = false;
        $this->auditAction('TDEV_COMPENSACION_MODAL_CLOSED', [
            'result_ids_count' => count($this->resultRecordIds),
            'result_ids_sample' => array_slice($this->resultRecordIds, 0, 20),
        ]);
    }

    public function savePaymentTab(): void
    {
        $this->validate([
            'paymentForm.comprobante_pago' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif,pdf', 'max:5120'],
            'paymentForm.forma_pago' => ['nullable', 'string'],
            'paymentForm.estatus_pago' => ['nullable', 'string'],
            'paymentForm.entidad_bancaria_receptora' => ['nullable', 'string', 'max:255'],
            'paymentForm.referencia_bancaria_pago_vaucher_credito' => ['nullable', 'string', 'max:255'],
            'paymentForm.tasa_bcv' => ['nullable', 'numeric', 'min:0'],
            'paymentForm.monto_abonado_en_cuenta_vaucher_credito' => ['nullable', 'numeric', 'min:0'],
            'paymentForm.fecha_pago_vaucher_credito' => ['nullable', 'date'],
        ]);

        $records = $this->getResultRecords();
        if ($records->isEmpty()) {
            $this->auditAction('TDEV_COMPENSACION_SAVE_PAYMENT_EMPTY', [
                'message' => 'Intento de guardar formulario pago sin resultados activos',
            ]);

            Notification::make()
                ->title('Sin registros')
                ->body('La búsqueda actual no tiene registros para actualizar.')
                ->warning()
                ->send();

            return;
        }

        $uploadedPath = null;
        $file = $this->paymentForm['comprobante_pago'] ?? null;
        if ($file instanceof TemporaryUploadedFile) {
            $uploadedPath = $file->store('tdev-reports/compensacion-vaucher/comprobantes', 'public');
            $this->paymentForm['comprobante_pago'] = $uploadedPath;
        }

        $rawEstatusPago = $this->paymentForm['estatus_pago'] ?? null;
        $formEstatusPago = is_string($rawEstatusPago) && $rawEstatusPago !== '' ? $rawEstatusPago : null;

        foreach ($records as $record) {
            $path = $uploadedPath ?? (is_string($record->comprobante_pago_path) ? $record->comprobante_pago_path : null);

            $estatusPago = $path !== null && $path !== ''
                ? TdevReportPaymentModalActions::resolveEstatusPagoAfterComprobanteUpload(
                    is_string($record->comprobante_pago_path) ? $record->comprobante_pago_path : null,
                    $path,
                    $formEstatusPago,
                )
                : $formEstatusPago;

            $record->update([
                'comprobante_pago_path' => $path,
                'forma_pago' => $this->paymentForm['forma_pago'] ?: null,
                'estatus_pago' => $estatusPago,
                'entidad_bancaria_receptora' => $this->paymentForm['entidad_bancaria_receptora'] ?: null,
                'referencia_bancaria_pago_vaucher_credito' => $this->paymentForm['referencia_bancaria_pago_vaucher_credito'] ?: null,
                'tasa_bcv' => $this->paymentForm['tasa_bcv'] ?: 0,
                'monto_abonado_en_cuenta_vaucher_credito' => $this->paymentForm['monto_abonado_en_cuenta_vaucher_credito'] ?: null,
                'fecha_pago_vaucher_credito' => $this->paymentForm['fecha_pago_vaucher_credito'] ?: null,
            ]);
        }

        $this->hydrateResults($records->fresh());
        $this->auditAction('TDEV_COMPENSACION_SAVE_PAYMENT_SUCCESS', [
            'affected_records' => $records->count(),
            'record_ids' => $records->pluck('id')->take(20)->all(),
            'has_uploaded_comprobante' => $uploadedPath !== null,
            'forma_pago' => $this->paymentForm['forma_pago'] ?: null,
            'estatus_pago' => $this->paymentForm['estatus_pago'] ?: null,
            'referencia' => $this->paymentForm['referencia_bancaria_pago_vaucher_credito'] ?: null,
            'fecha_pago' => $this->paymentForm['fecha_pago_vaucher_credito'] ?: null,
        ]);

        $this->dispatch('tab-saved', tab: 'pago');

        Notification::make()
            ->title('Pago actualizado')
            ->body('Formulario 1 completado. Se guardaron datos de pago en '.$records->count().' registro(s).')
            ->success()
            ->send();
    }

    public function saveStatusTab(): void
    {
        $this->validate([
            'statusForm.estatus_vaucher' => ['required', 'string'],
            'statusForm.observacion_anulacion' => ['nullable', 'string'],
        ]);

        $records = $this->getResultRecords();
        if ($records->isEmpty()) {
            $this->auditAction('TDEV_COMPENSACION_SAVE_STATUS_EMPTY', [
                'message' => 'Intento de guardar formulario estatus sin resultados activos',
            ]);

            Notification::make()
                ->title('Sin registros')
                ->body('La búsqueda actual no tiene registros para actualizar.')
                ->warning()
                ->send();

            return;
        }

        $raw = (string) ($this->statusForm['estatus_vaucher'] ?? '');
        $nuevo = StatusVaucher::tryFrom($raw) ?? StatusVaucher::fromStored($raw);
        if ($nuevo === null) {
            $this->auditAction('TDEV_COMPENSACION_SAVE_STATUS_INVALID', [
                'raw_status' => $raw,
            ]);

            Notification::make()
                ->title('Estatus no válido')
                ->body('Seleccione un estatus de voucher válido.')
                ->danger()
                ->send();

            return;
        }

        $obsHtml = null;
        if ($nuevo === StatusVaucher::Anulado) {
            $obsHtml = (string) ($this->statusForm['observacion_anulacion'] ?? '');
            $plainLength = mb_strlen(trim(html_entity_decode(strip_tags($obsHtml), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            if ($plainLength < 3) {
                $this->auditAction('TDEV_COMPENSACION_SAVE_STATUS_OBSERVATION_REQUIRED', [
                    'status' => $raw,
                    'observation_plain_length' => $plainLength,
                ]);

                Notification::make()
                    ->title('Observación requerida')
                    ->body('Al anular debe registrar al menos 3 caracteres en la observación.')
                    ->warning()
                    ->send();

                return;
            }
        }

        foreach ($records as $record) {
            TdevReportVaucherStatusUpdater::apply($record, $nuevo, $obsHtml);
        }

        $this->hydrateResults($records->fresh());
        $this->auditAction('TDEV_COMPENSACION_SAVE_STATUS_SUCCESS', [
            'affected_records' => $records->count(),
            'record_ids' => $records->pluck('id')->take(20)->all(),
            'status' => $raw,
            'has_observation' => filled($obsHtml),
        ]);

        $this->dispatch('tab-saved', tab: 'estatus');

        Notification::make()
            ->title('Estatus actualizado')
            ->body('Formulario 2 completado. Se actualizó el estatus de '.$records->count().' registro(s).')
            ->success()
            ->send();
    }

    public function saveCommissionTab(): void
    {
        $this->validate([
            'commissionForm.porcentaje_comision' => ['nullable', 'numeric', 'min:0'],
        ]);

        $records = $this->getResultRecords();
        if ($records->isEmpty()) {
            $this->auditAction('TDEV_COMPENSACION_SAVE_COMMISSION_EMPTY', [
                'message' => 'Intento de guardar formulario comision sin resultados activos',
            ]);

            Notification::make()
                ->title('Sin registros')
                ->body('La búsqueda actual no tiene registros para actualizar.')
                ->warning()
                ->send();

            return;
        }

        $percentage = $this->commissionForm['porcentaje_comision'];
        foreach ($records as $record) {
            TdevReportCommissionFromPercentageUpdater::apply($record, $percentage);
        }

        $this->hydrateResults($records->fresh());
        $this->auditAction('TDEV_COMPENSACION_SAVE_COMMISSION_SUCCESS', [
            'affected_records' => $records->count(),
            'record_ids' => $records->pluck('id')->take(20)->all(),
            'porcentaje_comision' => $percentage,
        ]);

        $this->dispatch('tab-saved', tab: 'comision');

        Notification::make()
            ->title('Comisión calculada')
            ->body('Formulario 3 completado. Se recalculó la comisión en '.$records->count().' registro(s).')
            ->success()
            ->send();
    }

    private function resetResults(): void
    {
        $this->resultRecordIds = [];
        $this->resultRows = [];
        $this->resultTotalMontoPvp = 0.0;
        $this->resultTotalMontoComision = 0.0;
        $this->isResultsModalOpen = false;
    }

    /**
     * @param  Collection<int, TdevReport>  $records
     */
    private function hydrateResults(Collection $records): void
    {
        $this->resultRecordIds = $records->pluck('id')->all();
        $this->resultRows = $records->map(function (TdevReport $record): array {
            return [
                'id' => $record->id,
                'vaucher' => (string) $record->vaucher,
                'pasajero' => (string) $record->pasajero,
                'agencia' => (string) $record->agencia,
                'monto_pvp_precio_de_venta' => (float) ($record->monto_pvp_precio_de_venta ?? 0),
                'monto_comision' => (float) ($record->monto_comision ?? 0),
                'estatus_vaucher' => $record->estatus_vaucher?->label() ?? (string) $record->getRawOriginal('estatus_vaucher'),
                'estatus_pago' => $record->estatus_pago?->label() ?? (string) $record->getRawOriginal('estatus_pago'),
                'forma_pago' => $record->forma_pago?->label() ?? (string) $record->getRawOriginal('forma_pago'),
            ];
        })->all();

        $this->resultTotalMontoPvp = (float) $records->sum('monto_pvp_precio_de_venta');
        $this->resultTotalMontoComision = (float) $records->sum('monto_comision');

        /** @var TdevReport|null $first */
        $first = $records->first();
        if ($first !== null) {
            $this->paymentForm = [
                'comprobante_pago' => $first->comprobante_pago_path,
                'forma_pago' => $first->forma_pago?->value ?? $first->getRawOriginal('forma_pago'),
                'estatus_pago' => $first->estatus_pago?->value ?? $first->getRawOriginal('estatus_pago'),
                'entidad_bancaria_receptora' => $first->entidad_bancaria_receptora,
                'referencia_bancaria_pago_vaucher_credito' => $first->referencia_bancaria_pago_vaucher_credito,
                'tasa_bcv' => $first->tasa_bcv,
                'monto_abonado_en_cuenta_vaucher_credito' => $first->monto_abonado_en_cuenta_vaucher_credito,
                'fecha_pago_vaucher_credito' => $first->fecha_pago_vaucher_credito,
            ];

            $this->statusForm = [
                'estatus_vaucher' => $first->estatus_vaucher?->value ?? $first->getRawOriginal('estatus_vaucher'),
                'observacion_anulacion' => null,
            ];

            $this->commissionForm = [
                'porcentaje_comision' => $first->porcentaje_comision,
            ];
        }
    }

    /**
     * @return Collection<int, TdevReport>
     */
    private function getResultRecords(): Collection
    {
        if ($this->resultRecordIds === []) {
            return collect();
        }

        return TdevReport::query()
            ->whereIn('id', $this->resultRecordIds)
            ->get();
    }

    /**
     * @return array<string, string>
     */
    public function formaPagoOptions(): array
    {
        return FormaPago::options();
    }

    /**
     * @return array<string, string>
     */
    public function statusPagoOptions(): array
    {
        return StatusPago::options();
    }

    /**
     * @return array<string, string>
     */
    public function statusVaucherOptions(): array
    {
        return StatusVaucher::options();
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function auditAction(string $action, array $details = []): void
    {
        $user = Auth::user();
        $traceId = (string) Str::uuid();

        $payload = [
            'trace_id' => $traceId,
            'component' => self::class,
            'user' => [
                'id' => $user?->id,
                'email' => $user?->email,
                'name' => $user?->name,
            ],
            'url' => request()->fullUrl(),
            'details' => $details,
        ];

        $encodedPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        LogController::log(
            (int) ($user?->id ?? 0),
            $action,
            'administration.compensacion-vaucher',
            Str::limit((string) $encodedPayload, 2000),
        );
    }
}
