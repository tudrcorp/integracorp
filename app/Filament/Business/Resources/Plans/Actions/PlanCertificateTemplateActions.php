<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\Plans\Actions;

use App\Models\Plan;
use App\Models\PlanCertificateTemplate;
use App\Models\User;
use App\Support\Affiliations\Certificates\PlanCertificateTemplateEngine;
use App\Support\Affiliations\Certificates\PlanCertificateTemplates;
use App\Support\Filament\BusinessFilamentActionAccess;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions as SchemaActions;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Diseño de la hoja del certificado por plan, desde la tabla de planes. Solo
 * lo usa el Generador de Certificado; sin diseño, el plan usa el predeterminado.
 */
final class PlanCertificateTemplateActions
{
    /** @var array<int, int>|null plan_id => versión activa (una consulta por petición) */
    private static ?array $activeVersions = null;

    public static function activeVersion(Plan $plan): ?int
    {
        if (! PlanCertificateTemplates::available()) {
            return null;
        }

        self::$activeVersions ??= PlanCertificateTemplate::query()
            ->where('status', PlanCertificateTemplate::STATUS_ACTIVE)
            ->pluck('version', 'plan_id')
            ->map(fn (mixed $version): int => (int) $version)
            ->all();

        return self::$activeVersions[(int) $plan->getKey()] ?? null;
    }

    public static function flush(): void
    {
        self::$activeVersions = null;
    }

    public static function canManage(): bool
    {
        return PlanCertificateTemplates::available()
            && BusinessFilamentActionAccess::userCan(BusinessFilamentActionPermissionRegistry::DESIGN_PLAN_CERTIFICATE);
    }

    /**
     * @return list<Action>
     */
    public static function all(): array
    {
        return [self::upload(), self::samplePdf(), self::restore(), self::useDefault()];
    }

    public static function upload(): Action
    {
        return Action::make('certificateTemplate')
            ->label(fn (Plan $record): string => self::activeVersion($record) !== null ? 'Diseño del certificado (v'.self::activeVersion($record).')' : 'Diseño del certificado')
            ->icon(Heroicon::OutlinedDocumentText)
            ->color('info')
            ->visible(fn (): bool => self::canManage())
            ->modalHeading(fn (Plan $record): string => 'Diseño del certificado · '.($record->description ?: $record->code))
            ->modalDescription('Opcional. Si el plan no tiene diseño propio, el Generador de Certificado usa el diseño predeterminado con los colores del plan. El diseño reemplaza solo la hoja del certificado: la relación de afiliados y los carnets siguen con el diseño estándar. Cada certificado emitido conserva la versión con que se generó.')
            ->modalIcon(Heroicon::OutlinedDocumentText)
            ->modalWidth(Width::FourExtraLarge)
            ->schema([
                Placeholder::make('status')
                    ->hiddenLabel()
                    ->content(fn (Plan $record): HtmlString => new HtmlString(view('filament.business.plans.certificate-template-status', [
                        'versions' => PlanCertificateTemplate::query()->where('plan_id', $record->getKey())->orderByDesc('version')->limit(10)->get(),
                    ])->render())),
                SchemaActions::make([
                    Action::make('downloadExample')
                        ->label('Descargar plantilla de ejemplo')
                        ->icon(Heroicon::OutlinedArrowDownTray)
                        ->color('gray')
                        ->action(fn (): StreamedResponse => response()->streamDownload(
                            fn () => print (file_get_contents(resource_path('views/documents/affiliation-certificate/custom/plantilla-ejemplo.html'))),
                            'plantilla-certificado-ejemplo.html',
                            ['Content-Type' => 'text/html; charset=UTF-8'],
                        )),
                    Action::make('downloadCurrent')
                        ->label('Descargar el diseño activo')
                        ->icon(Heroicon::OutlinedArrowDownTray)
                        ->color('gray')
                        ->visible(fn (Plan $record): bool => self::activeVersion($record) !== null)
                        ->action(function (Plan $record): ?StreamedResponse {
                            $template = PlanCertificateTemplates::activeFor((int) $record->getKey());

                            return $template === null ? null : response()->streamDownload(
                                fn () => print ($template->html),
                                'certificado-'.$record->code.'-v'.$template->version.'.html',
                                ['Content-Type' => 'text/html; charset=UTF-8'],
                            );
                        }),
                ]),
                Section::make('Guía de marcadores')
                    ->description('Copie los marcadores tal cual en su HTML. Los datos salen de la base en cada certificado.')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Placeholder::make('guide')
                            ->hiddenLabel()
                            ->content(fn (): HtmlString => new HtmlString(view('filament.business.plans.certificate-template-guide')->render())),
                    ]),
                FileUpload::make('file')
                    ->label('Archivo HTML del diseño')
                    ->helperText('Solo .html, hasta '.(int) (PlanCertificateTemplateEngine::MAX_BYTES / 1048576).' MB. Al subirlo se quitan scripts, formularios, eventos y recursos externos; se valida y se prueba con un PDF real antes de activarlo.')
                    ->acceptedFileTypes(['text/html', '.html', '.htm'])
                    ->maxSize((int) (PlanCertificateTemplateEngine::MAX_BYTES / 1024))
                    ->storeFiles(false)
                    ->required(),
                Textarea::make('notes')
                    ->label('Notas de esta versión')
                    ->placeholder('Ej.: Diseño aprobado por Marketing para Repsol 2026-2027.')
                    ->rows(2)
                    ->maxLength(1000),
            ])
            ->modalSubmitActionLabel('Validar y activar')
            ->action(function (array $data, Plan $record, Action $action): void {
                abort_unless(self::canManage(), 403);
                $user = Auth::user();
                abort_unless($user instanceof User, 403);

                $file = $data['file'] ?? null;
                if (is_array($file)) {
                    $file = reset($file);
                }

                if (! $file instanceof TemporaryUploadedFile) {
                    Notification::make()->title('Adjunte el archivo HTML')->danger()->send();
                    $action->halt();

                    return;
                }

                $extension = strtolower((string) $file->getClientOriginalExtension());
                if (! in_array($extension, ['html', 'htm'], true)) {
                    Notification::make()->title('Solo archivos .html')->body('El archivo «'.$file->getClientOriginalName().'» no es HTML.')->danger()->send();
                    $action->halt();

                    return;
                }

                $raw = (string) $file->get();

                try {
                    $template = PlanCertificateTemplates::upload($record, $raw, $file->getClientOriginalName(), $data['notes'] ?? null, $user);
                } catch (RuntimeException $exception) {
                    Notification::make()->title('El diseño no se activó')->body($exception->getMessage())->danger()->persistent()->send();
                    $action->halt();

                    return;
                } catch (Throwable $exception) {
                    report($exception);
                    Notification::make()->title('El diseño no se activó')->body('Error inesperado. No se cambió nada.')->danger()->send();
                    $action->halt();

                    return;
                } finally {
                    $file->delete();
                }

                self::flush();
                Notification::make()
                    ->title('Diseño v'.$template->version.' activo')
                    ->body('Ocupa '.$template->page_count.' hoja(s). Los certificados que se emitan desde ahora para este plan lo usan.'
                        .($template->removed ? ' Se quitó del archivo: '.implode(', ', array_slice($template->removed, 0, 6)).'.' : '')
                        .(($warnings = PlanCertificateTemplates::warnings($raw)) !== [] ? ' Revise: '.implode(' ', $warnings) : ''))
                    ->success()
                    ->persistent()
                    ->send();
            });
    }

    public static function samplePdf(): Action
    {
        return Action::make('certificateTemplateSample')
            ->label('PDF de muestra del certificado')
            ->icon(Heroicon::OutlinedEye)
            ->color('gray')
            ->visible(fn (Plan $record): bool => self::canManage() && self::activeVersion($record) !== null)
            ->action(function (Plan $record): ?StreamedResponse {
                abort_unless(self::canManage(), 403);
                $template = PlanCertificateTemplates::activeFor((int) $record->getKey());
                if ($template === null) {
                    return null;
                }

                try {
                    $pdf = PlanCertificateTemplates::samplePdf($record, (string) $template->html);
                } catch (Throwable $exception) {
                    report($exception);
                    Notification::make()->title('No se pudo dibujar la muestra')->body(mb_substr($exception->getMessage(), 0, 300))->danger()->send();

                    return null;
                }

                return response()->streamDownload(fn () => print ($pdf), 'muestra-certificado-'.$record->code.'-v'.$template->version.'.pdf', ['Content-Type' => 'application/pdf']);
            });
    }

    public static function restore(): Action
    {
        return Action::make('certificateTemplateRestore')
            ->label('Restaurar versión del certificado')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('gray')
            ->visible(fn (Plan $record): bool => self::canManage()
                && PlanCertificateTemplate::query()->where('plan_id', $record->getKey())->where('status', PlanCertificateTemplate::STATUS_INACTIVE)->exists())
            ->modalHeading('Restaurar una versión del diseño')
            ->schema([
                Select::make('template_id')
                    ->label('Versión')
                    ->options(fn (Plan $record): array => PlanCertificateTemplate::query()
                        ->where('plan_id', $record->getKey())
                        ->where('status', PlanCertificateTemplate::STATUS_INACTIVE)
                        ->orderByDesc('version')
                        ->get()
                        ->mapWithKeys(fn (PlanCertificateTemplate $template): array => [$template->getKey() => 'v'.$template->version.' · '.$template->created_at?->format('d/m/Y H:i').' · '.($template->original_name ?? 'sin nombre')])
                        ->all())
                    ->required(),
                Textarea::make('reason')->label('Motivo')->rows(2)->required()->minLength(PlanCertificateTemplates::REASON_MIN_LENGTH)->maxLength(1000),
            ])
            ->action(function (array $data, Plan $record, Action $action): void {
                abort_unless(self::canManage(), 403);
                $user = Auth::user();
                abort_unless($user instanceof User, 403);

                $template = PlanCertificateTemplate::query()->where('plan_id', $record->getKey())->find((int) ($data['template_id'] ?? 0));
                if (! $template instanceof PlanCertificateTemplate) {
                    Notification::make()->title('Esa versión no es de este plan')->danger()->send();
                    $action->halt();

                    return;
                }

                try {
                    PlanCertificateTemplates::restore($template, $user, (string) $data['reason']);
                } catch (RuntimeException $exception) {
                    Notification::make()->title('No se restauró')->body($exception->getMessage())->danger()->send();
                    $action->halt();

                    return;
                }

                self::flush();
                Notification::make()->title('Versión v'.$template->version.' activa')->success()->send();
            });
    }

    public static function useDefault(): Action
    {
        return Action::make('certificateTemplateDefault')
            ->label('Usar certificado predeterminado')
            ->icon(Heroicon::OutlinedXMark)
            ->color('danger')
            ->visible(fn (Plan $record): bool => self::canManage() && self::activeVersion($record) !== null)
            ->modalHeading('Volver al certificado predeterminado')
            ->modalDescription('El plan deja de usar su diseño propio: los certificados que se emitan desde ahora salen con el diseño predeterminado. Los ya emitidos conservan el suyo. Las versiones quedan guardadas para restaurarlas.')
            ->modalIcon(Heroicon::OutlinedXMark)
            ->modalIconColor('danger')
            ->schema([
                Textarea::make('reason')->label('Motivo')->rows(2)->required()->minLength(PlanCertificateTemplates::REASON_MIN_LENGTH)->maxLength(1000),
            ])
            ->modalSubmitActionLabel('Usar el predeterminado')
            ->action(function (array $data, Plan $record, Action $action): void {
                abort_unless(self::canManage(), 403);
                $user = Auth::user();
                abort_unless($user instanceof User, 403);

                try {
                    PlanCertificateTemplates::useDefault($record, $user, (string) $data['reason']);
                } catch (RuntimeException $exception) {
                    Notification::make()->title('No se cambió')->body($exception->getMessage())->danger()->send();
                    $action->halt();

                    return;
                }

                self::flush();
                Notification::make()->title('El plan usa el certificado predeterminado')->success()->send();
            });
    }
}
