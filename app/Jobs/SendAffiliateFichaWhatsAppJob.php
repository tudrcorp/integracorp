<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\AffiliateFichaScope;
use App\Http\Controllers\NotificationController;
use App\Models\User;
use App\Services\AffiliateFichaPdfService;
use App\Support\Affiliations\AffiliateFicha\AffiliateFichaAccess;
use App\Support\Affiliations\AffiliateFicha\AffiliateFichaNotifier;
use App\Support\SecurityAudit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Envía la ficha del afiliado por WhatsApp (UltraMsg).
 *
 * La API descarga el documento desde una URL pública, así que el PDF se deja
 * en `storage/fichas-afiliado/` con un nombre aleatorio imposible de adivinar
 * y se borra solo {@see self::PUBLIC_FILE_MINUTES} minutos después
 * ({@see DeleteAffiliateFichaPublicFileJob}), o enseguida si el envío falla.
 */
class SendAffiliateFichaWhatsAppJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public const PUBLIC_DIRECTORY = 'fichas-afiliado';

    public const PUBLIC_FILE_MINUTES = 120;

    public int $tries = 2;

    public int $timeout = 180;

    public function __construct(
        public string $kind,
        public int $affiliateId,
        public string $scope,
        public string $panelId,
        public int $userId,
        public string $phone,
        public ?string $message,
    ) {}

    public function backoff(): int
    {
        return 120;
    }

    public function handle(): void
    {
        $user = User::query()->find($this->userId);
        $scope = AffiliateFichaScope::tryFrom($this->scope);

        if ($user === null || $scope === null || ! AffiliateFichaAccess::canUse($user, $this->panelId, $scope)) {
            SecurityAudit::log('AUDIT_AFFILIATE_FICHA_WHATSAPP_DENIED', 'affiliate-ficha.whatsapp.job', $this->context(), $user);
            AffiliateFichaNotifier::failed($user, 'Ya no tiene permiso para enviar esta ficha con la información elegida.');

            return;
        }

        $record = AffiliateFichaPdfService::find($this->kind, $this->affiliateId);

        if ($record === null) {
            AffiliateFichaNotifier::failed($user, 'El afiliado ya no existe.');

            return;
        }

        $pdf = AffiliateFichaPdfService::render($this->kind, $record, $scope);
        $relativePath = self::PUBLIC_DIRECTORY.'/'.Str::random(48).'.pdf';
        $absolutePath = public_path('storage/'.$relativePath);

        if (! is_dir(dirname($absolutePath)) && ! mkdir(dirname($absolutePath), 0755, true) && ! is_dir(dirname($absolutePath))) {
            throw new RuntimeException('No se pudo crear la carpeta temporal de fichas.');
        }

        if (file_put_contents($absolutePath, $pdf['binary']) === false) {
            throw new RuntimeException('No se pudo guardar el PDF temporal de la ficha.');
        }

        try {
            $sent = NotificationController::sendWhatsAppDocument(
                $this->phone,
                $this->caption($pdf['data'], $scope),
                $relativePath,
                $pdf['filename'],
            );
        } catch (Throwable $exception) {
            @unlink($absolutePath);

            throw $exception;
        }

        if (! $sent) {
            @unlink($absolutePath);

            throw new RuntimeException('La API de WhatsApp no confirmó el envío de la ficha.');
        }

        DeleteAffiliateFichaPublicFileJob::dispatch($relativePath)->delay(now()->addMinutes(self::PUBLIC_FILE_MINUTES));

        /** El envío ya salió: un fallo al auditar no debe provocar un reintento que lo duplique. */
        try {
            SecurityAudit::log('AUDIT_AFFILIATE_FICHA_WHATSAPP_SENT', 'affiliate-ficha.whatsapp.job', [
                ...$this->context(),
                'filename' => $pdf['filename'],
            ], $user);
        } catch (Throwable $exception) {
            report($exception);
        }

        AffiliateFichaNotifier::sent($user, 'Se envió por WhatsApp la ficha de '.$pdf['data']['name'].' ('.$scope->label().') al '.$this->phone.'.');
    }

    public function failed(?Throwable $exception): void
    {
        SecurityAudit::log('AUDIT_AFFILIATE_FICHA_WHATSAPP_FAILED', 'affiliate-ficha.whatsapp.job', [
            ...$this->context(),
            'error' => $exception?->getMessage(),
        ], User::query()->find($this->userId));

        AffiliateFichaNotifier::failed(
            User::query()->find($this->userId),
            'El WhatsApp al '.$this->phone.' no salió. Verifique el número e intente de nuevo.',
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function caption(array $data, AffiliateFichaScope $scope): string
    {
        $lines = [
            '📎 *Ficha del afiliado*',
            '',
            'Afiliado: *'.$data['name'].'*',
            'Documento: '.$data['document'],
            'Afiliación: '.$data['affiliationCode'],
            'Información: '.$scope->label(),
        ];

        if ($this->message !== null) {
            $lines[] = '';
            $lines[] = $this->message;
        }

        $lines[] = '';
        $lines[] = $scope->includesClinical()
            ? '_Contiene información médica confidencial._'
            : '_Documento confidencial._';

        return implode("\n", $lines);
    }

    /**
     * @return array<string, mixed>
     */
    private function context(): array
    {
        return [
            'kind' => $this->kind,
            'affiliate_id' => $this->affiliateId,
            'scope' => $this->scope,
            'panel' => $this->panelId,
            'phone' => $this->phone,
        ];
    }
}
