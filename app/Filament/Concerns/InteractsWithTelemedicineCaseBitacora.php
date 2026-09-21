<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Jobs\SendTelemedicineCaseBitacoraJob;
use App\Models\TelemedicineCase;
use App\Services\TelemedicineCaseBitacoraPdfService;
use App\Support\Filament\FilamentIosButton;
use App\Support\Operations\TelemedicineCaseBitacora;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait InteractsWithTelemedicineCaseBitacora
{
    public string $search = '';

    public ?int $selectedCaseId = null;

    public function updatedSearch(): void
    {
        $this->search = trim($this->search);
    }

    /**
     * @return array<string, mixed>|null
     */
    #[Computed]
    public function dossier(): ?array
    {
        $case = $this->resolvedCase();

        return $case instanceof TelemedicineCase
            ? TelemedicineCaseBitacora::dossier($case)
            : null;
    }

    /**
     * @return list<array{id: int, label: string, status: string, patient: string, document: string}>
     */
    public function getSearchResultsProperty(): array
    {
        if ($this->selectedCaseId !== null || mb_strlen($this->search) < 2) {
            return [];
        }

        return TelemedicineCaseBitacora::searchResults($this->search, $this->bitacoraScope());
    }

    public function selectCase(int $caseId): void
    {
        $case = TelemedicineCaseBitacora::findAccessible($caseId, $this->bitacoraScope());

        if (! $case instanceof TelemedicineCase) {
            Notification::make()
                ->title('Caso no disponible')
                ->body('No se encontró el caso o no tiene permiso para consultarlo.')
                ->danger()
                ->send();

            return;
        }

        $this->selectedCaseId = (int) $case->id;
        $this->search = '';
        unset($this->dossier);
        $this->refreshHeaderActions();
    }

    public function clearCase(): void
    {
        $this->selectedCaseId = null;
        unset($this->dossier);
        $this->refreshHeaderActions();
    }

    public function getHeading(): string|Htmlable
    {
        return 'Bitácora de Caso';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Busque por código de caso, nombre o cédula del paciente. La bitácora reúne el expediente hasta el alta médica.';
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadPdf')
                ->label('Descargar PDF')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('primary')
                ->hidden(fn (): bool => ! $this->hasSelectedCase())
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('primary'),
                ])
                ->action(fn (): ?StreamedResponse => $this->downloadPdf()),
            Action::make('sendWhatsApp')
                ->label('Enviar por WhatsApp')
                ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                ->color('success')
                ->hidden(fn (): bool => ! $this->hasSelectedCase())
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('success'),
                ])
                ->modalHeading('Enviar bitácora por WhatsApp')
                ->modalDescription('Se usará el teléfono del paciente. Puede editarlo antes de enviar.')
                ->modalWidth(Width::Medium)
                ->modalSubmitActionLabel('Enviar')
                ->fillForm(fn (): array => [
                    'phone' => (string) data_get($this->dossier, 'contacts.phone', ''),
                ])
                ->form([
                    TextInput::make('phone')
                        ->label('WhatsApp / Teléfono')
                        ->tel()
                        ->required()
                        ->maxLength(30)
                        ->placeholder('Ej: 04127018390'),
                ])
                ->action(function (array $data): void {
                    $this->queueDelivery(phone: trim((string) ($data['phone'] ?? '')), email: null);
                }),
            Action::make('sendEmail')
                ->label('Enviar por correo')
                ->icon(Heroicon::OutlinedEnvelope)
                ->color('info')
                ->hidden(fn (): bool => ! $this->hasSelectedCase())
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('info'),
                ])
                ->modalHeading('Enviar bitácora por correo')
                ->modalDescription('Se usará el correo del paciente. Puede editarlo antes de enviar.')
                ->modalWidth(Width::Medium)
                ->modalSubmitActionLabel('Enviar')
                ->fillForm(fn (): array => [
                    'email' => (string) data_get($this->dossier, 'contacts.email', ''),
                ])
                ->form([
                    TextInput::make('email')
                        ->label('Correo electrónico')
                        ->email()
                        ->required()
                        ->maxLength(255)
                        ->placeholder('paciente@ejemplo.com'),
                ])
                ->action(function (array $data): void {
                    $this->queueDelivery(phone: null, email: trim((string) ($data['email'] ?? '')));
                }),
        ];
    }

    protected function bitacoraScope(): string
    {
        return TelemedicineCaseBitacora::SCOPE_OPERATIONS;
    }

    private function hasSelectedCase(): bool
    {
        return $this->selectedCaseId !== null && is_array($this->dossier);
    }

    private function refreshHeaderActions(): void
    {
        $this->cachedHeaderActions = [];
        $this->cacheInteractsWithHeaderActions();
    }

    private function downloadPdf(): ?StreamedResponse
    {
        $case = $this->resolvedCase();

        if (! $case instanceof TelemedicineCase) {
            Notification::make()
                ->title('Seleccione un caso')
                ->body('Busque y elija un caso antes de descargar la bitácora.')
                ->warning()
                ->send();

            return null;
        }

        $relativePath = TelemedicineCaseBitacoraPdfService::ensure($case);
        $absolute = public_path('storage/'.$relativePath);
        $filename = TelemedicineCaseBitacora::documentName($case);

        return response()->streamDownload(function () use ($absolute): void {
            echo (string) file_get_contents($absolute);
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    private function queueDelivery(?string $phone, ?string $email): void
    {
        $case = $this->resolvedCase();

        if (! $case instanceof TelemedicineCase) {
            Notification::make()
                ->title('Seleccione un caso')
                ->warning()
                ->send();

            return;
        }

        $phone = trim((string) $phone);
        $email = trim((string) $email);

        if ($phone === '' && $email === '') {
            Notification::make()
                ->title('Datos incompletos')
                ->body('Indique un teléfono o un correo electrónico.')
                ->danger()
                ->send();

            return;
        }

        SendTelemedicineCaseBitacoraJob::dispatch(
            (int) $case->id,
            $phone !== '' ? $phone : null,
            $email !== '' ? $email : null,
            (int) Auth::id(),
        );

        $channel = $phone !== '' ? 'WhatsApp' : 'correo electrónico';

        Notification::make()
            ->title('Envío en cola')
            ->body('La bitácora se está generando y se enviará por '.$channel.'. Recibirá una notificación al completar.')
            ->success()
            ->send();
    }

    private function resolvedCase(): ?TelemedicineCase
    {
        if ($this->selectedCaseId === null) {
            return null;
        }

        return TelemedicineCaseBitacora::findAccessible($this->selectedCaseId, $this->bitacoraScope());
    }
}
