<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\AffiliationRenovationHistories\Pages;

use App\Filament\Administration\Resources\AffiliationRenovationHistories\AffiliationRenovationHistoryResource;
use App\Filament\Administration\Resources\Affiliations\AffiliationResource;
use App\Models\AffiliationRenovationHistory;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

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
        /** @var AffiliationRenovationHistory $record */
        $record = $this->getRecord();
        $code = (string) ($record->code_affiliation ?? '—');
        $acceptedAt = $record->accepted_at?->format('d/m/Y H:i') ?? '—';

        return new HtmlString(
            '<div style="display:flex;flex-direction:column;gap:6px;padding:10px 0;">'
            .'<span class="text-sm font-bold uppercase tracking-tight text-gray-900 dark:text-white">'
            .'Renovación aceptada · '.e($code)
            .'</span>'
            .'<span class="text-lg font-semibold text-gray-700 dark:text-gray-200">'
            .e($acceptedAt)
            .'</span>'
            .'</div>'
        );
    }
}
