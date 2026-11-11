<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\AffiliationCorporates\Pages;

use App\Filament\Business\Resources\AffiliationCorporates\AffiliationCorporateResource;
use App\Filament\Business\Resources\AffiliationCorporates\RelationManagers\CorporateAffiliatesQuickRelationManager;
use App\Models\AffiliationCorporate;
use App\Support\AffiliationCorporates\ActivePopulationTotals;
use Filament\Actions\Action;
use Filament\Panel;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * «Ver afiliado(s)»: los afiliados de una afiliación corporativa con la misma
 * gestión que en la edición, sin abrir el formulario de la afiliación.
 */
class ManageAffiliationCorporateAffiliates extends Page
{
    use InteractsWithRecord;

    protected static string $resource = AffiliationCorporateResource::class;

    protected static ?string $title = 'Afiliados';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.business.affiliations.manage-affiliates';

    protected Width|string|null $maxContentWidth = Width::Full;

    public static function getRoutePath(Panel $panel): string
    {
        return '/{record}/afiliados';
    }

    public static function canAccess(array $parameters = []): bool
    {
        $record = $parameters['record'] ?? null;

        return AffiliationCorporateResource::canAccess()
            && (! $record instanceof AffiliationCorporate || AffiliationCorporateResource::canEdit($record));
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        abort_unless(static::canAccess(['record' => $this->record]), 403);
    }

    public function getTitle(): string|Htmlable
    {
        return 'Afiliados · '.$this->affiliation()->code;
    }

    public function getSubheading(): string|Htmlable|null
    {
        return (string) ($this->affiliation()->name_corporate ?: '');
    }

    public function affiliation(): AffiliationCorporate
    {
        /** @var AffiliationCorporate $record */
        $record = $this->getRecord();

        return $record;
    }

    /**
     * @return array{status: string, items: list<array{label: string, value: string, hint?: string|null}>, warning: string|null}
     */
    public function summary(): array
    {
        $record = $this->affiliation()->loadMissing(['agency:id,code,name_corporative', 'agent:id,name,code_agent', 'accountManager:id,name', 'affiliationCorporatePlans.plan:id,description']);
        $totals = ActivePopulationTotals::compute($record);
        $money = static fn (float $value): string => 'US$ '.number_format($value, 2, ',', '.');
        $plans = $record->affiliationCorporatePlans->map(fn ($row): ?string => $row->plan?->description)->filter()->unique()->implode(', ');

        return [
            'status' => (string) $record->status,
            'items' => [
                ['label' => 'Empresa', 'value' => (string) ($record->name_corporate ?: '—'), 'hint' => filled($record->rif) ? 'RIF '.trim(($record->document_type_rif ? $record->document_type_rif.'-' : '').$record->rif) : null],
                ['label' => 'Plan(es)', 'value' => $plans !== '' ? $plans : '—'],
                ['label' => 'Frecuencia de pago', 'value' => (string) ($record->payment_frequency ?: '—')],
                ['label' => 'Vigencia desde', 'value' => self::date($record->effective_date)],
                ['label' => 'Población activa', 'value' => $totals['persons'].' pers.', 'hint' => $totals['without_fee'] > 0 ? $totals['without_fee'].' sin tarifa' : null],
                ['label' => 'Tarifa anual', 'value' => $money($totals['fee_anual'])],
                ['label' => 'Cuota', 'value' => $money($totals['total_amount'])],
                ['label' => 'Agencia', 'value' => (string) ($record->agency?->name_corporative ?: ($record->code_agency ?: '—')), 'hint' => $record->agency?->code],
                ['label' => 'Agente', 'value' => (string) ($record->agent?->name ?: '—')],
                ['label' => 'Ejecutivo de cuenta', 'value' => (string) ($record->accountManager?->name ?: '—')],
            ],
            'warning' => $totals['differs']
                ? 'Los totales guardados en la afiliación ('.$totals['stored']['persons'].' pers. · '.$money($totals['stored']['fee_anual']).') no cuadran con la población activa. La cobranza usa lo guardado: corríjalo con «Recalcular totales» en la tabla de afiliaciones.'
                : null,
        ];
    }

    /**
     * @return class-string
     */
    public function relationManager(): string
    {
        return CorporateAffiliatesQuickRelationManager::class;
    }

    /**
     * La tabla se comporta como en la edición (misma clase de página dueña).
     *
     * @return class-string
     */
    public function relationManagerPageClass(): string
    {
        return EditAffiliationCorporate::class;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Afiliaciones')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(AffiliationCorporateResource::getUrl('index')),
            Action::make('edit')
                ->label('Editar afiliación')
                ->icon(Heroicon::OutlinedPencilSquare)
                ->url(fn (): string => AffiliationCorporateResource::getUrl('edit', ['record' => $this->affiliation()])),
        ];
    }

    private static function date(mixed $value): string
    {
        if (blank($value)) {
            return '—';
        }

        try {
            return \Illuminate\Support\Carbon::parse((string) $value)->format('d/m/Y');
        } catch (\Throwable) {
            return (string) $value;
        }
    }
}
