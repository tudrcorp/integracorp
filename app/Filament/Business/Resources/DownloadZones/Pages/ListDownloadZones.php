<?php

namespace App\Filament\Business\Resources\DownloadZones\Pages;

use App\Filament\Business\Resources\DownloadZones\DownloadZoneResource;
use App\Models\DownloadZone;
use App\Models\Zone;
use App\Support\Filament\DownloadZoneTabIcons;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListDownloadZones extends ListRecords
{
    protected static string $resource = DownloadZoneResource::class;

    protected static ?string $title = 'Zona de descarga';

    protected static ?string $subtitle = 'Aquí puedes gestionar los recursos disponibles para los agentes';

    public ?string $activeTab = null;

    public function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Volver')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(DownloadZoneResource::getUrl()),
            Action::make('editOrder')
                ->label('Editar orden')
                ->icon('heroicon-o-bars-3')
                ->color('gray')
                ->visible(fn (): bool => filled($this->getActiveZoneId()))
                ->modalHeading('Reordenar documentos')
                ->modalDescription('Arrastra y suelta para cambiar el orden. Al guardar, el orden se aplicará en esta pestaña.')
                ->modalSubmitActionLabel('Guardar orden')
                ->form(fn (): array => [
                    Repeater::make('items')
                        ->label('')
                        ->schema([
                            Hidden::make('id'),
                            TextInput::make('description')
                                ->label('Documento')
                                ->disabled()
                                ->dehydrated(false),
                        ])
                        ->reorderable()
                        ->reorderableWithButtons()
                        ->columns(1)
                        ->addable(false)
                        ->deletable(false),
                ])
                ->fillForm(function (): array {
                    $zoneId = $this->getActiveZoneId();

                    if (! $zoneId) {
                        return [
                            'items' => [],
                        ];
                    }

                    $items = DownloadZone::query()
                        ->where('zone_id', $zoneId)
                        ->orderBy('position')
                        ->orderBy('id')
                        ->get(['id', 'description'])
                        ->map(fn (DownloadZone $dz): array => [
                            'id' => $dz->id,
                            'description' => (string) $dz->description,
                        ])
                        ->all();

                    return [
                        'items' => $items,
                    ];
                })
                ->action(function (array $data): void {
                    $zoneId = $this->getActiveZoneId();

                    if (! $zoneId) {
                        return;
                    }

                    $ids = collect($data['items'] ?? [])
                        ->pluck('id')
                        ->filter()
                        ->values();

                    $records = DownloadZone::query()
                        ->where('zone_id', $zoneId)
                        ->whereIn('id', $ids)
                        ->get(['id']);

                    $recordsById = $records->keyBy('id');

                    foreach ($ids as $index => $id) {
                        /** @var DownloadZone|null $record */
                        $record = $recordsById->get($id);

                        if (! $record) {
                            continue;
                        }

                        $record->update([
                            'position' => $index + 1,
                        ]);
                    }
                }),
            CreateAction::make()
                ->label('Cargar Documento')
                ->icon('heroicon-o-plus'),
        ];
    }

    public function getTabs(): array
    {
        $tabs = [];

        $zones = Zone::query()
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('zone')
                    ->orWhere('zone', '!=', 'Zona test');
            })
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('code')
                    ->orWhere('code', '!=', 'Zona test');
            })
            ->orderBy('position')
            ->get();

        foreach ($zones as $zone) {
            $label = filled($zone->zone) ? $zone->zone : ($zone->code ?: 'Zona #'.$zone->id);
            $zoneId = $zone->id;

            $tabs['zone_'.$zoneId] = Tab::make($label)
                ->icon(DownloadZoneTabIcons::forZone($zone))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('zone_id', $zoneId)->orderBy('position')->orderBy('id'))
                ->badge(DownloadZone::query()->where('zone_id', $zoneId)->count())
                ->badgeColor('success');
        }

        $tabs['todos'] = Tab::make('TODOS')
            ->icon(DownloadZoneTabIcons::forTodosTab());

        return $tabs;
    }

    private function getActiveZoneId(): ?int
    {
        $key = $this->activeTab;

        if (! filled($key)) {
            $key = array_key_first($this->getTabs());
        }

        if (! is_string($key) || ! str_starts_with($key, 'zone_')) {
            return null;
        }

        $id = (int) str_replace('zone_', '', $key);

        return $id > 0 ? $id : null;
    }
}
