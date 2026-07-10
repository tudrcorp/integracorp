<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\CompanyAssociates\Pages;

use App\Filament\Business\Resources\CompanyAssociates\CompanyAssociateResource;
use App\Filament\Business\Resources\CompanyAssociates\Tables\CompanyAssociatesTable;
use App\Models\CompanyAssociate;
use App\Models\CompanyResponsible;
use App\Support\Companies\CompanyAssociateDocumentsManualSender;
use App\Support\Companies\CompanyAssociatesTableContext;
use App\Support\Filament\FilamentIosButton;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Url;
use Throwable;

class ListCompanyAssociates extends ListRecords
{
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
