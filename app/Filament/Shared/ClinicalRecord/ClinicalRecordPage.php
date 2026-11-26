<?php

declare(strict_types=1);

namespace App\Filament\Shared\ClinicalRecord;

use App\Jobs\GenerateClinicalRecordPdfJob;
use App\Models\ClinicalRecordAccessLog;
use App\Models\TelemedicinePatient;
use App\Models\User;
use App\Support\Telemedicine\ClinicalRecord\ClinicalRecordAccess;
use App\Support\Telemedicine\ClinicalRecord\PatientClinicalRecord;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Panel;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Ficha clínica del paciente (solo lectura), común a Telemedicina y Operaciones. Se arma en cada
 * apertura con {@see PatientClinicalRecord}; cada apertura y cada PDF quedan en la bitácora.
 */
abstract class ClinicalRecordPage extends Page
{
    use InteractsWithRecord;

    protected static ?string $title = 'Ficha clínica';

    protected static ?string $breadcrumb = 'Ficha clínica';

    protected static bool $shouldRegisterNavigation = false;

    protected Width|string|null $maxContentWidth = Width::Full;

    protected string $view = 'filament.shared.clinical-record.page';

    abstract public static function panelKey(): string;

    /**
     * Ficha clínica (sin afiliación) o ficha técnica de uso interno (con afiliación y cupos).
     */
    public static function recordKind(): string
    {
        return ClinicalRecordAccess::KIND_CLINICAL;
    }

    protected static function isTechnical(): bool
    {
        return static::recordKind() === ClinicalRecordAccess::KIND_TECHNICAL;
    }

    /**
     * Enlace al registro de origen de un evento de la línea de tiempo, si este panel lo tiene.
     *
     * @param  array<string, mixed>  $event
     */
    abstract protected function eventUrl(array $event): ?string;

    public static function getRoutePath(Panel $panel): string
    {
        return '/{record}/ficha-clinica';
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $user = Auth::user();

        abort_unless($user instanceof User && $this->record instanceof TelemedicinePatient && ClinicalRecordAccess::canView($user, $this->record, static::panelKey(), static::recordKind()), 403);

        ClinicalRecordAccess::record($user, $this->record, static::panelKey(), ClinicalRecordAccessLog::ACTION_VIEW, request()->ip(), static::recordKind());
    }

    public function getTitle(): string|Htmlable
    {
        return static::isTechnical() ? 'Ficha técnica del paciente (uso interno)' : 'Ficha clínica del paciente';
    }

    public function getBreadcrumb(): string
    {
        return static::isTechnical() ? 'Ficha técnica' : 'Ficha clínica';
    }

    public function getSubheading(): ?string
    {
        $patient = $this->record;

        return $patient instanceof TelemedicinePatient ? mb_strtoupper((string) $patient->full_name) : null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Volver al paciente')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(fn (): string => static::getResource()::getUrl('view', ['record' => $this->record])),
            Action::make('pdf')
                ->label('Descargar PDF')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('primary')
                ->action(fn () => $this->requestPdf()),
        ];
    }

    public function requestPdf(): void
    {
        $user = Auth::user();
        $patient = $this->record;

        if (! $user instanceof User || ! $patient instanceof TelemedicinePatient || ! ClinicalRecordAccess::canView($user, $patient, static::panelKey(), static::recordKind())) {
            Notification::make()->title('No tienes acceso a esta ficha')->danger()->send();

            return;
        }

        if (! Cache::add('clinical-record-pdf:'.static::recordKind().':'.$user->getKey().':'.$patient->getKey(), 1, now()->addSeconds(20))) {
            Notification::make()
                ->title('Ya estamos generando este PDF')
                ->body('Te avisamos en la campana apenas esté listo.')
                ->info()
                ->send();

            return;
        }

        try {
            GenerateClinicalRecordPdfJob::dispatch((int) $patient->getKey(), (int) $user->getKey(), static::panelKey(), request()->ip(), static::recordKind());
        } catch (Throwable) {
            Notification::make()->title('No se pudo iniciar el PDF')->body('Intenta de nuevo en un momento.')->danger()->send();

            return;
        }

        Notification::make()
            ->title(static::isTechnical() ? 'Generando el PDF de la ficha técnica' : 'Generando el PDF de la ficha clínica')
            ->body('Tarda unos segundos. Te avisamos en la campana con el enlace de descarga.')
            ->success()
            ->send();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        /** @var TelemedicinePatient $patient */
        $patient = $this->record;
        $ficha = PatientClinicalRecord::build($patient);

        $ficha['timeline'] = array_map(fn (array $event): array => [...$event, 'url' => $this->safeUrl($event)], $ficha['timeline']);

        return [
            'ficha' => $ficha,
            'technical' => static::isTechnical(),
            'accesses' => ClinicalRecordAccess::recent($patient),
        ];
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function safeUrl(array $event): ?string
    {
        try {
            return $this->eventUrl($event);
        } catch (Throwable) {
            return null;
        }
    }
}
