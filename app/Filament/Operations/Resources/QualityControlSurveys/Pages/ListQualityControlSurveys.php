<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\QualityControlSurveys\Pages;

use App\Enums\QualityControlAffiliateType;
use App\Filament\Operations\Resources\QualityControlSurveys\QualityControlSurveyResource;
use App\Jobs\ImportQualityControlSurveysJob;
use App\Support\Filament\FilamentIosButton;
use App\Support\Operations\QualityControl\QualityControlSurveyImporter;
use App\Support\Operations\QualityControl\QualityControlSurveySummary;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Listado de un ítem de Quality Control.
 *
 * La importación va en dos pasos: «Importar Excel» sube y analiza el archivo sin escribir
 * nada; la confirmación muestra qué entra y qué no, y recién ahí se encola el job. Entre
 * ambos pasos el navegador solo lleva un token: la ruta del archivo, el usuario y el tipo
 * quedan en caché del servidor, para que nadie pueda apuntar el job a otro archivo.
 */
abstract class ListQualityControlSurveys extends ListRecords
{
    public const CONFIRM_IMPORT_ACTION = 'confirmQualityControlImport';

    public const IMPORT_DIRECTORY = 'quality-control-imports';

    private const TOKEN_TTL_MINUTES = 30;

    /**
     * @return class-string<QualityControlSurveyResource>
     */
    protected function qualityControlResource(): string
    {
        /** @var class-string<QualityControlSurveyResource> $resource */
        $resource = static::getResource();

        return $resource;
    }

    public function getTitle(): string|Htmlable
    {
        return 'Quality Control '.$this->qualityControlResource()::affiliateType()->label();
    }

    public function getHeading(): string|Htmlable
    {
        $type = $this->qualityControlResource()::affiliateType();
        $summary = QualityControlSurveySummary::forType($type);

        return new HtmlString(view('filament.operations.partials.list-header', [
            'icon' => 'heroicon-o-clipboard-document-check',
            'eyebrow' => 'Afiliados · Control de calidad',
            'title' => 'Quality Control '.$type->label(),
            'total' => $summary['total'],
            'totalHint' => 'Encuestas registradas',
            'description' => 'Encuestas de satisfacción de los '.mb_strtolower($type->label()).' atendidos: canales, tiempo de respuesta, servicio médico y satisfacción general.'
                .($summary['satisfaction_rate'] !== null ? ' Satisfacción general: '.$summary['satisfaction_rate'].' %.' : ''),
            'stats' => [
                ['label' => 'Satisfechos', 'value' => $summary['satisfied'], 'icon' => 'heroicon-m-face-smile', 'tone' => 'success', 'hint' => 'Satisfacción general «Satisfecho» o «Totalmente satisfecho»'],
                ['label' => 'Neutros', 'value' => $summary['neutral'], 'icon' => 'heroicon-m-minus-circle', 'tone' => 'warning', 'hint' => 'Satisfacción general «Neutro»'],
                ['label' => 'Insatisfechos', 'value' => $summary['dissatisfied'], 'icon' => 'heroicon-m-face-frown', 'tone' => 'danger', 'hint' => 'Satisfacción general «Insatisfecho»: conviene contactar al paciente'],
                ['label' => 'Últimos 30 días', 'value' => $summary['last_30_days'], 'icon' => 'heroicon-m-calendar-days', 'tone' => 'primary', 'hint' => 'Encuestas respondidas en los últimos 30 días'],
            ],
        ])->render());
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->importAction(),
            CreateAction::make()
                ->label('Nueva encuesta')
                ->icon(Heroicon::Plus)
                ->color('primary')
                ->extraAttributes(['class' => FilamentIosButton::extraClassForFilamentColor('primary')]),
        ];
    }

    protected function importAction(): Action
    {
        $type = $this->qualityControlResource()::affiliateType();

        return Action::make('importQualityControl')
            ->label('Importar Excel')
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->color('gray')
            ->extraAttributes(['class' => FilamentIosButton::extraClassForFilamentColor('gray')])
            ->visible(fn (): bool => $this->qualityControlResource()::canCreate())
            ->modalIcon(Heroicon::OutlinedDocumentArrowUp)
            ->modalHeading('Importar respuestas de Quality Control '.$type->label())
            ->modalDescription('Cargue el Excel de respuestas del formulario (el mismo formato de «Respuestas del QC»). Primero verá un resumen de lo que entra; nada se guarda hasta que confirme.')
            ->modalWidth(Width::TwoExtraLarge)
            ->modalSubmitActionLabel('Revisar archivo')
            ->schema([
                FileUpload::make('file')
                    ->label('Archivo de respuestas')
                    ->helperText('Excel (.xlsx) o CSV de hasta 5 MB. Solo se lee la primera hoja. Las respuestas que ya estén cargadas no se repiten.')
                    ->disk(ImportQualityControlSurveysJob::DISK)
                    ->directory(self::IMPORT_DIRECTORY.'/'.Str::lower($type->value))
                    ->visibility('private')
                    ->acceptedFileTypes([
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel', 'text/x-csv',
                    ])
                    ->maxSize(5 * 1024)
                    ->storeFileNamesIn('file_name')
                    ->required(),
            ])
            ->successNotification(null)
            ->action(function (array $data): void {
                $this->analyzeImport($data);
            });
    }

    protected function confirmQualityControlImportAction(): Action
    {
        return Action::make(self::CONFIRM_IMPORT_ACTION)
            ->requiresConfirmation()
            ->modalIcon(Heroicon::OutlinedClipboardDocumentCheck)
            ->modalIconColor(fn (array $arguments): string => ($this->pendingImport($arguments)['summary']['other_type_matches'] ?? 0) > 0 ? 'warning' : 'success')
            ->modalHeading('Confirmar importación')
            ->modalDescription(fn (array $arguments): HtmlString => $this->importSummaryHtml($this->pendingImport($arguments)))
            ->modalWidth(Width::TwoExtraLarge)
            ->modalSubmitActionLabel(fn (array $arguments): string => 'Importar '.($this->pendingImport($arguments)['summary']['new'] ?? 0).' encuestas')
            ->modalCancelActionLabel('Cancelar')
            ->closeModalByClickingAway(false)
            ->modalSubmitAction(fn (Action $action): Action => $action->color('success'))
            ->action(function (Action $action): void {
                $this->dispatchImport($action->getArguments());
            });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function analyzeImport(array $data): void
    {
        $resource = $this->qualityControlResource();
        abort_unless($resource::canCreate(), 403);

        $type = $resource::affiliateType();
        $path = (string) (is_array($data['file'] ?? null) ? (array_values($data['file'])[0] ?? '') : ($data['file'] ?? ''));
        $fileName = (string) (is_array($data['file_name'] ?? null) ? (array_values($data['file_name'])[0] ?? '') : ($data['file_name'] ?? ''));
        $fileName = $fileName !== '' ? $fileName : basename($path);
        $disk = Storage::disk(ImportQualityControlSurveysJob::DISK);

        $this->pruneAbandonedUploads();

        if ($path === '' || ! $disk->exists($path)) {
            Notification::make()->title('No se recibió el archivo')->body('Vuelva a cargarlo e intente otra vez.')->danger()->send();

            return;
        }

        try {
            $summary = QualityControlSurveyImporter::analyze($disk->path($path), $type, pathinfo($fileName, PATHINFO_EXTENSION));
        } catch (Throwable $exception) {
            $disk->delete($path);

            Notification::make()
                ->title('No se pudo leer el archivo')
                ->body($exception instanceof RuntimeException ? $exception->getMessage() : 'Revise que sea el Excel de respuestas e intente otra vez.')
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        if ($summary['new'] === 0) {
            $disk->delete($path);

            Notification::make()
                ->title('No hay respuestas nuevas para cargar')
                ->body($summary['duplicates'] > 0 || $summary['repeated_in_file'] > 0
                    ? 'Todas las respuestas válidas del archivo ya estaban cargadas.'
                    : 'Ninguna fila del archivo es válida ('.$summary['error_count'].' con errores). Revise el formato e intente otra vez.')
                ->warning()
                ->persistent()
                ->send();

            return;
        }

        $token = (string) Str::uuid();

        Cache::put($this->cacheKey($token), [
            'path' => $path,
            'file_name' => $fileName,
            'type' => $type->value,
            'user_id' => (int) Auth::id(),
            'summary' => $summary,
        ], now()->addMinutes(self::TOKEN_TTL_MINUTES));

        $this->replaceMountedAction(self::CONFIRM_IMPORT_ACTION, ['token' => $token]);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    protected function dispatchImport(array $arguments): void
    {
        $resource = $this->qualityControlResource();
        abort_unless($resource::canCreate(), 403);

        $token = (string) ($arguments['token'] ?? '');
        $pending = $this->pendingImport($arguments);
        Cache::forget($this->cacheKey($token));

        if ($pending === null) {
            Notification::make()
                ->title('La revisión del archivo expiró')
                ->body('Vuelva a cargar el Excel para revisarlo de nuevo.')
                ->warning()
                ->send();

            return;
        }

        $batch = (string) Str::uuid();

        ImportQualityControlSurveysJob::dispatch(
            $pending['path'],
            $pending['type'],
            $pending['user_id'],
            $batch,
            $pending['file_name'],
        )->afterCommit();

        Notification::make()
            ->title('Importación en curso')
            ->body('Se están cargando '.$pending['summary']['new'].' encuestas. Le avisaremos en la campana de notificaciones cuando termine.')
            ->success()
            ->send();
    }

    /**
     * La entrada en caché solo vale para el mismo usuario y el mismo ítem que la creó.
     *
     * @param  array<string, mixed>  $arguments
     * @return array{path: string, file_name: string, type: string, user_id: int, summary: array<string, mixed>}|null
     */
    protected function pendingImport(array $arguments): ?array
    {
        $token = (string) ($arguments['token'] ?? '');

        if (! Str::isUuid($token)) {
            return null;
        }

        $pending = Cache::get($this->cacheKey($token));

        if (! is_array($pending)
            || (int) ($pending['user_id'] ?? 0) !== (int) Auth::id()
            || ($pending['type'] ?? null) !== $this->qualityControlResource()::affiliateType()->value) {
            return null;
        }

        return $pending;
    }

    /**
     * @param  array{file_name: string, summary: array<string, mixed>}|null  $pending
     */
    protected function importSummaryHtml(?array $pending): HtmlString
    {
        if ($pending === null) {
            return new HtmlString('<p>La revisión expiró. Cierre esta ventana y vuelva a cargar el archivo.</p>');
        }

        $summary = $pending['summary'];
        $type = $this->qualityControlResource()::affiliateType();
        $otherLabel = ($type === QualityControlAffiliateType::Individual ? QualityControlAffiliateType::Corporate : QualityControlAffiliateType::Individual)->label();

        $lines = [
            'Archivo: <strong>'.e($pending['file_name']).'</strong> · '.$summary['total'].' respuestas.',
            '<strong>Se cargarán '.$summary['new'].' encuestas nuevas</strong>'.($summary['linked'] > 0 ? ', '.$summary['linked'].' enlazadas a su afiliado.' : '.'),
        ];

        if ($summary['duplicates'] > 0) {
            $lines[] = $summary['duplicates'].' ya estaban cargadas (o se eliminaron antes) y no se repiten.';
        }

        if ($summary['repeated_in_file'] > 0) {
            $lines[] = $summary['repeated_in_file'].' filas repetidas dentro del mismo archivo se cuentan una sola vez.';
        }

        $html = '<div class="space-y-2 text-start text-sm"><p>'.implode('</p><p>', $lines).'</p>';

        if ($summary['other_type_matches'] > 0) {
            $html .= '<p class="rounded-lg bg-amber-50 px-3 py-2 font-medium text-amber-800 ring-1 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-300">'
                .$summary['other_type_matches'].' cédulas son de afiliados '.mb_strtolower($otherLabel).'. Si estas encuestas son de esa población, cancele e impórtelas desde «Quality Control '.$otherLabel.'».</p>';
        }

        if ($summary['error_count'] > 0) {
            $html .= '<p class="font-medium text-danger-600 dark:text-danger-400">'.$summary['error_count'].' filas con errores no se cargarán:</p><ul class="max-h-48 list-disc space-y-1 overflow-y-auto ps-5">';

            foreach ($summary['errors'] as $error) {
                $html .= '<li>Fila '.(int) $error['row'].': '.e($error['message']).'</li>';
            }

            if ($summary['error_count'] > count($summary['errors'])) {
                $html .= '<li>… y '.($summary['error_count'] - count($summary['errors'])).' más.</li>';
            }

            $html .= '</ul>';
        }

        if ($summary['missing_columns'] !== []) {
            $html .= '<p class="text-gray-500 dark:text-gray-400">Columnas que no vienen en el archivo (quedarán vacías): '.e(implode(', ', $summary['missing_columns'])).'.</p>';
        }

        return new HtmlString($html.'</div>');
    }

    /**
     * Archivos de revisiones que nadie confirmó (más de un día): se borran para no acumular
     * datos personales en disco.
     */
    protected function pruneAbandonedUploads(): void
    {
        $disk = Storage::disk(ImportQualityControlSurveysJob::DISK);
        $limit = now()->subDay()->getTimestamp();

        try {
            foreach ($disk->allFiles(self::IMPORT_DIRECTORY) as $file) {
                if ($disk->lastModified($file) < $limit) {
                    $disk->delete($file);
                }
            }
        } catch (Throwable) {
            // La limpieza nunca debe impedir una importación.
        }
    }

    private function cacheKey(string $token): string
    {
        return 'quality-control-import:'.$token;
    }
}
