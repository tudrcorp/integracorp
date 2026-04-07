<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\Concerns;

use Filament\Actions\Action;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\View\View;
use Illuminate\Support\HtmlString;

trait HasMedicamentosStepInfoModal
{
    public function openMedicamentosStepInfoModal(): void
    {
        $this->mountAction('medicamentosStepInfoModal');
    }

    protected function medicamentosStepInfoModalAction(): Action
    {
        return Action::make('medicamentosStepInfoModal')
            ->modalHeading(new HtmlString('<span class="sr-only">Medicamentos e indicaciones</span>'))
            ->modalDescription(null)
            ->modalWidth(Width::ExtraLarge)
            ->modalContent(fn (): View => view('filament.modals.medicamentos-step-info'))
            ->modalIcon(null)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Entendido')
            ->closeModalByClickingAway(true)
            ->modalCancelAction(
                fn (Action $action) => $action
                    ->color('primary')
                    ->extraAttributes([
                        'class' => 'w-full justify-center rounded-xl py-3 font-semibold shadow-md transition active:scale-[0.98] dark:shadow-sky-900/40 sm:w-auto',
                        'style' => 'box-shadow: 0 4px 14px rgba(2, 132, 199, 0.25), inset 0 1px 0 rgba(255,255,255,0.2);',
                    ])
            )
            ->extraModalWindowAttributes([
                'class' => 'medicamentos-step-info-modal-window !max-w-[32rem] sm:!max-w-[36rem] overflow-hidden rounded-3xl shadow-2xl ring-1 ring-black/5 dark:bg-zinc-900 dark:ring-white/15',
                'style' => 'box-shadow: 0 25px 50px -12px rgba(0,0,0,0.18), 0 0 0 1px rgba(0,0,0,0.04), inset 0 1px 0 rgba(255,255,255,0.65);',
            ], merge: false);
    }
}
