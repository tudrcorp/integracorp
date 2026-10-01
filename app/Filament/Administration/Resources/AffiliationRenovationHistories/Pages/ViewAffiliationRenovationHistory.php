<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\AffiliationRenovationHistories\Pages;

use App\Filament\Administration\Resources\AffiliationRenovationHistories\AffiliationRenovationHistoryResource;
use App\Filament\Administration\Resources\Affiliations\AffiliationResource;
use App\Models\AffiliationRenovationHistory;
use App\Support\Filament\RecordPageHeader;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewAffiliationRenovationHistory extends ViewRecord
{
    protected static string $resource = AffiliationRenovationHistoryResource::class;

    private const WARNING_BUTTON_CLASS = 'aviso-btn-ios-warning shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    private const PRIMARY_BUTTON_CLASS = 'aviso-btn-ios-primary shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewAffiliation')
                ->label('Ver afiliación')
                ->icon('heroicon-o-user-group')
                ->color(self::PRIMARY_BUTTON_CLASS)
                ->url(fn (AffiliationRenovationHistory $record): string => AffiliationResource::getUrl('view', ['record' => $record->affiliation_id]))
                ->visible(fn (AffiliationRenovationHistory $record): bool => $record->affiliation_id > 0),
            Action::make('back')
                ->label('Volver')
                ->icon('heroicon-o-arrow-left')
                ->color('warning')
                ->url(AffiliationRenovationHistoryResource::getUrl())
                ->extraAttributes([
                    'class' => self::WARNING_BUTTON_CLASS,
                ]),
        ];
    }

    public function getTitle(): string|Htmlable
    {
        return 'Renovación aceptada '.($this->getRecord()->code_affiliation ?? '');
    }

    public function getHeading(): string|Htmlable
    {
        /** @var AffiliationRenovationHistory $record */
        $record = $this->getRecord();
        $record->loadMissing(['affiliation:id,full_name_ti', 'plan:id,description', 'previousPlan:id,description']);

        $previousPlan = $record->previousPlan?->description;
        $planChanged = filled($previousPlan) && $previousPlan !== $record->plan?->description;
        $period = filled($record->previous_effective_date) && filled($record->new_effective_date)
            ? $record->previous_effective_date.' → '.$record->new_effective_date
            : ($record->new_effective_date ?: null);

        return RecordPageHeader::render(
            eyebrow: 'Renovación aceptada · '.($record->code_affiliation ?? 'Sin código'),
            title: (string) ($record->affiliation?->full_name_ti ?: 'Titular no disponible'),
            status: RecordPageHeader::tag('ACEPTADA', RecordPageHeader::TONE_SUCCESS),
            chips: [
                $record->is_negotiation_candidate ? RecordPageHeader::tag('Candidata a negociación', RecordPageHeader::TONE_VIOLET) : null,
                $planChanged ? RecordPageHeader::tag('Cambio de plan', RecordPageHeader::TONE_WARNING) : null,
            ],
            facts: [
                'Aceptada el' => $record->accepted_at?->format('d/m/Y h:i A'),
                'Aceptada por' => $record->accepted_by,
                'Vigencia' => $period,
            ],
        );
    }
}
