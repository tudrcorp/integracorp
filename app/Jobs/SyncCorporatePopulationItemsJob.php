<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\CorporatePopulationUpdate;
use App\Models\CorporatePopulationUpdateItem as Item;
use App\Models\User;
use App\Support\AffiliationCorporates\Population\PopulationItemCorrection;
use App\Support\AffiliationCorporates\Population\PopulationUpdateUrls;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * «Sincronizar con el archivo» para lotes grandes de filas «Coincide».
 *
 * Cada ficha se corrige en su propia transacción (ver
 * {@see PopulationItemCorrection::syncWithFile()}), así que reintentarlo es
 * seguro: lo ya sincronizado vuelve como «sin cambios». Mientras corre, la
 * carga no se puede aprobar (marca en caché).
 */
class SyncCorporatePopulationItemsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    /**
     * @param  list<int>  $itemIds
     * @param  list<string>  $groups
     */
    public function __construct(
        public int $updateId,
        public array $itemIds,
        public array $groups,
        public string $note,
        public int $userId,
    ) {}

    public static function busyKey(int $updateId): string
    {
        return 'corporate-population-sync:'.$updateId;
    }

    public function handle(): void
    {
        $user = User::query()->find($this->userId);
        $update = CorporatePopulationUpdate::query()->find($this->updateId);

        try {
            if (! $user instanceof User || ! $update instanceof CorporatePopulationUpdate) {
                return;
            }

            $total = ['updated' => 0, 'unchanged' => 0, 'failed' => []];

            Item::query()
                ->where('corporate_population_update_id', $this->updateId)
                ->whereIn('id', $this->itemIds)
                ->orderBy('id')
                ->chunkById(PopulationItemCorrection::BULK_LIMIT, function ($items) use (&$total, $user): void {
                    $result = PopulationItemCorrection::syncWithFile($items->all(), $this->groups, $this->note, $user);
                    $total['updated'] += $result['updated'];
                    $total['unchanged'] += $result['unchanged'];
                    array_push($total['failed'], ...$result['failed']);
                });

            $url = PopulationUpdateUrls::detail($update);
            $failed = collect($total['failed'])->take(10)->map(fn (array $row): string => $row['name'].': '.$row['error'])->implode(' · ');

            $notification = Notification::make()
                ->title('Sincronización con el archivo terminada')
                ->body('Actualizadas: '.$total['updated'].' · Sin cambios: '.$total['unchanged'].' · Con error: '.count($total['failed']).($failed !== '' ? '. '.$failed : '.'))
                ->actions($url !== null ? [Action::make('open')->label('Ver la carga')->url($url)->button()] : []);

            (count($total['failed']) > 0 ? $notification->warning() : $notification->success())->sendToDatabase($user);
        } finally {
            Cache::forget(self::busyKey($this->updateId));
        }
    }

    public function failed(?Throwable $exception): void
    {
        Cache::forget(self::busyKey($this->updateId));

        $user = User::query()->find($this->userId);
        if ($user instanceof User) {
            Notification::make()
                ->title('La sincronización con el archivo se interrumpió')
                ->body('Las fichas ya sincronizadas quedaron guardadas y verificadas. Vuelva a lanzarla para terminar las demás: las que ya cuadran aparecerán como «sin cambios».')
                ->danger()
                ->sendToDatabase($user);
        }
    }
}
