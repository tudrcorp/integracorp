<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Support\HelpdeskTicketCreationGate;
use Filament\Notifications\Notification;

trait AssertsHelpdeskTicketCreationAccess
{
    abstract protected static function helpdeskTicketCreationEnforcesQuota(): bool;

    public static function canAccess(array $parameters = []): bool
    {
        return static::getResource()::canCreate();
    }

    public function mount(): void
    {
        parent::mount();

        $this->assertHelpdeskCreationAllowedOrRedirect();
    }

    protected function beforeCreate(): void
    {
        $this->assertHelpdeskCreationAllowedOrHalt();

        if (method_exists($this, 'validatePendingHelpdeskColaboradorAssigneesOrHalt')) {
            $this->validatePendingHelpdeskColaboradorAssigneesOrHalt();
        }
    }

    protected function assertHelpdeskCreationAllowedOrRedirect(): void
    {
        $verdict = HelpdeskTicketCreationGate::allowsCreation(
            enforceGroupQuota: static::helpdeskTicketCreationEnforcesQuota(),
        );

        if ($verdict->allowed) {
            return;
        }

        Notification::make()
            ->title('No puede crear tickets')
            ->body($verdict->message)
            ->icon('heroicon-m-no-symbol')
            ->iconColor('danger')
            ->danger()
            ->persistent()
            ->send();

        $this->redirect(static::getResource()::getUrl('index'));
    }

    protected function assertHelpdeskCreationAllowedOrHalt(): void
    {
        $verdict = HelpdeskTicketCreationGate::allowsCreation(
            enforceGroupQuota: static::helpdeskTicketCreationEnforcesQuota(),
        );

        if ($verdict->allowed) {
            return;
        }

        Notification::make()
            ->title('No puede crear tickets')
            ->body($verdict->message)
            ->icon('heroicon-m-no-symbol')
            ->iconColor('danger')
            ->danger()
            ->send();

        $this->halt();
    }
}
