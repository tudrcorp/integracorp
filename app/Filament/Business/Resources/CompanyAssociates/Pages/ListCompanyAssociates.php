<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\CompanyAssociates\Pages;

use App\Filament\Business\Resources\CompanyAssociates\CompanyAssociateResource;
use App\Filament\Business\Resources\CompanyAssociates\Tables\CompanyAssociatesTable;
use App\Models\CompanyResponsible;
use App\Support\Companies\CompanyAssociatesTableContext;
use App\Support\Filament\FilamentIosButton;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Url;

class ListCompanyAssociates extends ListRecords
{
    protected static string $resource = CompanyAssociateResource::class;

    #[Url(as: 'contextCompany')]
    public ?string $contextCompany = null;

    #[Url(as: 'contextResponsible')]
    public ?string $contextResponsible = null;

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
