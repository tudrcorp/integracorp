<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\AffiliateFichaScope;
use App\Mail\AffiliateFichaMail;
use App\Models\User;
use App\Services\AffiliateFichaPdfService;
use App\Support\Affiliations\AffiliateFicha\AffiliateFichaAccess;
use App\Support\Affiliations\AffiliateFicha\AffiliateFichaNotifier;
use App\Support\SecurityAudit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Arma la ficha del afiliado y la envía en un solo correo a Para, CC y CCO.
 *
 * Antes de enviar repite la autorización: si al analista le quitaron el
 * permiso clínico entre el clic y la cola, no sale nada.
 */
class SendAffiliateFichaEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $timeout = 180;

    /**
     * @param  list<string>  $to
     * @param  list<string>  $cc
     * @param  list<string>  $bcc
     */
    public function __construct(
        public string $kind,
        public int $affiliateId,
        public string $scope,
        public string $panelId,
        public int $userId,
        public array $to,
        public array $cc,
        public array $bcc,
        public ?string $message,
    ) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(): void
    {
        $user = User::query()->find($this->userId);
        $scope = AffiliateFichaScope::tryFrom($this->scope);

        if ($user === null || $scope === null || ! AffiliateFichaAccess::canUse($user, $this->panelId, $scope)) {
            SecurityAudit::log('AUDIT_AFFILIATE_FICHA_EMAIL_DENIED', 'affiliate-ficha.email.job', $this->context(), $user);
            AffiliateFichaNotifier::failed($user, 'Ya no tiene permiso para enviar esta ficha con la información elegida.');

            return;
        }

        $record = AffiliateFichaPdfService::find($this->kind, $this->affiliateId);

        if ($record === null) {
            AffiliateFichaNotifier::failed($user, 'El afiliado ya no existe.');

            return;
        }

        $pdf = AffiliateFichaPdfService::render($this->kind, $record, $scope);
        $data = $pdf['data'];

        Mail::to($this->to)
            ->cc($this->cc)
            ->bcc($this->bcc)
            ->send(new AffiliateFichaMail(
                affiliateName: (string) $data['name'],
                affiliateDocument: (string) $data['document'],
                affiliationCode: (string) $data['affiliationCode'],
                scopeLabel: $scope->label(),
                includesClinical: $scope->includesClinical(),
                analystMessage: $this->message,
                senderName: $user->name,
                pdfBinary: $pdf['binary'],
                attachmentFilename: $pdf['filename'],
            ));

        /** El envío ya salió: un fallo al auditar no debe provocar un reintento que lo duplique. */
        try {
            SecurityAudit::log('AUDIT_AFFILIATE_FICHA_EMAIL_SENT', 'affiliate-ficha.email.job', [
                ...$this->context(),
                'filename' => $pdf['filename'],
            ], $user);
        } catch (Throwable $exception) {
            report($exception);
        }

        $copies = ($this->cc !== [] ? ' Con copia a '.implode(', ', $this->cc).'.' : '')
            .($this->bcc !== [] ? ' Copia oculta a '.implode(', ', $this->bcc).'.' : '');

        AffiliateFichaNotifier::sent(
            $user,
            'Se envió la ficha de '.$data['name'].' ('.$scope->label().') a '.implode(', ', $this->to).'.'.$copies,
        );
    }

    public function failed(?Throwable $exception): void
    {
        SecurityAudit::log('AUDIT_AFFILIATE_FICHA_EMAIL_FAILED', 'affiliate-ficha.email.job', [
            ...$this->context(),
            'error' => $exception?->getMessage(),
        ], User::query()->find($this->userId));

        AffiliateFichaNotifier::failed(
            User::query()->find($this->userId),
            'El correo a '.implode(', ', $this->to).' no salió tras varios intentos. Intente de nuevo o avise a Sistemas.',
        );
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
            'to' => $this->to,
            'cc' => $this->cc,
            'bcc' => $this->bcc,
        ];
    }
}
