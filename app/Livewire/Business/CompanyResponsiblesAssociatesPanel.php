<?php

declare(strict_types=1);

namespace App\Livewire\Business;

use App\Models\Company;
use App\Models\CompanyAssociate;
use App\Support\Companies\CompanyAssociateVoucherIlsDocumentsNotifier;
use App\Support\Companies\CompanyAssociateVoucherIlsUpdater;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Livewire\Component;

class CompanyResponsiblesAssociatesPanel extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    public int $companyId;

    /** @var array<int, int> */
    public array $expandedResponsibles = [];

    public function mount(int $companyId): void
    {
        $this->companyId = $companyId;
    }

    /**
     * @return Collection<int, \App\Models\CompanyResponsible>
     */
    public function getResponsiblesProperty(): Collection
    {
        return Company::query()
            ->with([
                'responsibles' => fn ($query) => $query
                    ->withCount('associates')
                    ->withSum('associates as associates_consumed_days_sum', 'registration_period_days')
                    ->with([
                        'state',
                        'zone',
                        'associates' => fn ($query) => $query->orderByDesc('registered_at'),
                    ]),
            ])
            ->findOrFail($this->companyId)
            ->responsibles;
    }

    public function toggleResponsible(int $responsibleId): void
    {
        if ($this->isResponsibleExpanded($responsibleId)) {
            $this->expandedResponsibles = array_values(array_filter(
                $this->expandedResponsibles,
                fn (int $id): bool => $id !== $responsibleId,
            ));

            return;
        }

        $this->expandedResponsibles[] = $responsibleId;
    }

    public function isResponsibleExpanded(int $responsibleId): bool
    {
        return in_array($responsibleId, $this->expandedResponsibles, true);
    }

    public function voucherIlsAction(): Action
    {
        return Action::make('voucherIls')
            ->label('Voucher ILS')
            ->icon(Heroicon::Ticket)
            ->color('info')
            ->modalIcon(Heroicon::OutlinedTicket)
            ->modalHeading(fn (array $arguments): string => 'Voucher ILS — '.$this->resolveAssociate($arguments)->full_name)
            ->modalDescription('Cargue o actualice el código, vigencia e imagen del voucher ILS del asociado.')
            ->modalWidth(Width::TwoExtraLarge)
            ->modalSubmitActionLabel('Guardar voucher')
            ->fillForm(fn (array $arguments): array => CompanyAssociateVoucherIlsUpdater::formDefaults($this->resolveAssociate($arguments)))
            ->form(CompanyAssociateVoucherIlsUpdater::formComponents(
                fn (): bool => blank($this->mountedAssociate()?->document_ils),
            ))
            ->action(function (array $arguments, array $data): void {
                $associate = $this->resolveAssociate($arguments);

                CompanyAssociateVoucherIlsUpdater::save($associate, $data);

                $userId = auth()->id();

                if (is_int($userId)) {
                    CompanyAssociateVoucherIlsDocumentsNotifier::queueGenerationAfterVoucherSave($associate->getKey(), $userId);
                }
            })
            ->successNotification(function (array $arguments): Notification {
                $associate = $this->resolveAssociate($arguments);

                return Notification::make()
                    ->success()
                    ->title('Voucher ILS guardado')
                    ->body('El voucher de '.$associate->full_name.' se registró correctamente. La tarjeta y el QR se están generando en segundo plano.');
            });
    }

    public function render(): View
    {
        return view('livewire.business.company-responsibles-associates-panel', [
            'responsibles' => $this->responsibles,
        ]);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function resolveAssociate(array $arguments): CompanyAssociate
    {
        $associateId = (int) ($arguments['associateId'] ?? 0);

        $associate = CompanyAssociate::query()
            ->where('company_id', $this->companyId)
            ->whereKey($associateId)
            ->first();

        if ($associate === null) {
            throw (new ModelNotFoundException)->setModel(CompanyAssociate::class, [$associateId]);
        }

        return $associate;
    }

    private function mountedAssociate(): ?CompanyAssociate
    {
        $arguments = $this->getMountedAction()?->getArguments() ?? [];

        if (! is_array($arguments) || blank($arguments['associateId'] ?? null)) {
            return null;
        }

        return CompanyAssociate::query()
            ->where('company_id', $this->companyId)
            ->whereKey((int) $arguments['associateId'])
            ->first();
    }
}
