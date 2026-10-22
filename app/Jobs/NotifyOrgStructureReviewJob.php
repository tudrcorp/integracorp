<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Filament\Business\Pages\EstructuraOrganizacional;
use App\Models\OrgStructureVersion;
use App\Models\User;
use App\Support\Renovations\EarlyRenovationRecipients;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Avisos en la campana del flujo de aprobación del organigrama:
 *
 * - enviado: a todos los SUPERADMIN activos menos quien lo envió;
 * - aprobado o devuelto: a quien lo envió.
 *
 * Son notificaciones de base de datos, baratas, pero van en cola para no sumar
 * escrituras a la petición de quien publica.
 */
class NotifyOrgStructureReviewJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const SUBMITTED = 'submitted';

    public const APPROVED = 'approved';

    public const RETURNED = 'returned';

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        public int $versionId,
        public string $event,
        public int $actorId,
    ) {
        $this->onQueue('system');
    }

    public function handle(): void
    {
        $version = OrgStructureVersion::query()->find($this->versionId);

        if ($version === null) {
            return;
        }

        $actor = User::query()->find($this->actorId);
        $actorName = $actor?->name ?? 'Un usuario';
        $url = EstructuraOrganizacional::getUrl(['borrador' => $version->id], panel: 'business');

        if ($this->event === self::SUBMITTED) {
            $recipients = EarlyRenovationRecipients::superAdmins()
                ->reject(fn (User $user): bool => (int) $user->getKey() === $this->actorId);

            if ($recipients->isEmpty()) {
                return;
            }

            Notification::make()
                ->title('Organigrama por aprobar')
                ->body($actorName.' envió «'.$version->name.'» a aprobación.')
                ->icon('heroicon-o-rectangle-group')
                ->warning()
                ->actions([
                    Action::make('review')->label('Revisar')->button()->url($url)->markAsRead(),
                ])
                ->sendToDatabase($recipients);

            return;
        }

        $submitter = $version->submitted_by !== null ? User::query()->find($version->submitted_by) : null;

        if ($submitter === null) {
            return;
        }

        $notification = $this->event === self::APPROVED
            ? Notification::make()
                ->title('Organigrama aprobado')
                ->body($actorName.' aprobó «'.$version->name.'». Ya es el organigrama vigente.')
                ->success()
            : Notification::make()
                ->title('Organigrama devuelto')
                ->body($actorName.' devolvió «'.$version->name.'»: '.$version->review_notes)
                ->danger();

        $notification
            ->icon('heroicon-o-rectangle-group')
            ->actions([
                Action::make('open')->label('Abrir')->button()->url($url)->markAsRead(),
            ])
            ->sendToDatabase($submitter);
    }

    public function failed(?Throwable $exception): void
    {
        Log::warning('NotifyOrgStructureReviewJob falló', [
            'version_id' => $this->versionId,
            'event' => $this->event,
            'message' => $exception?->getMessage(),
        ]);
    }
}
