<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\CompanyAssociates\Pages;

use App\Filament\Business\Resources\CompanyAssociates\CompanyAssociateResource;
use App\Filament\Business\Resources\CompanyAssociates\Tables\CompanyAssociatesTable;
use App\Jobs\NotifyCompanyAssociateIlsCoverageConfirmedJob;
use App\Models\CompanyAssociate;
use App\Models\CompanyResponsible;
use App\Support\Companies\CompanyAssociateDocumentsManualSender;
use App\Support\Companies\CompanyAssociatesTableContext;
use App\Support\Companies\CompanyAssociateVoucherIlsUpdater;
use App\Support\Filament\FilamentIosButton;
use App\Support\SecurityAudit;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Url;
use Throwable;

class ListCompanyAssociates extends ListRecords
{
    public const CONFIRM_ILS_COVERAGE_ACTION = 'confirmCompanyAssociateIlsCoverage';

    protected static string $resource = CompanyAssociateResource::class;

    #[Url(as: 'contextCompany')]
    public ?string $contextCompany = null;

    #[Url(as: 'contextResponsible')]
    public ?string $contextResponsible = null;

    /**
     * @var array{
     *     status: string,
     *     total: int,
     *     processed: int,
     *     percentage: int,
     *     current_name: string|null,
     *     sent: int,
     *     failed_messages: array<int, string>
     * }|null
     */
    public ?array $associateDocumentsBulkSendProgress = null;

    public function mount(): void
    {
        parent::mount();

        if (filled($this->contextResponsible) && blank($this->tableFilters['company_responsible_id']['value'] ?? null)) {
            $this->tableFilters ??= [];
            $this->tableFilters['company_responsible_id'] = [
                'value' => $this->contextResponsible,
            ];
        }

        if (filled($this->contextCompany) && blank($this->tableFilters['company_id']['value'] ?? null)) {
            $this->tableFilters ??= [];
            $this->tableFilters['company_id'] = [
                'value' => $this->contextCompany,
            ];
        }

        if ($this->isScopedToResponsible()) {
            $this->tableGrouping = CompanyAssociatesTableContext::GROUPING_RESPONSIBLE;
        }
    }

    public function table(Table $table): Table
    {
        return CompanyAssociatesTable::configure($table, [
            'scopedResponsible' => $this->isScopedToResponsible(),
            'scopedCompany' => filled($this->contextCompany),
        ]);
    }

    /**
     * Segundo paso de la carga del voucher ILS. La acción de la tabla ya validó el
     * formulario y, en lugar de guardar, remonta esta confirmación con los datos
     * como argumentos. Solo aquí se persiste y se notifica: la declaración del
     * analista es la que autoriza el aviso de cobertura.
     */
    protected function confirmCompanyAssociateIlsCoverageAction(): Action
    {
        return Action::make(self::CONFIRM_ILS_COVERAGE_ACTION)
            ->requiresConfirmation()
            ->modalIcon(Heroicon::OutlinedShieldCheck)
            ->modalIconColor('warning')
            ->modalHeading('Confirmar cobertura del cliente')
            ->modalDescription(fn (array $arguments): string => self::confirmIlsCoverageDescription($arguments))
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('Sí, el cliente está cubierto')
            ->modalCancelActionLabel('Volver')
            ->closeModalByClickingAway(false)
            ->modalSubmitAction(fn (Action $action): Action => $action->color('success'))
            ->action(function (Action $action): void {
                $this->handleIlsCoverageConfirmation($action->getArguments());
            });
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private static function confirmIlsCoverageDescription(array $arguments): string
    {
        $voucher = is_array($arguments['voucher'] ?? null) ? $arguments['voucher'] : [];

        $code = filled($voucher['vaucherIls'] ?? null) ? (string) $voucher['vaucherIls'] : '—';
        $dateInit = filled($voucher['dateInit'] ?? null) ? (string) $voucher['dateInit'] : '—';
        $dateEnd = filled($voucher['dateEnd'] ?? null) ? (string) $voucher['dateEnd'] : '—';

        return '¿Está seguro de haber realizado toda la gestión que garantiza que el cliente está cubierto? '
            .'Al confirmar se guardará el voucher '.$code.' con vigencia del '.$dateInit.' al '.$dateEnd
            .', y se notificará por correo y WhatsApp, con el voucher adjunto, a los destinatarios del centro de notificaciones. '
            .'Su confirmación queda registrada en las trazas de seguridad.';
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function handleIlsCoverageConfirmation(array $arguments): void
    {
        $associateId = (int) ($arguments['associateId'] ?? 0);
        $voucher = is_array($arguments['voucher'] ?? null) ? $arguments['voucher'] : [];

        $associate = $associateId > 0
            ? CompanyAssociate::query()->find($associateId)
            : null;

        if ($associate === null) {
            Notification::make()
                ->warning()
                ->title('No se encontró el asociado')
                ->body('El registro ya no existe. Vuelva a intentarlo desde la tabla.')
                ->send();

            return;
        }

        if ($voucher === []) {
            Notification::make()
                ->danger()
                ->title('No se pudo guardar el voucher')
                ->body('Se perdieron los datos del formulario. Vuelva a abrir «Voucher ILS» y cárguelos de nuevo.')
                ->send();

            return;
        }

        try {
            CompanyAssociateVoucherIlsUpdater::save($associate, $voucher);
        } catch (Throwable $throwable) {
            report($throwable);

            Notification::make()
                ->danger()
                ->title('No se pudo guardar el voucher')
                ->body($throwable->getMessage())
                ->send();

            return;
        }

        SecurityAudit::log('AUDIT_BUSINESS_COMPANY_ASSOCIATE_ILS_COVERAGE_CONFIRMED', 'company-associates.voucher-ils.coverage-confirmed', [
            'associate_id' => $associate->getKey(),
            'company_id' => $associate->company_id,
            'vaucher_ils' => $voucher['vaucherIls'] ?? null,
            'date_init' => $voucher['dateInit'] ?? null,
            'date_end' => $voucher['dateEnd'] ?? null,
        ]);

        // Los paneles corren la acción dentro de una transacción: sin `afterCommit()`
        // un worker podría tomar el job antes de que el voucher esté confirmado en la base.
        NotifyCompanyAssociateIlsCoverageConfirmedJob::dispatch((int) $associate->getKey())
            ->afterCommit();

        Notification::make()
            ->success()
            ->title('Cobertura confirmada')
            ->body('El voucher de '.$associate->full_name.' se registró y el estatus pasó a ACTIVO. La notificación con el voucher adjunto va en camino.')
            ->send();
    }

    /**
     * @param  array<int, int|string>  $associateIds
     */
    public function initAssociateDocumentsBulkSend(array $associateIds): void
    {
        $associateIds = array_values(array_unique(array_filter(
            array_map(intval(...), $associateIds),
            fn (int $id): bool => $id > 0,
        )));

        $this->associateDocumentsBulkSendProgress = [
            'status' => 'running',
            'total' => count($associateIds),
            'processed' => 0,
            'percentage' => 0,
            'current_name' => null,
            'sent' => 0,
            'failed_messages' => [],
        ];
    }

    /**
     * @return array{ok: bool, associate_id: int, name: string, message: string}
     */
    public function sendAssociateDocument(int $associateId): array
    {
        $associate = CompanyAssociate::query()->find($associateId);

        if ($associate === null) {
            $result = [
                'ok' => false,
                'associate_id' => $associateId,
                'name' => '—',
                'message' => 'No se encontró el asociado seleccionado.',
            ];

            $this->recordAssociateDocumentsBulkSendResult($result);

            return $result;
        }

        if ($this->associateDocumentsBulkSendProgress !== null) {
            $this->associateDocumentsBulkSendProgress['current_name'] = (string) $associate->full_name;
        }

        try {
            CompanyAssociateDocumentsManualSender::send($associate);

            $result = [
                'ok' => true,
                'associate_id' => $associateId,
                'name' => (string) $associate->full_name,
                'message' => 'Documentos enviados correctamente.',
            ];
        } catch (Throwable $exception) {
            $result = [
                'ok' => false,
                'associate_id' => $associateId,
                'name' => (string) $associate->full_name,
                'message' => $exception->getMessage(),
            ];
        }

        $this->recordAssociateDocumentsBulkSendResult($result);

        return $result;
    }

    public function finishAssociateDocumentsBulkSendFromProgress(): void
    {
        $progress = $this->associateDocumentsBulkSendProgress;

        if ($progress === null) {
            return;
        }

        $this->associateDocumentsBulkSendProgress['status'] = 'finished';
        $this->associateDocumentsBulkSendProgress['percentage'] = 100;
        $this->associateDocumentsBulkSendProgress['current_name'] = null;

        $this->finishAssociateDocumentsBulkSend(
            (int) ($progress['sent'] ?? 0),
            (int) ($progress['total'] ?? 0),
            $progress['failed_messages'] ?? [],
        );
    }

    public function resetAssociateDocumentsBulkSendProgress(): void
    {
        $this->associateDocumentsBulkSendProgress = null;
    }

    /**
     * @param  array{ok: bool, associate_id: int, name: string, message: string}  $result
     */
    private function recordAssociateDocumentsBulkSendResult(array $result): void
    {
        if ($this->associateDocumentsBulkSendProgress === null) {
            return;
        }

        $this->associateDocumentsBulkSendProgress['processed'] = (int) ($this->associateDocumentsBulkSendProgress['processed'] ?? 0) + 1;

        $total = max(1, (int) ($this->associateDocumentsBulkSendProgress['total'] ?? 1));
        $this->associateDocumentsBulkSendProgress['percentage'] = (int) round(
            ($this->associateDocumentsBulkSendProgress['processed'] / $total) * 100,
        );

        if ($result['ok']) {
            $this->associateDocumentsBulkSendProgress['sent'] = (int) ($this->associateDocumentsBulkSendProgress['sent'] ?? 0) + 1;
        } else {
            $this->associateDocumentsBulkSendProgress['failed_messages'][] = $result['name'].': '.$result['message'];
        }
    }

    /**
     * @param  array<int, string>  $failedMessages
     */
    public function finishAssociateDocumentsBulkSend(int $sentCount, int $totalCount, array $failedMessages): void
    {
        if ($sentCount > 0 && $failedMessages === []) {
            Notification::make()
                ->success()
                ->title('Documentos enviados')
                ->body($sentCount === 1
                    ? 'El carnet y el QR se enviaron correctamente al asociado seleccionado.'
                    : 'El carnet y el QR se enviaron correctamente a '.$sentCount.' asociados.')
                ->send();
        } elseif ($sentCount > 0) {
            Notification::make()
                ->warning()
                ->title('Envío parcial completado')
                ->body('Se enviaron '.$sentCount.' de '.$totalCount.' seleccionados.')
                ->send();
        } elseif ($failedMessages !== []) {
            Notification::make()
                ->danger()
                ->title('No se pudieron enviar los documentos')
                ->body(implode(' · ', $failedMessages))
                ->send();
        }

        $this->deselectAllTableRecords();
    }

    public function getTitle(): string|Htmlable
    {
        $responsible = $this->scopedResponsible();

        if ($responsible !== null) {
            return 'Asociados de '.$responsible->full_name;
        }

        return parent::getTitle();
    }

    public function getSubheading(): string|Htmlable|null
    {
        $responsible = $this->scopedResponsible();

        if ($responsible === null) {
            return 'Usuarios registrados públicamente bajo responsables de nuevos negocios.';
        }

        $companyName = e((string) ($responsible->company?->name ?? '—'));
        $identityCard = e((string) $responsible->identity_card);
        $associatesCount = (int) ($responsible->associates_count ?? $responsible->associates()->count());

        return new HtmlString(
            '<div class="flex flex-wrap items-center gap-2 text-sm text-gray-600 dark:text-gray-300">'
            .'<span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-700 dark:bg-white/10 dark:text-gray-200">'.$companyName.'</span>'
            .'<span>Cédula responsable: <strong>'.$identityCard.'</strong></span>'
            .'<span>·</span>'
            .'<span>'.$associatesCount.' asociado(s) en este grupo</span>'
            .'</div>'
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToCompany')
                ->label('Volver al negocio')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(fn (): string => CompanyAssociatesTableContext::companyViewUrl((int) $this->contextCompany))
                ->visible(fn (): bool => filled($this->contextCompany))
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('gray'),
                ]),
            Action::make('viewAllAssociates')
                ->label('Ver todos los asociados')
                ->icon(Heroicon::OutlinedUserGroup)
                ->color('info')
                ->url(CompanyAssociatesTableContext::indexUrl())
                ->visible(fn (): bool => $this->isScopedToResponsible())
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('info'),
                ]),
        ];
    }

    private function isScopedToResponsible(): bool
    {
        return filled($this->contextResponsible);
    }

    private function scopedResponsible(): ?CompanyResponsible
    {
        if (! $this->isScopedToResponsible()) {
            return null;
        }

        return CompanyResponsible::query()
            ->with(['company'])
            ->withCount('associates')
            ->find((int) $this->contextResponsible);
    }
}
