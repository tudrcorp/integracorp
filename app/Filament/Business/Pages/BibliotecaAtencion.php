<?php

declare(strict_types=1);

namespace App\Filament\Business\Pages;

use App\Models\CrmDocument;
use App\Models\CrmQuickReply;
use App\Models\User;
use App\Support\CrmInbox\CrmInboxAreas;
use App\Support\CrmInbox\CrmInboxLibrary;
use App\Support\CrmInbox\CrmInboxSupervisor;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Url;
use Throwable;

/**
 * Biblioteca de Atención WhatsApp: respuestas rápidas y documentos aprobados que el analista usa
 * desde el chat. La administra el supervisor del panel. Sin área: sirve en todas las bandejas.
 */
class BibliotecaAtencion extends Page implements HasTable
{
    use InteractsWithTable;

    /**
     * @var array<string, string>
     */
    public const TABS = [
        'respuestas' => 'Respuestas rápidas',
        'documentos' => 'Documentos',
    ];

    protected static ?string $navigationLabel = 'Biblioteca de atención';

    protected static ?string $title = 'Biblioteca de atención';

    protected static ?string $slug = 'biblioteca-atencion';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static ?int $navigationSort = 6;

    protected Width|string|null $maxContentWidth = Width::Full;

    protected string $view = 'filament.crm.pages.biblioteca-atencion';

    #[Url(as: 'tab')]
    public string $tab = 'respuestas';

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && CrmInboxSupervisor::can($user, static::panelKey());
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function panelKey(): string
    {
        return 'business';
    }

    public function boot(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public function mount(): void
    {
        if (! isset(self::TABS[$this->tab])) {
            $this->tab = 'respuestas';
        }
    }

    public function selectTab(string $tab): void
    {
        $this->tab = isset(self::TABS[$tab]) ? $tab : 'respuestas';
        $this->resetTable();
    }

    public function table(Table $table): Table
    {
        $documents = $this->tab === 'documentos';
        $areas = CrmInboxAreas::forPanel(static::panelKey());

        return $table
            ->query(fn (): Builder => ($documents ? CrmDocument::query() : CrmQuickReply::query())
                ->where(fn (Builder $query) => $query->whereNull('area')->orWhereIn('area', $areas)))
            ->defaultSort('sort')
            ->paginated([25, 50])
            ->emptyStateHeading($documents ? 'Sin documentos todavía' : 'Sin respuestas rápidas todavía')
            ->emptyStateDescription($documents
                ? 'Sube los PDF que el equipo envía a diario: cuadro de beneficios, red de clínicas, condiciones.'
                : 'Mientras no cargues ninguna, la bandeja usa las tres de respaldo. Carga las frases que el equipo repite.')
            ->columns($documents ? $this->documentColumns() : $this->replyColumns())
            ->headerActions([$documents ? $this->createDocumentAction() : $this->createReplyAction()])
            ->recordActions([
                $documents ? $this->editDocumentAction() : $this->editReplyAction(),
                $this->toggleAction(),
                $this->deleteAction($documents),
            ]);
    }

    /**
     * @return list<TextColumn|IconColumn>
     */
    private function replyColumns(): array
    {
        return [
            TextColumn::make('label')->label('Nombre')->searchable()->weight('bold'),
            TextColumn::make('body')->label('Texto')->searchable()->limit(90)->wrap(),
            TextColumn::make('area')->label('Área')->formatStateUsing(fn (?string $state): string => $state !== null ? CrmInboxAreas::label($state) : 'Todas')->placeholder('Todas'),
            TextColumn::make('uses')->label('Usos')->numeric()->sortable(),
            TextColumn::make('sort')->label('Orden')->numeric()->sortable(),
            IconColumn::make('active')->label('Activa')->boolean(),
        ];
    }

    /**
     * @return list<TextColumn|IconColumn>
     */
    private function documentColumns(): array
    {
        return [
            TextColumn::make('title')->label('Título')->searchable()->weight('bold'),
            TextColumn::make('description')->label('Descripción')->searchable()->limit(80)->wrap()->placeholder('—'),
            TextColumn::make('filename')->label('Archivo')->limit(40),
            TextColumn::make('size')->label('Tamaño')->formatStateUsing(fn (?int $state): string => number_format(max(1, intdiv((int) $state, 1024)), 0, ',', '.').' KB'),
            TextColumn::make('area')->label('Área')->formatStateUsing(fn (?string $state): string => $state !== null ? CrmInboxAreas::label($state) : 'Todas')->placeholder('Todas'),
            TextColumn::make('uses')->label('Envíos')->numeric()->sortable(),
            IconColumn::make('active')->label('Activo')->boolean(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function areaOptions(): array
    {
        $options = [];

        foreach (CrmInboxAreas::forPanel(static::panelKey()) as $area) {
            $options[$area] = CrmInboxAreas::label($area);
        }

        return $options;
    }

    /**
     * @return list<mixed>
     */
    private function replySchema(): array
    {
        return [
            TextInput::make('label')->label('Nombre corto')->helperText('Así la busca el analista al escribir «/».')->required()->maxLength(60),
            Textarea::make('body')->label('Texto')->required()->maxLength(1000)->rows(4)
                ->helperText('Puedes usar {cliente} (primer nombre del cliente), {analista} (tu nombre) y {propuesta} (número y monto de la última propuesta).'),
            Select::make('area')->label('Área')->options($this->areaOptions())->placeholder('Todas las áreas')->native(false),
            TextInput::make('sort')->label('Orden')->numeric()->minValue(0)->maxValue(999)->default(0)->helperText('Menor sale primero.'),
            Toggle::make('active')->label('Activa')->default(true),
        ];
    }

    /**
     * @return list<mixed>
     */
    private function documentSchema(bool $creating): array
    {
        return [
            TextInput::make('title')->label('Título')->helperText('Es el texto que ve el cliente con el PDF.')->required()->maxLength(80),
            TextInput::make('description')->label('Descripción para el analista')->maxLength(200),
            FileUpload::make('path')
                ->label('PDF')
                ->disk(CrmInboxLibrary::DISK)
                ->directory(CrmInboxLibrary::DIRECTORY)
                ->visibility('private')
                ->acceptedFileTypes(['application/pdf'])
                ->maxSize(intdiv(CrmInboxLibrary::MAX_BYTES, 1024))
                ->storeFileNamesIn('filename')
                ->required($creating)
                ->helperText('Solo PDF, hasta 8 MB.'.($creating ? '' : ' Sube uno nuevo solo si quieres reemplazarlo.')),
            Select::make('area')->label('Área')->options($this->areaOptions())->placeholder('Todas las áreas')->native(false),
            TextInput::make('sort')->label('Orden')->numeric()->minValue(0)->maxValue(999)->default(0),
            Toggle::make('active')->label('Activo')->default(true),
        ];
    }

    private function createReplyAction(): Action
    {
        return Action::make('createReply')
            ->label('Nueva respuesta')
            ->icon('heroicon-o-plus')
            ->modalHeading('Nueva respuesta rápida')
            ->schema($this->replySchema())
            ->action(function (array $data): void {
                CrmQuickReply::query()->create([
                    ...$this->replyData($data),
                    'created_by' => Auth::id(),
                    'updated_by' => Auth::id(),
                ]);

                Notification::make()->title('Respuesta guardada')->body('Ya aparece en la bandeja al escribir «/».')->success()->send();
            });
    }

    private function editReplyAction(): Action
    {
        return Action::make('editReply')
            ->label('Editar')
            ->icon('heroicon-o-pencil-square')
            ->modalHeading('Editar respuesta rápida')
            ->fillForm(fn (CrmQuickReply $record): array => $record->only(['label', 'body', 'area', 'sort', 'active']))
            ->schema($this->replySchema())
            ->action(function (CrmQuickReply $record, array $data): void {
                $record->update([...$this->replyData($data), 'updated_by' => Auth::id()]);

                Notification::make()->title('Respuesta actualizada')->success()->send();
            });
    }

    private function createDocumentAction(): Action
    {
        return Action::make('createDocument')
            ->label('Subir documento')
            ->icon('heroicon-o-arrow-up-tray')
            ->modalHeading('Nuevo documento aprobado')
            ->schema($this->documentSchema(true))
            ->action(function (array $data): void {
                $file = $this->documentFile($data);

                if ($file === null) {
                    Notification::make()->title('El archivo no es un PDF válido')->body('Súbelo de nuevo.')->danger()->send();

                    return;
                }

                CrmDocument::query()->create([
                    ...$this->documentData($data),
                    ...$file,
                    'created_by' => Auth::id(),
                    'updated_by' => Auth::id(),
                ]);

                Notification::make()->title('Documento guardado')->body('El analista ya puede enviarlo desde el chat.')->success()->send();
            });
    }

    private function editDocumentAction(): Action
    {
        return Action::make('editDocument')
            ->label('Editar')
            ->icon('heroicon-o-pencil-square')
            ->modalHeading('Editar documento')
            ->fillForm(fn (CrmDocument $record): array => [...$record->only(['title', 'description', 'area', 'sort', 'active']), 'path' => null])
            ->schema($this->documentSchema(false))
            ->action(function (CrmDocument $record, array $data): void {
                $old = (string) $record->path;
                $file = filled($data['path'] ?? null) ? $this->documentFile($data) : [];

                if ($file === null) {
                    Notification::make()->title('El archivo no es un PDF válido')->body('El documento anterior sigue igual.')->danger()->send();

                    return;
                }

                DB::transaction(fn () => $record->update([...$this->documentData($data), ...$file, 'updated_by' => Auth::id()]));

                if ($file !== [] && $old !== $file['path'] && CrmInboxLibrary::safePath($old)) {
                    Storage::disk(CrmInboxLibrary::DISK)->delete($old);
                }

                Notification::make()->title('Documento actualizado')->success()->send();
            });
    }

    private function toggleAction(): Action
    {
        return Action::make('toggle')
            ->label(fn (Model $record): string => $record->getAttribute('active') ? 'Desactivar' : 'Activar')
            ->icon(fn (Model $record): string => $record->getAttribute('active') ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
            ->color('gray')
            ->action(function (Model $record): void {
                $record->forceFill(['active' => ! $record->getAttribute('active'), 'updated_by' => Auth::id()])->save();
            });
    }

    private function deleteAction(bool $documents): Action
    {
        return Action::make('delete')
            ->label('Eliminar')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading($documents ? '¿Eliminar este documento?' : '¿Eliminar esta respuesta?')
            ->modalDescription($documents
                ? 'Deja de aparecer en el chat y se borra el PDF. Lo ya enviado a clientes no cambia. Si solo quieres ocultarlo, desactívalo.'
                : 'Deja de aparecer en el chat. Si solo quieres ocultarla, desactívala.')
            ->modalSubmitActionLabel('Eliminar')
            ->action(function (Model $record) use ($documents): void {
                $path = $documents ? (string) $record->getAttribute('path') : '';
                $record->delete();

                if ($documents && CrmInboxLibrary::safePath($path)) {
                    Storage::disk(CrmInboxLibrary::DISK)->delete($path);
                }

                Notification::make()->title('Eliminado')->success()->send();
            });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function replyData(array $data): array
    {
        return [
            'label' => trim((string) $data['label']),
            'body' => trim((string) $data['body']),
            'area' => $this->area($data['area'] ?? null),
            'sort' => max(0, min(999, (int) ($data['sort'] ?? 0))),
            'active' => (bool) ($data['active'] ?? true),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function documentData(array $data): array
    {
        $description = trim((string) ($data['description'] ?? ''));

        return [
            'title' => trim((string) $data['title']),
            'description' => $description !== '' ? $description : null,
            'area' => $this->area($data['area'] ?? null),
            'sort' => max(0, min(999, (int) ($data['sort'] ?? 0))),
            'active' => (bool) ($data['active'] ?? true),
        ];
    }

    /**
     * Comprueba en el disco que lo subido es un PDF dentro de la carpeta de la biblioteca.
     *
     * @param  array<string, mixed>  $data
     * @return array{path: string, filename: string, size: int}|null
     */
    private function documentFile(array $data): ?array
    {
        $path = is_array($data['path'] ?? null) ? (string) (array_values($data['path'])[0] ?? '') : (string) ($data['path'] ?? '');

        if (! CrmInboxLibrary::safePath($path)) {
            return null;
        }

        try {
            $disk = Storage::disk(CrmInboxLibrary::DISK);
            $size = $disk->size($path);
            $head = substr((string) $disk->get($path), 0, 4);
        } catch (Throwable) {
            return null;
        }

        if ($head !== '%PDF' || $size <= 0 || $size > CrmInboxLibrary::MAX_BYTES) {
            $disk->delete($path);

            return null;
        }

        $filename = trim((string) ($data['filename'] ?? ''));

        return [
            'path' => $path,
            'filename' => mb_substr($filename !== '' ? $filename : basename($path), 0, 120),
            'size' => (int) $size,
        ];
    }

    private function area(mixed $value): ?string
    {
        return is_string($value) && in_array($value, CrmInboxAreas::forPanel(static::panelKey()), true) ? $value : null;
    }
}
