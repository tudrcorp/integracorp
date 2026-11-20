<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\QuoteDesigns\Concerns;

use App\Filament\Business\Resources\QuoteDesigns\Schemas\QuoteDesignForm;
use App\Models\Plan;
use App\Models\QuoteDesign;
use App\Support\Quotes\Design\QuoteDesignHtmlImporter;
use App\Support\Quotes\Design\QuoteDesignImageNormalizer;
use App\Support\Quotes\Design\QuoteDesignPersistence;
use App\Support\Quotes\Design\QuoteDesignPreview;
use App\Support\Quotes\Design\QuoteDesignTheme;
use App\Support\TuDrQuote\QuoteDocumentLayout;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Acciones de la cabecera de crear/editar diseño: importar el HTML de diseño y ver la propuesta
 * con lo que hay en pantalla, sin guardar.
 */
trait InteractsWithQuoteDesignActions
{
    public function importHtmlAction(): Action
    {
        return Action::make('importHtml')
            ->label('Importar HTML de diseño')
            ->icon(Heroicon::OutlinedCodeBracketSquare)
            ->color('gray')
            ->modalHeading('Importar HTML de diseño')
            ->modalDescription('Suba el archivo .html que envió diseño. El sistema toma los colores, los textos y el logo (si viene dentro del archivo) y llena el formulario; usted revisa y guarda. El HTML no se imprime tal cual: el motor de PDF no entiende su maquetación.')
            ->modalSubmitActionLabel('Importar')
            ->modalWidth(Width::Large)
            ->schema([
                FileUpload::make('html')
                    ->label('Archivo HTML')
                    ->disk('local')
                    ->directory(QuoteDesign::DIRECTORY.'/html')
                    ->acceptedFileTypes(['text/html', 'text/plain', 'application/xhtml+xml'])
                    ->maxSize((int) (QuoteDesignHtmlImporter::MAX_BYTES / 1024))
                    ->required(),
            ])
            ->action(function (array $data): void {
                $path = is_array($data['html'] ?? null) ? reset($data['html']) : ($data['html'] ?? null);
                $disk = Storage::disk('local');

                if (! is_string($path) || ! $disk->exists($path)) {
                    Notification::make()->danger()->title('No se recibió el archivo')->body('Vuelva a subir el HTML.')->send();

                    return;
                }

                $result = QuoteDesignHtmlImporter::analyze((string) $disk->get($path));
                $this->applyImportedDesign($result, $path);
            });
    }

    public function previewAction(): Action
    {
        return Action::make('preview')
            ->label('Vista previa')
            ->icon(Heroicon::OutlinedEye)
            ->color('info')
            ->modalHeading('Vista previa de la propuesta')
            ->modalDescription('Se arma con lo que tiene en pantalla, aunque no lo haya guardado, y con las tarifas activas del plan (una persona por rango de edad). No se guarda nada en la cotización.')
            ->modalSubmitActionLabel('Generar vista previa')
            ->modalWidth(Width::Large)
            ->schema([
                Select::make('plan_id')
                    ->label('Plan')
                    ->options(fn (): array => $this->previewPlanOptions())
                    ->default(fn (): ?int => array_key_first($this->previewPlanOptions()))
                    ->searchable()
                    ->required(),
                ToggleButtons::make('scope')
                    ->label('Tipo de cotización')
                    ->options([
                        QuoteDocumentLayout::SCOPE_INDIVIDUAL => 'Individual',
                        QuoteDocumentLayout::SCOPE_CORPORATE => 'Corporativa',
                    ])
                    ->default(QuoteDocumentLayout::SCOPE_INDIVIDUAL)
                    ->inline()
                    ->required(),
            ])
            ->action(function (array $data): void {
                try {
                    $snapshot = $this->previewSnapshot();
                    $document = QuoteDesignPreview::render($snapshot, (int) $data['plan_id'], (string) $data['scope']);
                    $url = QuoteDesignPreview::store($document);
                } catch (ValidationException $exception) {
                    throw $exception;
                } catch (RuntimeException $exception) {
                    Notification::make()->warning()->title('No se pudo generar la vista previa')->body($exception->getMessage())->persistent()->send();

                    return;
                } catch (Throwable $exception) {
                    Log::error('quote-design: falló la vista previa', ['message' => $exception->getMessage()]);

                    Notification::make()
                        ->danger()
                        ->title('No se pudo generar la vista previa')
                        ->body('Revise las imágenes y las fuentes subidas (una fuente dañada impide dibujar el PDF). Si persiste, avise al administrador del sistema.')
                        ->persistent()
                        ->send();

                    return;
                }

                $this->replaceMountedAction('showPreview', ['url' => $url]);
            });
    }

    public function showPreviewAction(): Action
    {
        return Action::make('showPreview')
            ->modalHeading('Vista previa de la propuesta')
            ->modalDescription('Así verá el cliente la propuesta. Si algo no le convence, cierre, ajuste y vuelva a generar.')
            ->modalContent(fn (array $arguments) => view('filament.business.quote-designs.preview-modal', [
                'url' => (string) ($arguments['url'] ?? ''),
            ]))
            ->modalWidth(Width::SevenExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Cerrar');
    }

    /**
     * @param  array{theme: array<string, mixed>, logo: ?array{extension: string, contents: string}, report: list<string>, warnings: list<string>}  $result
     */
    protected function applyImportedDesign(array $result, string $htmlPath): void
    {
        $theme = $result['theme'];

        if (isset($theme['conditions'])) {
            $theme['conditions'] = QuoteDesignForm::conditionsToText($theme['conditions']);
        }

        $this->data['theme'] = array_merge((array) ($this->data['theme'] ?? []), $theme);
        $this->data['source_html_path'] = $htmlPath;

        if ($result['logo'] !== null) {
            $logoPath = QuoteDesign::DIRECTORY.'/logos/'.Str::uuid()->toString().'.'.$result['logo']['extension'];
            Storage::disk('public')->put($logoPath, $result['logo']['contents']);
            $this->data['logo_path'] = [(string) Str::uuid() => $logoPath];
        }

        $found = $result['report'] !== [];
        $body = collect([...$result['report'], ...$result['warnings']])
            ->map(fn (string $line): string => '• '.e($line))
            ->implode('<br>');

        Notification::make()
            ->title($found ? 'Diseño importado: revise y guarde' : 'No se encontró nada que importar')
            ->body($body !== '' ? $body : 'El archivo no trae estilos reconocibles.')
            ->status($found ? 'success' : 'warning')
            ->persistent()
            ->send();
    }

    /**
     * Valida el formulario, guarda las imágenes recién subidas (ya en A4, para que guardar después
     * no las procese otra vez) y devuelve la copia que se imprimiría.
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    protected function previewSnapshot(): array
    {
        $state = $this->form->getState();

        foreach (['pages_before', 'pages_after'] as $field) {
            $normalized = [];

            foreach (array_values((array) ($state[$field] ?? [])) as $path) {
                if (! is_string($path) || $path === '') {
                    continue;
                }

                try {
                    $normalizedPath = QuoteDesignImageNormalizer::normalize($path);
                } catch (RuntimeException $exception) {
                    throw ValidationException::withMessages(["data.{$field}" => $exception->getMessage()]);
                }

                if ($normalizedPath !== $path) {
                    Storage::disk('public')->delete($path);
                }

                $normalized[] = $normalizedPath;
            }

            $state[$field] = $normalized;
            $this->data[$field] = collect($normalized)->mapWithKeys(fn (string $path): array => [(string) Str::uuid() => $path])->all();
        }

        foreach (['font_regular_path', 'font_bold_path', 'font_mono_path'] as $field) {
            $state[$field] = QuoteDesignPersistence::validatedFont($state[$field] ?? null, $field);
        }

        return QuoteDesignTheme::snapshotFrom(
            (array) ($state['theme'] ?? []),
            $state['pages_before'],
            $state['pages_after'],
            is_string($state['logo_path'] ?? null) ? $state['logo_path'] : null,
            $state['font_regular_path'],
            $state['font_bold_path'],
            $state['font_mono_path'],
        );
    }

    /**
     * Planes asignados en pantalla primero; si no hay, todos los activos.
     *
     * @return array<int, string>
     */
    protected function previewPlanOptions(): array
    {
        $assigned = array_map('intval', (array) ($this->data['plans'] ?? []));

        $query = Plan::query()->orderBy('description');

        if ($assigned !== []) {
            $query->whereIn('id', $assigned);
        } else {
            $query->where('status', 'ACTIVO');
        }

        return $query->pluck('description', 'id')->mapWithKeys(fn (string $description, int|string $id): array => [(int) $id => $description])->all();
    }
}
