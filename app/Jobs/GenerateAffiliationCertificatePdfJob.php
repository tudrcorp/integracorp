<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AffiliationCertificateIssue;
use App\Models\User;
use App\Services\AffiliationCertificateGeneratorService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Dibuja en cola el PDF de un certificado grande del «Generador de Certificado»
 * (corporativos con cientos de carnets) y avisa a quien lo pidió.
 *
 * Idempotente: si el PDF ya está listo y el archivo existe, no lo vuelve a dibujar.
 */
class GenerateAffiliationCertificatePdfJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(public int $issueId)
    {
        $this->onQueue((string) config('affiliate-card.documents_queue', 'documents'));
    }

    public function handle(AffiliationCertificateGeneratorService $generator): void
    {
        $issue = AffiliationCertificateIssue::query()->find($this->issueId);

        if ($issue === null) {
            return;
        }

        if ($issue->pdf_status === AffiliationCertificateIssue::PDF_READY && $issue->pdf_path && Storage::disk('local')->exists($issue->pdf_path)) {
            return;
        }

        // Un padrón de ~2.700 afiliados con todos sus carnets son ~750 páginas.
        ini_set('memory_limit', '2048M');
        set_time_limit(1740);

        $path = AffiliationCertificateGeneratorService::STORAGE_DIRECTORY.'/'.$issue->verification_key.'.pdf';

        Storage::disk('local')->put($path, $generator->renderPdf($issue, fromStorage: false));

        $issue->forceFill([
            'pdf_status' => AffiliationCertificateIssue::PDF_READY,
            'pdf_path' => $path,
            'pdf_error' => null,
            'pdf_generated_at' => now(),
        ])->save();

        $this->notify($issue, ready: true);
    }

    public function failed(?Throwable $exception): void
    {
        $issue = AffiliationCertificateIssue::query()->find($this->issueId);

        Log::error('GenerateAffiliationCertificatePdfJob: no se pudo dibujar el certificado', [
            'affiliation_certificate_issue_id' => $this->issueId,
            'message' => $exception?->getMessage(),
        ]);

        if ($issue === null) {
            return;
        }

        $issue->forceFill([
            'pdf_status' => AffiliationCertificateIssue::PDF_FAILED,
            'pdf_error' => mb_substr((string) $exception?->getMessage(), 0, 1000),
        ])->save();

        $this->notify($issue, ready: false);
    }

    private function notify(AffiliationCertificateIssue $issue, bool $ready): void
    {
        $user = $issue->issued_by ? User::query()->find($issue->issued_by) : null;

        if ($user === null) {
            return;
        }

        $notification = $ready
            ? Notification::make()
                ->title('Certificado listo')
                ->body('El certificado de '.$issue->affiliation_code.' con '.$issue->carnets_count.' carnets ya se puede descargar.')
                ->success()
                ->actions([
                    Action::make('download')
                        ->label('Descargar PDF')
                        ->url(route('business.affiliation-certificate.pdf', ['issue' => $issue->id, 'descargar' => 1]))
                        ->markAsRead(),
                ])
            : Notification::make()
                ->title('No se pudo generar el certificado')
                ->body('El certificado de '.$issue->affiliation_code.' falló al dibujarse. Intente de nuevo; si persiste, reporte a soporte.')
                ->danger();

        $notification->sendToDatabase($user);
    }
}
