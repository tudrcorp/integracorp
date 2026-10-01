<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\RenovationCorporates\Pages;

use App\Filament\Administration\Resources\AffiliationCorporates\AffiliationCorporateResource;
use App\Filament\Administration\Resources\RenovationCorporates\RenovationCorporateResource;
use App\Models\RenovationCorporate;
use App\Support\Filament\RecordPageHeader;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewRenovationCorporate extends ViewRecord
{
    protected static string $resource = RenovationCorporateResource::class;

    private const WARNING_BUTTON_CLASS = 'aviso-btn-ios-warning shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    private const PRIMARY_BUTTON_CLASS = 'aviso-btn-ios-primary shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewAffiliationCorporate')
                ->label('Ver afiliación corporativa')
                ->icon('heroicon-o-user-group')
                ->color(self::PRIMARY_BUTTON_CLASS)
                ->url(fn (RenovationCorporate $record): string => AffiliationCorporateResource::getUrl('view', ['record' => $record->affiliation_corporate_id]))
                ->visible(fn (RenovationCorporate $record): bool => $record->affiliation_corporate_id > 0),
            Action::make('back')
                ->label('Volver')
                ->icon('heroicon-o-arrow-left')
                ->color('warning')
                ->url(RenovationCorporateResource::getUrl())
                ->extraAttributes([
                    'class' => self::WARNING_BUTTON_CLASS,
                ]),
        ];
    }

    public function getTitle(): string|Htmlable
    {
        return 'Renovación corporativa '.($this->getRecord()->code_affiliation ?? '');
    }

    public function getHeading(): string|Htmlable
    {
        /** @var RenovationCorporate $record */
        $record = $this->getRecord();
        $record->loadMissing(['affiliationCorporate:id,name_corporate', 'plan:id,description', 'previousPlan:id,description']);

        $previousPlan = $record->previousPlan?->description;
        $planChanged = filled($previousPlan) && $previousPlan !== $record->plan?->description;

        return RecordPageHeader::render(
            eyebrow: 'Renovación corporativa · '.($record->code_affiliation ?? 'Sin código'),
            title: (string) ($record->affiliationCorporate?->name_corporate ?: 'Empresa no disponible'),
            status: RecordPageHeader::statusFor($record->status),
            chips: [
                RecordPageHeader::remainingDaysTag($record->remaining_days),
                $record->is_negotiation_candidate ? RecordPageHeader::tag('Candidata a negociación', RecordPageHeader::TONE_VIOLET) : null,
                $planChanged ? RecordPageHeader::tag('Cambio de plan', RecordPageHeader::TONE_WARNING) : null,
            ],
            facts: [
                'Fecha de renovación' => $record->date_renewal?->format('d/m/Y'),
                'Plan' => $planChanged ? $previousPlan.' → '.$record->plan?->description : $record->plan?->description,
                'Frecuencia' => $record->payment_frequency,
            ],
        );
    }
}
