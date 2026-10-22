<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\AffiliationDateInconsistencies\Pages;

use App\Filament\Administration\Resources\AffiliationDateInconsistencies\AffiliationDateInconsistencyResource;
use App\Jobs\ScanAffiliationDateInconsistenciesJob;
use App\Models\AffiliationDateInconsistency;
use App\Support\Affiliations\DateInconsistencies\DateInconsistencyScanner;
use App\Support\Filament\BusinessFilamentActionAccess;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class ListAffiliationDateInconsistencies extends ListRecords
{
    protected static string $resource = AffiliationDateInconsistencyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('scanAll')
                ->label('Revisar todas ahora')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Revisar todas las afiliaciones activas')
                ->modalDescription('La revisión corre en segundo plano (también se hace sola todos los días a las 6:45). La lista se actualiza al terminar; puede seguir trabajando.')
                ->modalSubmitActionLabel('Revisar en segundo plano')
                ->visible(fn (): bool => BusinessFilamentActionAccess::userCan(BusinessFilamentActionPermissionRegistry::REPAIR_DATE_INCONSISTENCIES))
                ->action(function (): void {
                    $probe = Cache::lock(DateInconsistencyScanner::LOCK_KEY, 5);

                    if (! $probe->get()) {
                        Notification::make()->title('Ya hay una revisión en curso')->body('Espere a que termine y recargue la lista.')->warning()->send();

                        return;
                    }

                    $probe->release();
                    ScanAffiliationDateInconsistenciesJob::dispatch()->onQueue('system');

                    Notification::make()
                        ->title('Revisión en marcha')
                        ->body('Se revisan todas las afiliaciones activas en segundo plano. Recargue la lista en unos minutos.')
                        ->success()
                        ->send();
                }),
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $tab = fn (string $label, ?string $status): Tab => Tab::make($label)
            ->modifyQueryUsing(fn (Builder $query): Builder => $status === null ? $query : $query->where('status', $status));

        return [
            'open' => $tab('Abiertas', AffiliationDateInconsistency::STATUS_OPEN)
                ->badge(fn (): int => AffiliationDateInconsistency::query()->where('status', AffiliationDateInconsistency::STATUS_OPEN)->count())
                ->badgeColor('danger'),
            'repaired' => $tab('Reparadas', AffiliationDateInconsistency::STATUS_REPAIRED),
            'dismissed' => $tab('No son inconsistencia', AffiliationDateInconsistency::STATUS_DISMISSED),
            'resolved' => $tab('Resueltas solas', AffiliationDateInconsistency::STATUS_RESOLVED),
            'all' => $tab('Todas', null),
        ];
    }
}
