<?php

declare(strict_types=1);

namespace App\Filament\Business\Concerns;

use App\Enums\ClinicalUsageAccessContext;
use App\Enums\SystemOtpCodeKind;
use App\Filament\Forms\Components\OtpBoxesInput;
use App\Models\ClinicalUsageAccessChallenge;
use App\Models\User;
use App\Support\ClinicalEntitlements\ClinicalEntitlementException;
use App\Support\ClinicalEntitlements\ClinicalUsageAccessOtp;
use App\Support\Filament\FilamentIosButton;
use App\Support\Otp\SelfAuthorizedOtp;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Locked;

trait InteractsWithClinicalUsageAccessGate
{
    #[Locked]
    public bool $clinicalUsageUnlocked = false;

    #[Locked]
    public ?string $pendingClinicalUsageAccessPublicId = null;

    public function clinicalUsageIsUnlocked(): bool
    {
        return $this->clinicalUsageUnlocked;
    }

    protected function bootClinicalUsageAccessGate(): void
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            $this->clinicalUsageUnlocked = false;

            return;
        }

        if (! $this->clinicalUsageAccessRequiresGate()) {
            $this->clinicalUsageUnlocked = true;

            return;
        }

        if (ClinicalUsageAccessOtp::userMayBypass($user)) {
            $this->clinicalUsageUnlocked = true;

            return;
        }

        $this->clinicalUsageUnlocked = false;
        $this->pendingClinicalUsageAccessPublicId = null;

        if ($this->clinicalUsageAccessBlocksPage()) {
            $this->defaultAction = 'unlockClinicalUsage';
        }
    }

    protected function clinicalUsageAccessRequiresGate(): bool
    {
        return true;
    }

    protected function clinicalUsageAccessBlocksPage(): bool
    {
        return false;
    }

    abstract protected function clinicalUsageAccessContext(): ClinicalUsageAccessContext;

    protected function clinicalUsageAccessRecordId(): ?int
    {
        return null;
    }

    protected function clinicalUsageAccessSubjectLabel(): ?string
    {
        return $this->clinicalUsageAccessContext()->label();
    }

    /**
     * @return array<int, Action>
     */
    protected function clinicalUsageAccessHeaderActions(): array
    {
        if (! $this->clinicalUsageAccessRequiresGate() || $this->clinicalUsageIsUnlocked()) {
            return [];
        }

        return [$this->unlockClinicalUsageAction()];
    }

    protected function unlockClinicalUsageAction(): Action
    {
        $blocksPage = $this->clinicalUsageAccessBlocksPage();

        return Action::make('unlockClinicalUsage')
            ->label(fn (): string => filled($this->pendingClinicalUsageAccessPublicId)
                ? 'Ingresar clave OTP'
                : 'Solicitar acceso clínico (OTP)')
            ->icon(Heroicon::OutlinedShieldExclamation)
            ->color('danger')
            ->visible(fn (): bool => $this->clinicalUsageAccessRequiresGate() && ! $this->clinicalUsageIsUnlocked())
            ->modalHeading('Ambiente restrictivo de IntegraCorp')
            ->modalDescription('Está por entrar a configurar el uso clínico. Cualquier cambio mal ejecutado en esta vista puede afectar telemedicina, operaciones y los cupos de los afiliados.')
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel(fn (): string => filled($this->pendingClinicalUsageAccessPublicId)
                ? 'Confirmar clave y entrar'
                : 'Solicitar clave OTP')
            ->modalIcon(Heroicon::OutlinedShieldExclamation)
            ->modalIconColor('danger')
            ->closeModalByClickingAway(! $blocksPage)
            ->closeModalByEscaping(! $blocksPage)
            ->modalCloseButton(! $blocksPage)
            ->modalCancelActionLabel($blocksPage ? 'Salir' : 'Cancelar')
            ->extraAttributes([
                'class' => FilamentIosButton::extraClassForFilamentColor('danger'),
            ])
            ->form([
                Placeholder::make('restrictive_warning')
                    ->hiddenLabel()
                    ->content(new HtmlString(
                        '<div style="padding: 12px 14px; border-radius: 10px; background: #fef2f2; border: 1px solid #fecaca; color: #7f1d1d; font-size: 0.925rem; line-height: 1.5;">'
                        .'<strong>Ambiente restrictivo de IntegraCorp.</strong> '
                        .'La clave OTP llega solo a usuarios SUPERADMIN. Ellos se la dictan al analista. '
                        .'El acceso vale únicamente para esta visita: si sale o recarga, deberá solicitar otra clave. '
                        .'Un cambio mal ejecutado acarrea consecuencias sobre los cupos clínicos del plan.'
                        .'</div>'
                    )),
                Placeholder::make('otp_status')
                    ->label('Estado de la clave')
                    ->content(fn (): string => $this->clinicalUsageAccessStatusMessage())
                    ->visible(fn (): bool => filled($this->pendingClinicalUsageAccessPublicId)),
                Toggle::make('use_own_code')
                    ->label('Usar la clave que yo solicité')
                    ->helperText('Su usuario tiene permiso para entrar sin esperar al SUPERADMIN. Queda registrado y se les avisa.')
                    ->live()
                    ->visible(fn (): bool => $this->canUseOwnClinicalUsageCode())
                    ->dehydrated(fn (): bool => $this->canUseOwnClinicalUsageCode()),
                Textarea::make('own_code_reason')
                    ->label('¿Por qué usa su propia clave?')
                    ->placeholder('Ej.: Ningún SUPERADMIN respondió y hay que corregir el cupo hoy.')
                    ->rows(2)
                    ->visible(fn (Get $get): bool => $this->canUseOwnClinicalUsageCode() && (bool) $get('use_own_code'))
                    ->required(fn (Get $get): bool => $this->canUseOwnClinicalUsageCode() && (bool) $get('use_own_code'))
                    ->minLength(SelfAuthorizedOtp::REASON_MIN_LENGTH)
                    ->maxLength(1000),
                OtpBoxesInput::make('otp_code')
                    ->label('Clave de 6 dígitos')
                    ->length(6)
                    ->autofocus()
                    ->required(fn (Get $get): bool => filled($this->pendingClinicalUsageAccessPublicId) && ! ($this->canUseOwnClinicalUsageCode() && (bool) $get('use_own_code')))
                    ->visible(fn (Get $get): bool => filled($this->pendingClinicalUsageAccessPublicId) && ! ($this->canUseOwnClinicalUsageCode() && (bool) $get('use_own_code')))
                    ->helperText('Un dígito por casilla. Puede pegar la clave completa. Pídala a un SUPERADMIN: si sale de esta pantalla, esta visita queda cerrada.'),
            ])
            ->extraModalFooterActions([
                Action::make('resendClinicalUsageOtp')
                    ->label(fn (): string => ($wait = $this->secondsUntilClinicalUsageOtpResend()) > 0
                        ? 'Reenviar clave ('.$wait.' s)'
                        : 'Reenviar clave')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('gray')
                    ->visible(fn (): bool => filled($this->pendingClinicalUsageAccessPublicId))
                    ->disabled(fn (): bool => $this->secondsUntilClinicalUsageOtpResend() > 0)
                    ->action(function (Action $action): void {
                        $this->resendPendingClinicalUsageAccess();
                        $action->halt();
                    }),
            ])
            ->action(function (array $data, Action $action): void {
                $this->handleClinicalUsageAccessModal($data, $action);
            });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleClinicalUsageAccessModal(array $data, Action $action): void
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            $action->halt();

            return;
        }

        if (ClinicalUsageAccessOtp::userMayBypass($user)) {
            $this->clinicalUsageUnlocked = true;
            $this->defaultAction = null;
            $this->onClinicalUsageUnlocked();
            $this->forceRender();

            return;
        }

        if (! filled($this->pendingClinicalUsageAccessPublicId)) {
            $this->issueClinicalUsageAccess($user);
            $action->halt();

            return;
        }

        $challenge = $this->pendingClinicalUsageAccessChallenge();
        if (! $challenge instanceof ClinicalUsageAccessChallenge || ! $challenge->isActive()) {
            $this->pendingClinicalUsageAccessPublicId = null;
            Notification::make()
                ->title('La clave ya no es válida')
                ->body('Solicite una nueva clave OTP. El acceso anterior venció o se agotó.')
                ->danger()
                ->send();
            $action->halt();

            return;
        }

        $useOwn = (bool) ($data['use_own_code'] ?? false);

        try {
            $code = SelfAuthorizedOtp::resolveCode($challenge, $user, SystemOtpCodeKind::ClinicalUsageAccess, $useOwn, $data['own_code_reason'] ?? null, $data['otp_code'] ?? null);
        } catch (\RuntimeException $exception) {
            Notification::make()->title('No se pudo usar su clave')->body($exception->getMessage())->danger()->send();
            $action->halt();

            return;
        }

        $code = preg_replace('/\D+/', '', $code) ?? '';
        if (! ClinicalUsageAccessOtp::verify($challenge, $code, (int) $user->id)) {
            $fresh = $challenge->fresh();
            $remaining = max(0, (int) ($fresh?->max_attempts ?? 0) - (int) ($fresh?->attempt_count ?? 0));
            if ($remaining < 1) {
                $this->pendingClinicalUsageAccessPublicId = null;
            }

            Notification::make()
                ->title('Clave incorrecta')
                ->body($remaining > 0
                    ? 'Quedan '.$remaining.' intento(s). Pídala de nuevo a un SUPERADMIN.'
                    : 'Se agotaron los intentos. Solicite una clave nueva.')
                ->danger()
                ->send();
            $action->halt();

            return;
        }

        ClinicalUsageAccessOtp::markConsumed($challenge);

        if ($useOwn) {
            $contacts = ClinicalUsageAccessOtp::superAdminContactPoints();

            try {
                SelfAuthorizedOtp::recordUse(SystemOtpCodeKind::ClinicalUsageAccess, $challenge, $user, (string) ($data['own_code_reason'] ?? ''), (string) ($this->clinicalUsageAccessSubjectLabel() ?? $this->clinicalUsageAccessContext()->label()), $contacts['emails'], $contacts['phones']);
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        $this->pendingClinicalUsageAccessPublicId = null;
        $this->clinicalUsageUnlocked = true;
        $this->defaultAction = null;
        $this->onClinicalUsageUnlocked();
        $this->forceRender();

        Notification::make()
            ->title('Acceso autorizado para esta visita')
            ->body('Puede configurar el uso clínico. Si sale de esta pantalla deberá solicitar otra clave.')
            ->success()
            ->send();
    }

    protected function issueClinicalUsageAccess(User $user): void
    {
        try {
            $issued = ClinicalUsageAccessOtp::issue(
                $user,
                $this->clinicalUsageAccessContext(),
                $this->clinicalUsageAccessRecordId(),
                $this->clinicalUsageAccessSubjectLabel(),
            );
        } catch (ClinicalEntitlementException $exception) {
            Notification::make()
                ->title('No se pudo enviar la clave')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        $this->pendingClinicalUsageAccessPublicId = $issued['challenge']->public_id;

        if (! ($issued['delivered'] ?? true)) {
            Notification::make()
                ->title('La clave no se pudo entregar')
                ->body('No llegó a ningún SUPERADMIN. Como tiene permiso, marque «Usar la clave que yo solicité» para entrar.')
                ->warning()
                ->persistent()
                ->send();

            return;
        }

        Notification::make()
            ->title('Clave enviada a SUPERADMIN')
            ->body('La OTP se entregó a '.$issued['emails'].' correo(s) y '.$issued['phones'].' WhatsApp de usuarios SUPERADMIN. Pídasela para entrar.')
            ->success()
            ->send();
    }

    protected function resendPendingClinicalUsageAccess(): void
    {
        $user = Auth::user();
        $challenge = $this->pendingClinicalUsageAccessChallenge();
        if (! $user instanceof User || ! $challenge instanceof ClinicalUsageAccessChallenge) {
            Notification::make()
                ->title('No hay una clave pendiente')
                ->body('Solicite primero una clave OTP.')
                ->danger()
                ->send();

            return;
        }

        try {
            $issued = ClinicalUsageAccessOtp::resend(
                $challenge,
                $user,
                $this->clinicalUsageAccessContext(),
                $this->clinicalUsageAccessRecordId(),
                $this->clinicalUsageAccessSubjectLabel(),
            );
        } catch (ClinicalEntitlementException $exception) {
            Notification::make()
                ->title('No se pudo reenviar la clave')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        $this->pendingClinicalUsageAccessPublicId = $issued['challenge']->public_id;

        Notification::make()
            ->title('Clave reenviada a SUPERADMIN')
            ->body('Pídasela de nuevo a un SUPERADMIN. Sigue valiendo solo para esta visita.')
            ->success()
            ->send();
    }

    protected function pendingClinicalUsageAccessChallenge(): ?ClinicalUsageAccessChallenge
    {
        if (! filled($this->pendingClinicalUsageAccessPublicId)) {
            return null;
        }

        $user = Auth::user();
        if (! $user instanceof User) {
            return null;
        }

        return ClinicalUsageAccessChallenge::query()
            ->where('public_id', $this->pendingClinicalUsageAccessPublicId)
            ->where('user_id', $user->id)
            ->first();
    }

    protected function secondsUntilClinicalUsageOtpResend(): int
    {
        return $this->pendingClinicalUsageAccessChallenge()?->secondsUntilResend() ?? 0;
    }

    protected function clinicalUsageAccessStatusMessage(): string
    {
        $challenge = $this->pendingClinicalUsageAccessChallenge();
        if (! $challenge instanceof ClinicalUsageAccessChallenge) {
            return 'Solicite la clave para continuar.';
        }

        $ttl = (int) config('clinical-entitlements.otp.ttl_minutes', 5);

        return 'La clave ya se envió a los SUPERADMIN ('.$challenge->emails_sent.' correo(s), '.$challenge->phones_sent.' WhatsApp). Vence en '.$ttl.' minutos y es de un solo uso.';
    }

    protected function onClinicalUsageUnlocked(): void {}

    protected function canUseOwnClinicalUsageCode(): bool
    {
        $user = Auth::user();

        return filled($this->pendingClinicalUsageAccessPublicId)
            && $user instanceof User
            && SelfAuthorizedOtp::userCan($user, SystemOtpCodeKind::ClinicalUsageAccess);
    }

    protected function assertClinicalUsageUnlocked(): void
    {
        abort_unless($this->clinicalUsageIsUnlocked(), 403, 'Debe autorizar esta visita con la clave OTP de un SUPERADMIN.');
    }
}
