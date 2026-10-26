<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\PersonIdentityFindings\Pages;

use App\Filament\Business\Resources\PersonIdentityFindings\PersonIdentityFindingResource;
use App\Jobs\ScanPersonIdentityFindingsJob;
use App\Models\PersonIdentityFinding;
use App\Support\Affiliations\PersonIdentity\PersonIdentityScanner;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class ListPersonIdentityFindings extends ListRecords
{
    protected static string $resource = PersonIdentityFindingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('scanAll')
                ->label('Revisar todas ahora')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Revisar todas las personas vigentes')
                ->modalDescription('La revisión corre en segundo plano (también se hace sola todos los días a las 6:50). La lista se actualiza al terminar; puede seguir trabajando.')
                ->modalSubmitActionLabel('Revisar en segundo plano')
                ->action(function (): void {
                    $probe = Cache::lock(PersonIdentityScanner::LOCK_KEY, 5);

                    if (! $probe->get()) {
                        Notification::make()->title('Ya hay una revisión en curso')->body('Espere a que termine y recargue la lista.')->warning()->send();

                        return;
                    }

                    $probe->release();
                    ScanPersonIdentityFindingsJob::dispatch()->onQueue('system');

                    Notification::make()
                        ->title('Revisión en marcha')
                        ->body('Se revisan los afiliados individuales, corporativos y de Nuevos Negocios en segundo plano. Recargue la lista en unos minutos.')
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
            'open' => $tab('Abiertos', PersonIdentityFinding::STATUS_OPEN)
                ->badge(fn (): int => PersonIdentityFinding::query()->where('status', PersonIdentityFinding::STATUS_OPEN)->count())
                ->badgeColor('danger'),
            'corrected' => $tab('Corregidos', PersonIdentityFinding::STATUS_CORRECTED),
            'dismissed' => $tab('No son duplicados', PersonIdentityFinding::STATUS_DISMISSED),
            'resolved' => $tab('Resueltos solos', PersonIdentityFinding::STATUS_RESOLVED),
            'all' => $tab('Todos', null),
        ];
    }
}
