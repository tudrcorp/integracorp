<?php

namespace App\Filament\Marketing\Resources\MassNotifications\Pages;

use App\Filament\Marketing\Resources\MassNotifications\MassNotificationResource;
use App\Filament\Marketing\Resources\MassNotifications\Tables\MassNotificationsTable;
use App\Models\MassNotificationFolder;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View as IlluminateView;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;

class ListMassNotifications extends ListRecords
{
    protected static string $resource = MassNotificationResource::class;

    protected static ?string $title = 'Notificaciones Individuales y Masivas';

    #[Url]
    public ?int $folderId = null;

    public function openFolder(int $folderId): void
    {
        if (! MassNotificationFolder::query()->whereKey($folderId)->exists()) {
            return;
        }

        $this->folderId = $folderId;
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getTabsContentComponent(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
                View::make('filament.marketing.mass-notifications.folder-browser')
                    ->visible(fn (): bool => $this->folderId === null)
                    ->viewData(fn (): array => [
                        'folders' => self::foldersForBrowser(),
                    ]),
                EmbeddedTable::make()
                    ->visible(fn (): bool => $this->folderId !== null),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
            ]);
    }

    /**
     * Con la tabla oculta (vista de carpetas), el layout de Filament no incluye los modales de acciones de página;
     * la tabla los inyecta al renderizarse. Aquí repetimos solo el contenedor de modales en la raíz de carpetas.
     */
    public function getFooter(): ?IlluminateView
    {
        if ($this->folderId !== null) {
            return null;
        }

        return view('filament.marketing.mass-notifications.page-header-action-modals');
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query): Builder {
                if ($this->folderId === null) {
                    return $query->whereRaw('0 = 1');
                }

                if (! SchemaFacade::hasColumn('mass_notifications', 'mass_notification_folder_id')) {
                    return $query->whereRaw('0 = 1');
                }

                return $query->where('mass_notification_folder_id', $this->folderId);
            })
            ->when(
                $this->folderId !== null,
                fn (Table $t): Table => $t
                    ->heading(MassNotificationFolder::find($this->folderId)?->name ?? 'Carpeta')
                    ->description('Notificaciones en esta carpeta.')
            );
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back_to_folders')
                ->label('Carpetas')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->visible(fn (): bool => $this->folderId !== null)
                ->action(function (): void {
                    $this->folderId = null;
                }),
            CreateAction::make()
                ->label('Crear Notificación')
                ->icon('heroicon-s-squares-plus')
                ->visible(fn (): bool => $this->folderId === null),
            MassNotificationsTable::makeCreateFolderAction()
                ->visible(fn (): bool => $this->folderId === null),
        ];
    }

    /**
     * @return Collection<int, MassNotificationFolder>
     */
    protected static function foldersForBrowser(): Collection
    {
        $query = MassNotificationFolder::query()
            ->orderByDesc('is_default')
            ->orderBy('name');

        if (SchemaFacade::hasColumn('mass_notifications', 'mass_notification_folder_id')) {
            return $query
                ->withCount('massNotifications')
                ->with([
                    'massNotifications' => fn ($notificationQuery) => $notificationQuery
                        ->select(['id', 'mass_notification_folder_id', 'file', 'created_at'])
                        ->latest('created_at')
                        ->limit(3),
                ])
                ->get()
                ->each(function (MassNotificationFolder $folder): void {
                    $folder->setAttribute(
                        'preview_images',
                        $folder->massNotifications
                            ->pluck('file')
                            ->map(fn (?string $file): ?string => self::resolveNotificationFileUrl($file))
                            ->filter()
                            ->values()
                            ->all()
                    );
                });
        }

        return $query->get()->each(function (MassNotificationFolder $folder): void {
            $folder->setAttribute('mass_notifications_count', 0);
            $folder->setAttribute('preview_images', []);
        });
    }

    private static function resolveNotificationFileUrl(?string $filePath): ?string
    {
        if (blank($filePath)) {
            return null;
        }

        if (Str::startsWith($filePath, ['http://', 'https://', '/'])) {
            return $filePath;
        }

        return Storage::disk('public')->url($filePath);
    }
}
