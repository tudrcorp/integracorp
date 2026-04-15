<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Models\RrhhColaborador;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

trait PreparesHelpdeskColaboradorAssigneesOnCreate
{
    /**
     * @var list<int>
     */
    protected array $helpdeskColaboradorIdsPendingValidation = [];

    /**
     * @var list<int>
     */
    protected array $helpdeskCcColaboradorIdsPendingValidation = [];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepareHelpdeskColaboradorAssigneesForCreate(array $data): array
    {
        $data['status'] ??= 'PENDIENTE POR INICIAR';

        $ids = $data['rrhhColaboradores'] ?? [];
        if (! is_array($ids)) {
            $ids = [];
        }

        $ids = array_values(array_unique(array_map(
            static fn (mixed $v): int => (int) $v,
            array_filter($ids, static fn (mixed $v): bool => filled($v))
        )));

        $myColaboradorId = RrhhColaborador::query()->where('user_id', Auth::id())->value('id');
        if ($myColaboradorId !== null) {
            $ids = array_values(array_diff($ids, [$myColaboradorId]));
        }

        $data['rrhhColaboradores'] = $ids;
        $this->helpdeskColaboradorIdsPendingValidation = $ids;

        $ccIds = $data['cc_colaboradores'] ?? null;
        if (! is_array($ccIds)) {
            $ccIds = [];
        }

        $ccIds = array_values(array_unique(array_map(
            static fn (mixed $v): int => (int) $v,
            array_filter($ccIds, static fn (mixed $v): bool => filled($v))
        )));

        if ($myColaboradorId !== null) {
            $ccIds = array_values(array_diff($ccIds, [$myColaboradorId]));
        }

        $ccIds = array_values(array_diff($ccIds, $ids));

        $data['cc_colaboradores'] = $ccIds === [] ? null : $ccIds;
        $this->helpdeskCcColaboradorIdsPendingValidation = $ccIds;

        return $data;
    }

    protected function validatePendingHelpdeskColaboradorAssigneesOrHalt(): void
    {
        foreach ($this->helpdeskColaboradorIdsPendingValidation as $colaboradorId) {
            $this->assertHelpdeskColaboradorHasUserOrHalt($colaboradorId);
            $this->assertHelpdeskColaboradorHasCorporateEmailOrHalt($colaboradorId);
        }

        foreach ($this->helpdeskCcColaboradorIdsPendingValidation as $colaboradorId) {
            $this->assertHelpdeskColaboradorHasCorporateEmailOrHalt($colaboradorId);
        }
    }

    protected function assertHelpdeskColaboradorHasUserOrHalt(int $colaboradorId): void
    {
        $colaborador = RrhhColaborador::query()->where('id', $colaboradorId)->first(['id', 'user_id']);
        if ($colaborador === null || blank($colaborador->user_id)) {
            Notification::make()
                ->title('ERROR')
                ->body('Un colaborador asignado no posee ID de usuario en sistema. Por favor, seleccione solo colaboradores con usuario en sistema.')
                ->icon('heroicon-m-tag')
                ->iconColor('danger')
                ->danger()
                ->send();
            $this->halt();
        }
    }

    protected function assertHelpdeskColaboradorHasCorporateEmailOrHalt(int $colaboradorId): void
    {
        $emailCorporativo = RrhhColaborador::query()->where('id', $colaboradorId)->value('emailCorporativo');
        if (blank($emailCorporativo)) {
            Notification::make()
                ->title('ERROR')
                ->body('Un colaborador asignado no posee correo electrónico corporativo. Por favor, seleccione colaboradores con correo corporativo.')
                ->icon('heroicon-m-tag')
                ->iconColor('danger')
                ->danger()
                ->send();
            $this->halt();
        }
    }
}
