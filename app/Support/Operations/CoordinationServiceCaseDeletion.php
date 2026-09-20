<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\OperationCoordinationService;
use App\Models\TelemedicineCase;
use App\Services\TelemedicineCaseLogicalDeletionService;
use App\Support\Filament\BusinessFilamentActionAccess;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\Filament\FilamentIosButton;
use App\Support\SecurityAudit;
use App\Support\Telemedicine\Scopes\HideDeletedTelemedicineCasesScope;
use App\Support\Telemedicine\Scopes\HideDeletedTelemedicineCaseTracesScope;
use App\Support\Telemedicine\TelemedicineCaseDeletion;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;
use Throwable;

/**
 * Eliminación y restauración de casos desde el cuadro de control de servicios médicos.
 *
 * Reemplaza al borrado físico de Filament: el caso pasa a ELIMINADO, se oculta
 * con todas sus trazas y nada se borra de la base de datos. Solo la ve quien
 * tenga el permiso granular (por defecto, únicamente SUPERADMIN).
 */
final class CoordinationServiceCaseDeletion
{
    /**
     * Pestaña del cuadro de control que lista los casos eliminados.
     */
    public const DELETED_TAB = 'eliminados';

    public static function userCanDeleteCases(): bool
    {
        return BusinessFilamentActionAccess::userCan(
            BusinessFilamentActionPermissionRegistry::DELETE_TELEMEDICINE_CASE
        );
    }

    public static function makeDeleteBulkAction(): BulkAction
    {
        return BulkAction::make('delete_telemedicine_case')
            ->label('Eliminar caso')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->visible(fn (mixed $livewire): bool => self::userCanDeleteCases() && ! self::viewingDeletedTab($livewire))
            ->modalHeading('Eliminar caso de servicios médicos')
            ->modalDescription(fn (EloquentCollection|Collection $records): HtmlString => self::deletionModalDescription($records))
            ->modalIcon(Heroicon::OutlinedTrash)
            ->modalIconColor('danger')
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('Sí, eliminar el caso')
            ->modalCancelActionLabel('Cancelar')
            ->deselectRecordsAfterCompletion()
            ->closeModalByClickingAway(false)
            ->modalSubmitAction(fn (Action $action): Action => self::styleButton($action, 'danger'))
            ->modalCancelAction(fn (Action $action): Action => self::styleButton($action, 'gray'))
            ->form([
                Textarea::make('deletion_reason')
                    ->label('Motivo de la eliminación')
                    ->placeholder('Explique por qué se elimina el caso (ej.: caso duplicado, registrado por error, paciente equivocado…)')
                    ->helperText('Campo obligatorio. Mínimo 10 caracteres. Queda en la bitácora del caso y en el log de seguridad.')
                    ->required()
                    ->minLength(10)
                    ->maxLength(5000)
                    ->rows(5)
                    ->columnSpanFull()
                    ->validationMessages([
                        'required' => 'Debe indicar el motivo de la eliminación.',
                        'minLength' => 'El motivo debe tener al menos 10 caracteres.',
                    ]),
            ])
            ->action(function (EloquentCollection|Collection $records, array $data): void {
                self::runOverCases(
                    $records,
                    (string) ($data['deletion_reason'] ?? ''),
                    deleting: true,
                );
            });
    }

    public static function makeRestoreBulkAction(): BulkAction
    {
        return BulkAction::make('restore_telemedicine_case')
            ->label('Restaurar caso')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('success')
            ->visible(fn (mixed $livewire): bool => self::userCanDeleteCases() && self::viewingDeletedTab($livewire))
            ->modalHeading('Restaurar caso eliminado')
            ->modalDescription(fn (EloquentCollection|Collection $records): HtmlString => self::restorationModalDescription($records))
            ->modalIcon(Heroicon::OutlinedArrowUturnLeft)
            ->modalIconColor('success')
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('Sí, restaurar el caso')
            ->modalCancelActionLabel('Cancelar')
            ->deselectRecordsAfterCompletion()
            ->closeModalByClickingAway(false)
            ->modalSubmitAction(fn (Action $action): Action => self::styleButton($action, 'success'))
            ->modalCancelAction(fn (Action $action): Action => self::styleButton($action, 'gray'))
            ->form([
                Textarea::make('restoration_reason')
                    ->label('Motivo de la restauración')
                    ->placeholder('Explique por qué se restaura el caso (ej.: eliminado por error, el paciente sí corresponde…)')
                    ->helperText('Campo obligatorio. Mínimo 10 caracteres. Queda en la bitácora del caso y en el log de seguridad.')
                    ->required()
                    ->minLength(10)
                    ->maxLength(5000)
                    ->rows(5)
                    ->columnSpanFull()
                    ->validationMessages([
                        'required' => 'Debe indicar el motivo de la restauración.',
                        'minLength' => 'El motivo debe tener al menos 10 caracteres.',
                    ]),
            ])
            ->action(function (EloquentCollection|Collection $records, array $data): void {
                self::runOverCases(
                    $records,
                    (string) ($data['restoration_reason'] ?? ''),
                    deleting: false,
                );
            });
    }

    /**
     * Casos distintos detrás de los servicios seleccionados.
     *
     * La tabla lista servicios, no casos: seleccionar dos filas del mismo caso
     * debe eliminarlo una sola vez. El scope que oculta los eliminados se retira
     * aquí a propósito, porque la restauración trabaja justamente sobre ellos.
     *
     * @param  EloquentCollection<int, mixed>|Collection<int, mixed>  $records
     * @return Collection<int, TelemedicineCase>
     */
    public static function casesFromRecords(EloquentCollection|Collection $records): Collection
    {
        $caseIds = Collection::make($records)
            ->filter(fn (mixed $record): bool => $record instanceof OperationCoordinationService)
            ->map(fn (OperationCoordinationService $service): ?int => filled($service->telemedicine_case_id)
                ? (int) $service->telemedicine_case_id
                : null)
            ->filter()
            ->unique()
            ->values();

        if ($caseIds->isEmpty()) {
            return Collection::make();
        }

        return TelemedicineCase::query()
            ->withoutGlobalScope(HideDeletedTelemedicineCasesScope::class)
            ->whereIn('id', $caseIds->all())
            ->orderBy('code')
            ->get()
            ->pipe(fn (EloquentCollection $cases): Collection => Collection::make($cases->all()));
    }

    /**
     * @param  EloquentCollection<int, mixed>|Collection<int, mixed>  $records
     */
    private static function runOverCases(EloquentCollection|Collection $records, string $reason, bool $deleting): void
    {
        if (! self::userCanDeleteCases()) {
            Notification::make()
                ->title('Acción no autorizada')
                ->body('No tiene permiso para eliminar o restaurar casos de servicios médicos.')
                ->danger()
                ->send();

            return;
        }

        $cases = self::casesFromRecords($records);

        if ($cases->isEmpty()) {
            Notification::make()
                ->title('Sin casos seleccionados')
                ->body('Seleccione al menos un servicio con caso de telemedicina vinculado.')
                ->warning()
                ->send();

            return;
        }

        $service = app(TelemedicineCaseLogicalDeletionService::class);
        $processed = 0;
        /** @var list<string> $skipped */
        $skipped = [];

        foreach ($cases as $case) {
            try {
                $deleting
                    ? $service->delete($case, $reason)
                    : $service->restore($case, $reason);

                $processed++;
            } catch (InvalidArgumentException $exception) {
                $skipped[] = $exception->getMessage();
            } catch (Throwable $exception) {
                Log::error('CoordinationServiceCaseDeletion: error', [
                    'telemedicine_case_id' => $case->id,
                    'deleting' => $deleting,
                    'message' => $exception->getMessage(),
                ]);

                SecurityAudit::log(
                    $deleting
                        ? 'AUDIT_TELEMEDICINE_CASE_LOGICAL_DELETION_FAILED'
                        : 'AUDIT_TELEMEDICINE_CASE_LOGICAL_RESTORATION_FAILED',
                    $deleting
                        ? 'operations.coordination-services.delete-case'
                        : 'operations.coordination-services.restore-case',
                    [
                        'telemedicine_case_id' => (int) $case->id,
                        'telemedicine_case_code' => (string) $case->code,
                        'error' => $exception->getMessage(),
                    ]
                );

                $skipped[] = 'El caso '.$case->code.' no pudo procesarse por un error inesperado.';
            }
        }

        self::notifyResult($processed, $skipped, $deleting);
    }

    /**
     * @param  list<string>  $skipped
     */
    private static function notifyResult(int $processed, array $skipped, bool $deleting): void
    {
        if ($processed === 0) {
            Notification::make()
                ->title($deleting ? 'No se eliminó ningún caso' : 'No se restauró ningún caso')
                ->body(implode(' ', $skipped) !== '' ? implode(' ', $skipped) : 'No hubo casos válidos para procesar.')
                ->warning()
                ->send();

            return;
        }

        $title = $deleting
            ? ($processed === 1 ? 'Caso eliminado' : 'Casos eliminados')
            : ($processed === 1 ? 'Caso restaurado' : 'Casos restaurados');

        $body = $deleting
            ? ($processed === 1
                ? 'El caso quedó en estatus ELIMINADO y sus trazas ya no se muestran. Nada se borró de la base de datos.'
                : "Se eliminaron {$processed} casos. Quedaron en estatus ELIMINADO y sus trazas ya no se muestran.")
            : ($processed === 1
                ? 'El caso volvió a su estatus anterior y sus trazas son visibles de nuevo.'
                : "Se restauraron {$processed} casos con su estatus anterior.");

        if ($skipped !== []) {
            $body .= ' No se procesaron: '.implode(' ', $skipped);
        }

        Notification::make()
            ->title($title)
            ->body($body)
            ->success()
            ->send();
    }

    /**
     * @param  EloquentCollection<int, mixed>|Collection<int, mixed>  $records
     */
    private static function deletionModalDescription(EloquentCollection|Collection $records): HtmlString
    {
        $cases = self::casesFromRecords($records);

        if ($cases->isEmpty()) {
            return new HtmlString(
                '<p class="text-sm text-gray-600 dark:text-gray-300">Seleccione al menos un servicio con caso de telemedicina vinculado.</p>'
            );
        }

        $service = app(TelemedicineCaseLogicalDeletionService::class);
        $rows = [];

        foreach ($cases as $case) {
            if (! TelemedicineCaseDeletion::canBeDeleted($case)) {
                $rows[] = '<li class="text-amber-700 dark:text-amber-300"><span class="font-semibold">'
                    .e((string) $case->code).'</span> — '.e((string) TelemedicineCaseDeletion::blockedReason($case)).'</li>';

                continue;
            }

            $summary = $service->traceSummary($case);

            $detail = 'servicios: '.$summary['services']
                .' · órdenes: '.$summary['service_orders']
                .' · consultas: '.$summary['consultations']
                .' · documentos: '.$summary['documents'];

            $warnings = [];

            if (TelemedicineCaseDeletion::isSensitive($case)) {
                $warnings[] = 'el caso tiene ALTA MÉDICA y sus documentos ya se emitieron al paciente';
            }

            if ($summary['billed_service_orders'] > 0) {
                $warnings[] = $summary['billed_service_orders'].' orden(es) con factura registrada';
            }

            if ($summary['inventory_movements'] > 0) {
                $warnings[] = $summary['inventory_movements'].' movimiento(s) de inventario que seguirán descontando stock';
            }

            $rows[] = '<li><span class="font-semibold text-gray-900 dark:text-white">'.e((string) $case->code).'</span> · '
                .e((string) ($case->patient_name ?? '—')).'<br><span class="text-xs text-gray-500 dark:text-gray-400">'.e($detail).'</span>'
                .($warnings === []
                    ? ''
                    : '<br><span class="text-xs font-semibold text-amber-700 dark:text-amber-300">Atención: '.e(implode(' · ', $warnings)).'</span>')
                .'</li>';
        }

        return new HtmlString(
            '<div class="space-y-3 text-sm text-gray-600 dark:text-gray-300">'
            .'<p>El caso pasará a estatus <span class="font-semibold text-gray-900 dark:text-white">ELIMINADO</span> y dejará de verse en todo el sistema junto con sus servicios, órdenes, consultas, documentos y bitácora. '
            .'<span class="font-semibold text-gray-900 dark:text-white">Ningún registro se borra de la base de datos</span>: el caso puede restaurarse desde la pestaña ELIMINADOS.</p>'
            .'<p>Los cupos clínicos consumidos por este caso se devuelven al afiliado. Los movimientos de inventario no se revierten: la salida física ya ocurrió.</p>'
            .'<ul class="list-disc space-y-2 ps-5">'.implode('', $rows).'</ul>'
            .'</div>'
        );
    }

    /**
     * @param  EloquentCollection<int, mixed>|Collection<int, mixed>  $records
     */
    private static function restorationModalDescription(EloquentCollection|Collection $records): HtmlString
    {
        $cases = self::casesFromRecords($records);

        if ($cases->isEmpty()) {
            return new HtmlString(
                '<p class="text-sm text-gray-600 dark:text-gray-300">Seleccione al menos un servicio de un caso eliminado.</p>'
            );
        }

        $rows = [];

        foreach ($cases as $case) {
            if (! TelemedicineCaseDeletion::isDeleted($case)) {
                $rows[] = '<li class="text-amber-700 dark:text-amber-300"><span class="font-semibold">'
                    .e((string) $case->code).'</span> — el caso no está eliminado.</li>';

                continue;
            }

            $rows[] = '<li><span class="font-semibold text-gray-900 dark:text-white">'.e((string) $case->code).'</span> · '
                .e((string) ($case->patient_name ?? '—'))
                .'<br><span class="text-xs text-gray-500 dark:text-gray-400">Volverá al estatus '
                .e((string) ($case->deletion_status_before ?: 'EN SEGUIMIENTO'))
                .' · eliminado por '.e((string) ($case->deleted_by_name ?? '—')).'</span></li>';
        }

        return new HtmlString(
            '<div class="space-y-3 text-sm text-gray-600 dark:text-gray-300">'
            .'<p>El caso volverá a su estatus anterior y sus trazas serán visibles de nuevo en todos los paneles.</p>'
            .'<p>Los cupos clínicos que se devolvieron al eliminar <span class="font-semibold text-gray-900 dark:text-white">no se vuelven a descontar</span> automáticamente: si corresponde, verifíquelos con el equipo clínico.</p>'
            .'<ul class="list-disc space-y-2 ps-5">'.implode('', $rows).'</ul>'
            .'</div>'
        );
    }

    /**
     * Servicios de coordinación cuyos casos están eliminados, para la pestaña ELIMINADOS.
     *
     * @param  Builder<OperationCoordinationService>  $query
     * @return Builder<OperationCoordinationService>
     */
    public static function applyDeletedCasesScope(Builder $query): Builder
    {
        return $query
            ->withoutGlobalScope(HideDeletedTelemedicineCaseTracesScope::class)
            ->whereExists(function (QueryBuilder $sub): void {
                $sub->select(DB::raw(1))
                    ->from('telemedicine_cases')
                    ->whereColumn('telemedicine_cases.id', 'operation_coordination_services.telemedicine_case_id')
                    ->where('telemedicine_cases.status', TelemedicineCaseDeletion::STATUS);
            });
    }

    /**
     * La restauración solo aparece en la pestaña ELIMINADOS, y la eliminación
     * en las demás: así ninguna de las dos se ofrece sobre filas donde no aplica.
     */
    public static function viewingDeletedTab(mixed $livewire): bool
    {
        $activeTab = data_get($livewire, 'activeTab');

        return is_string($activeTab) && $activeTab === self::DELETED_TAB;
    }

    private static function styleButton(Action $action, string $color): Action
    {
        return $action
            ->color($color)
            ->extraAttributes([
                'class' => FilamentIosButton::extraClassForFilamentColor($color),
            ]);
    }
}
