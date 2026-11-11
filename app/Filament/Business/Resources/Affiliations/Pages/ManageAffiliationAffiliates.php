<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\Affiliations\Pages;

use App\Filament\Business\Resources\Affiliations\AffiliationResource;
use App\Filament\Business\Resources\Affiliations\RelationManagers\AffiliatesRelationManager;
use App\Models\Affiliate;
use App\Models\Affiliation;
use Filament\Actions\Action;
use Filament\Panel;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * «Ver afiliado(s)»: el grupo familiar de una afiliación individual con la misma
 * gestión que en la edición, sin abrir el formulario de la afiliación.
 */
class ManageAffiliationAffiliates extends Page
{
    use InteractsWithRecord;

    protected static string $resource = AffiliationResource::class;

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

        return AffiliationResource::canAccess()
            && (! $record instanceof Affiliation || AffiliationResource::canEdit($record));
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
        return (string) ($this->affiliation()->full_name_ti ?: '');
    }

    public function affiliation(): Affiliation
    {
        /** @var Affiliation $record */
        $record = $this->getRecord();

        return $record;
    }

    /**
     * @return array{status: string, items: list<array{label: string, value: string, hint?: string|null}>, warning: string|null}
     */
    public function summary(): array
    {
        $record = $this->affiliation()->loadMissing(['agency:id,code,name_corporative', 'agent:id,name,code_agent', 'plan:id,description', 'coverage:id,price']);
        $active = Affiliate::query()->where('affiliation_id', $record->getKey())->where('status', 'ACTIVO')->count();
        $money = static fn (mixed $value): string => 'US$ '.number_format((float) $value, 2, ',', '.');

        return [
            'status' => (string) $record->status,
            'items' => [
                ['label' => 'Titular', 'value' => (string) ($record->full_name_ti ?: '—'), 'hint' => filled($record->nro_identificacion_ti) ? 'C.I. '.trim(($record->document_type_ti ? $record->document_type_ti.'-' : '').$record->nro_identificacion_ti) : null],
                ['label' => 'Pagador', 'value' => (string) ($record->full_name_payer ?: '—'), 'hint' => filled($record->nro_identificacion_payer) ? 'C.I. '.$record->nro_identificacion_payer : null],
                ['label' => 'Plan', 'value' => (string) ($record->plan?->description ?: '—'), 'hint' => $record->coverage ? 'Cobertura US$ '.number_format((float) $record->coverage->price, 0, ',', '.') : null],
                ['label' => 'Frecuencia de pago', 'value' => (string) ($record->payment_frequency ?: '—')],
                ['label' => 'Vigencia desde', 'value' => self::date($record->effective_date)],
                ['label' => 'Afiliados activos', 'value' => $active.' pers.'],
                ['label' => 'Tarifa anual', 'value' => $money($record->fee_anual)],
                ['label' => 'Cuota', 'value' => $money($record->total_amount)],
                ['label' => 'Agencia', 'value' => (string) ($record->agency?->name_corporative ?: ($record->code_agency ?: '—')), 'hint' => $record->agency?->code],
                ['label' => 'Agente', 'value' => (string) ($record->agent?->name ?: '—')],
            ],
            'warning' => null,
        ];
    }

    /**
     * @return class-string
     */
    public function relationManager(): string
    {
        return AffiliatesRelationManager::class;
    }

    /**
     * La tabla se comporta como en la edición (misma clase de página dueña).
     *
     * @return class-string
     */
    public function relationManagerPageClass(): string
    {
        return EditAffiliation::class;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Afiliaciones')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(AffiliationResource::getUrl('index')),
            Action::make('edit')
                ->label('Editar afiliación')
                ->icon(Heroicon::OutlinedPencilSquare)
                ->url(fn (): string => AffiliationResource::getUrl('edit', ['record' => $this->affiliation()])),
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
