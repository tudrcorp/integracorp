<?php

declare(strict_types=1);

namespace App\Livewire\Operations;

use App\Models\OperationCoordinationClinicDocument;
use App\Models\OperationCoordinationService;
use App\Support\SecurityAudit;
use App\Support\Telemedicine\TelemedicineDerivedServiceBadge;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class ClinicCoordinationDocumentsManager extends Component
{
    use WithFileUploads;

    public int $serviceId;

    public bool $readOnly = false;

    /** @var array<int, TemporaryUploadedFile> */
    public array $ingresoUploads = [];

    /** @var array<int, TemporaryUploadedFile> */
    public array $egresoUploads = [];

    public function mount(int $serviceId, bool $readOnly = false): void
    {
        $this->serviceId = $serviceId;
        $this->syncReadOnlyFlag($readOnly);

        SecurityAudit::log(
            action: 'AUDIT_OPERATIONS_COORDINATION_CLINIC_DOCUMENTS_PANEL_OPENED',
            route: 'livewire.operations.clinic-coordination-documents-manager',
            details: [
                'operation_coordination_service_id' => $this->serviceId,
                'read_only' => $this->readOnly,
            ],
        );
    }

    public function removeIngresoUpload(int $index): void
    {
        if ($this->isLockedForEdits()) {
            return;
        }

        if (! isset($this->ingresoUploads[$index])) {
            return;
        }

        unset($this->ingresoUploads[$index]);
        $this->ingresoUploads = array_values($this->ingresoUploads);
        $this->resetValidation('ingresoUploads');
    }

    public function removeEgresoUpload(int $index): void
    {
        if ($this->isLockedForEdits()) {
            return;
        }

        if (! isset($this->egresoUploads[$index])) {
            return;
        }

        unset($this->egresoUploads[$index]);
        $this->egresoUploads = array_values($this->egresoUploads);
        $this->resetValidation('egresoUploads');
    }

    public function saveIngreso(): void
    {
        if ($this->isLockedForEdits()) {
            abort(403);
        }

        $this->validateIngresoUploads();

        if ($this->ingresoUploads === []) {
            Notification::make()
                ->title('Sin archivos')
                ->body('Seleccione uno o más documentos de ingreso a clínica antes de guardar.')
                ->warning()
                ->send();

            return;
        }

        $service = $this->resolveService();
        $userId = Auth::id();
        $uploadedCount = count($this->ingresoUploads);

        DB::transaction(function () use ($service, $userId): void {
            foreach ($this->ingresoUploads as $file) {
                if (! $file instanceof TemporaryUploadedFile) {
                    continue;
                }

                $storedPath = $file->store(
                    path: 'operation-coordination-clinic-docs/'.$this->serviceId.'/ingreso',
                    options: 'public',
                );

                OperationCoordinationClinicDocument::query()->create([
                    'operation_coordination_service_id' => $this->serviceId,
                    'category' => OperationCoordinationClinicDocument::CATEGORY_INGRESO,
                    'path' => $storedPath,
                    'original_filename' => $file->getClientOriginalName(),
                    'mime_type' => ($m = $file->getMimeType()) ? (string) $m : null,
                    'size_bytes' => ($s = $file->getSize()) !== false ? (int) $s : null,
                    'uploaded_by_user_id' => $userId,
                ]);
            }

            $service->updated_by = Auth::user()?->name;
            $service->save();
        });

        SecurityAudit::log(
            action: 'AUDIT_OPERATIONS_COORDINATION_CLINIC_INGRESO_UPLOADED',
            route: 'livewire.operations.clinic-coordination-documents-manager',
            details: [
                'operation_coordination_service_id' => $this->serviceId,
                'files_count' => $uploadedCount,
            ],
        );

        $this->reset('ingresoUploads');

        Notification::make()
            ->title('Documentos de ingreso guardados')
            ->body('Los archivos quedaron registrados en la orden.')
            ->success()
            ->send();
    }

    public function saveEgreso(): void
    {
        if ($this->isLockedForEdits()) {
            abort(403);
        }

        $this->validateEgresoUploads();

        if ($this->egresoUploads === []) {
            Notification::make()
                ->title('Sin archivos de egreso')
                ->body('Seleccione al menos un documento de egreso de clínica. Al guardarlos, la orden pasará a estatus Finalizado.')
                ->warning()
                ->send();

            return;
        }

        $userId = Auth::id();

        DB::transaction(function () use ($userId): void {
            foreach ($this->egresoUploads as $file) {
                if (! $file instanceof TemporaryUploadedFile) {
                    continue;
                }

                $storedPath = $file->store(
                    path: 'operation-coordination-clinic-docs/'.$this->serviceId.'/egreso',
                    options: 'public',
                );

                OperationCoordinationClinicDocument::query()->create([
                    'operation_coordination_service_id' => $this->serviceId,
                    'category' => OperationCoordinationClinicDocument::CATEGORY_EGRESO,
                    'path' => $storedPath,
                    'original_filename' => $file->getClientOriginalName(),
                    'mime_type' => ($m = $file->getMimeType()) ? (string) $m : null,
                    'size_bytes' => ($s = $file->getSize()) !== false ? (int) $s : null,
                    'uploaded_by_user_id' => $userId,
                ]);
            }

            $service = OperationCoordinationService::query()->lockForUpdate()->findOrFail($this->serviceId);
            $service->status = 'FINALIZADO';
            $service->updated_by = Auth::user()?->name;
            $service->save();
        });

        $this->reset('egresoUploads');
        $this->readOnly = true;

        SecurityAudit::log(
            action: 'AUDIT_OPERATIONS_COORDINATION_CLINIC_EGRESO_UPLOADED_ORDER_FINALIZED',
            route: 'livewire.operations.clinic-coordination-documents-manager',
            details: [
                'operation_coordination_service_id' => $this->serviceId,
                'new_status' => 'FINALIZADO',
            ],
        );

        Notification::make()
            ->title('Egreso registrado y orden finalizada')
            ->body('Los documentos de egreso se guardaron y el estatus de la orden quedó en Finalizado.')
            ->success()
            ->send();
    }

    public function deleteDocument(int $documentId): void
    {
        if ($this->isLockedForEdits()) {
            abort(403);
        }

        $document = OperationCoordinationClinicDocument::query()
            ->whereKey($documentId)
            ->where('operation_coordination_service_id', $this->serviceId)
            ->firstOrFail();

        $path = $document->path;
        $category = $document->category;

        DB::transaction(function () use ($document): void {
            $document->delete();

            $service = OperationCoordinationService::query()->find($this->serviceId);
            if ($service !== null) {
                $service->updated_by = Auth::user()?->name;
                $service->save();
            }
        });

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }

        SecurityAudit::log(
            action: 'AUDIT_OPERATIONS_COORDINATION_CLINIC_DOCUMENT_DELETED',
            route: 'livewire.operations.clinic-coordination-documents-manager',
            details: [
                'document_id' => $documentId,
                'operation_coordination_service_id' => $this->serviceId,
                'category' => $category,
                'path' => $path,
            ],
        );

        Notification::make()
            ->title('Documento eliminado')
            ->body('El archivo fue retirado de la orden.')
            ->success()
            ->send();
    }

    public function render(): View
    {
        $service = OperationCoordinationService::query()->findOrFail($this->serviceId);

        $documents = OperationCoordinationClinicDocument::query()
            ->where('operation_coordination_service_id', $this->serviceId)
            ->orderBy('id')
            ->get();

        return view('livewire.operations.clinic-coordination-documents-manager', [
            'service' => $service,
            'ingresoDocuments' => $documents->where('category', OperationCoordinationClinicDocument::CATEGORY_INGRESO)->values(),
            'egresoDocuments' => $documents->where('category', OperationCoordinationClinicDocument::CATEGORY_EGRESO)->values(),
        ]);
    }

    private function syncReadOnlyFlag(bool $initialReadOnly): void
    {
        $status = OperationCoordinationService::query()->whereKey($this->serviceId)->value('status');
        $this->readOnly = $initialReadOnly || $status === 'FINALIZADO';
    }

    private function isLockedForEdits(): bool
    {
        $this->syncReadOnlyFlag($this->readOnly);

        return $this->readOnly;
    }

    private function resolveService(): OperationCoordinationService
    {
        $service = OperationCoordinationService::query()->findOrFail($this->serviceId);

        if (! TelemedicineDerivedServiceBadge::specificServiceIsIngresoAClinica($service->specific_service)) {
            abort(403);
        }

        if (in_array($service->status, ['CANCELADA', 'CANCELADO'], true)) {
            abort(403);
        }

        return $service;
    }

    private function validateIngresoUploads(): void
    {
        $this->validate([
            'ingresoUploads' => ['nullable', 'array'],
            'ingresoUploads.*' => ['file', 'max:2048', 'mimes:jpg,jpeg,png,webp,pdf'],
        ], [], [
            'ingresoUploads.*' => 'archivo de ingreso',
        ]);
    }

    private function validateEgresoUploads(): void
    {
        $this->validate([
            'egresoUploads' => ['nullable', 'array'],
            'egresoUploads.*' => ['file', 'max:2048', 'mimes:jpg,jpeg,png,webp,pdf'],
        ], [], [
            'egresoUploads.*' => 'archivo de egreso',
        ]);
    }
}
