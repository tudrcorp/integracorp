<?php

declare(strict_types=1);

namespace App\Livewire\Operations;

use App\Models\ObservationCase;
use App\Models\TelemedicineCase;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class PatientCaseObservationPanel extends Component
{
    public int $telemedicineCaseId;

    public string $description = '';

    public function mount(int $telemedicineCaseId): void
    {
        $this->telemedicineCaseId = $telemedicineCaseId;
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function rules(): array
    {
        return [
            'description' => ['required', 'string', 'min:2', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'description' => 'observación',
        ];
    }

    public function save(): void
    {
        $this->validate();

        $userId = Auth::id();
        if ($userId === null) {
            Notification::make()
                ->title('Sesión requerida')
                ->body('Inicie sesión para registrar observaciones.')
                ->danger()
                ->send();

            return;
        }

        TelemedicineCase::query()->findOrFail($this->telemedicineCaseId);

        ObservationCase::query()->create([
            'telemedicine_case_id' => $this->telemedicineCaseId,
            'description' => trim($this->description),
            'created_by' => (string) $userId,
        ]);

        $this->reset('description');
        $this->resetValidation();

        Notification::make()
            ->title('Observación registrada')
            ->body('La nota quedó guardada en el historial del caso.')
            ->success()
            ->send();
    }

    public function render(): View
    {
        $latestObservation = ObservationCase::query()
            ->where('telemedicine_case_id', $this->telemedicineCaseId)
            ->with(['createdBy:id,name,email'])
            ->latest()
            ->first();

        return view('livewire.operations.patient-case-observation-panel', [
            'latestObservation' => $latestObservation,
        ]);
    }
}
