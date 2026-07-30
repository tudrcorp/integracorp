<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\RenovationCorporates\Pages;

use App\Filament\Administration\Resources\AffiliationCorporates\AffiliationCorporateResource;
use App\Filament\Administration\Resources\RenovationCorporates\RenovationCorporateResource;
use App\Models\RenovationCorporate;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

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
        /** @var RenovationCorporate $record */
        $record = $this->getRecord();
        $code = (string) ($record->code_affiliation ?? '—');
        $status = (string) ($record->status ?? '—');
        $days = $record->remaining_days;
        $daysLabel = $days === null ? '—' : (string) $days.' días';

        return new HtmlString(
            '<div style="display:flex;flex-direction:column;gap:6px;padding:10px 0;">'
            .'<span class="text-sm font-bold uppercase tracking-tight text-gray-900 dark:text-white">'
            .'Renovación corporativa · '.e($code)
            .'</span>'
            .'<span class="text-lg font-semibold text-gray-700 dark:text-gray-200">'
            .e($status).' · '.e($daysLabel)
            .'</span>'
            .'</div>'
        );
    }
}
