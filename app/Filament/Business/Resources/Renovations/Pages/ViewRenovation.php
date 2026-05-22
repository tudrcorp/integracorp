<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\Renovations\Pages;

use App\Filament\Business\Resources\Affiliations\AffiliationResource;
use App\Filament\Business\Resources\Renovations\RenovationResource;
use App\Models\Renovation;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class ViewRenovation extends ViewRecord
{
    protected static string $resource = RenovationResource::class;

    private const WARNING_BUTTON_CLASS = 'aviso-btn-ios-warning shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    private const PRIMARY_BUTTON_CLASS = 'aviso-btn-ios-primary shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewAffiliation')
                ->label('Ver afiliación')
                ->icon('heroicon-o-user-group')
                ->color(self::PRIMARY_BUTTON_CLASS)
                ->url(fn (Renovation $record): string => AffiliationResource::getUrl('view', ['record' => $record->affiliation_id]))
                ->visible(fn (Renovation $record): bool => $record->affiliation_id > 0),
            Action::make('back')
                ->label('Volver')
                ->icon('heroicon-o-arrow-left')
                ->color('warning')
                ->url(RenovationResource::getUrl())
                ->extraAttributes([
                    'class' => self::WARNING_BUTTON_CLASS,
                ]),
        ];
    }

    public function getTitle(): string|Htmlable
    {
        /** @var Renovation $record */
        $record = $this->getRecord();
        $code = (string) ($record->code_affiliation ?? '—');
        $status = (string) ($record->status ?? '—');
        $days = $record->remaining_days;
        $daysLabel = $days === null ? '—' : (string) $days.' días';

        return new HtmlString(
            '<div style="display:flex;flex-direction:column;gap:6px;padding:10px 0;">'
            .'<span class="text-sm font-bold uppercase tracking-tight text-gray-900 dark:text-white">'
            .'Renovación · '.e($code)
            .'</span>'
            .'<span class="text-lg font-semibold text-gray-700 dark:text-gray-200">'
            .e($status).' · '.e($daysLabel)
            .'</span>'
            .'</div>'
        );
    }
}
