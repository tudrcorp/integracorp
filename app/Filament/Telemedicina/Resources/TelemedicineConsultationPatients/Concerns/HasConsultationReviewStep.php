<?php

declare(strict_types=1);

namespace App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\Concerns;

use App\Models\TelemedicineAmdInform;
use App\Support\Telemedicine\TelemedicineAmdInformRegistrar;
use App\Support\Telemedicine\TelemedicineConsultationReview;
use App\Support\Telemedicine\TelemedicineConsultationWizardSteps;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Exceptions\Halt;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Paso final obligatorio «Revisar y registrar» del asistente de consulta.
 *
 * La revisión se arma bajo demanda (sólo cuando el médico llega al paso) y
 * nunca escribe: lee el estado del formulario. Desde ella el médico salta al
 * paso que quiere corregir y vuelve a la revisión validando lo intermedio.
 */
trait HasConsultationReviewStep
{
    /**
     * El médico ya pasó por la revisión: los pasos muestran «Volver a la revisión».
     */
    public bool $consultationReviewVisited = false;

    public function consultationReviewHtml(): string
    {
        $this->consultationReviewVisited = true;

        try {
            $review = TelemedicineConsultationReview::build($this->form->getRawState(), [
                'amd_inform' => $this->consultationReviewHasAmdInform(),
                'amd_exam' => method_exists($this, 'hasAmdPhysicalExam') && $this->hasAmdPhysicalExam(),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return view('filament.telemedicina.consultations.review.error')->render();
        }

        return view('filament.telemedicina.consultations.review.summary', ['review' => $review])->render();
    }

    /**
     * Valida los pasos anteriores a la revisión. Devuelve la clave del paso al
     * que debe ir el asistente: la revisión si todo está bien, o el primer paso
     * con errores (que quedan visibles en sus campos).
     */
    public function validateStepsBeforeReview(): string
    {
        $wizard = $this->consultationWizard();

        if (! $wizard instanceof Wizard) {
            return TelemedicineConsultationWizardSteps::REVIEW;
        }

        foreach ($wizard->getChildSchema()->getComponents() as $step) {
            if (! $step instanceof Step || $step->getKey(isAbsolute: false) === TelemedicineConsultationWizardSteps::REVIEW) {
                continue;
            }

            try {
                $step->callBeforeValidation();
                $step->getChildSchema()->validate();
                $step->callAfterValidation();
            } catch (ValidationException $exception) {
                $this->setErrorBag($exception->validator->errors());

                return (string) $step->getKey(isAbsolute: false);
            } catch (Halt) {
                return (string) $step->getKey(isAbsolute: false);
            }
        }

        $this->resetErrorBag();

        return TelemedicineConsultationWizardSteps::REVIEW;
    }

    private function consultationWizard(): ?Wizard
    {
        foreach ($this->form->getFlatComponents() as $component) {
            if ($component instanceof Wizard) {
                return $component;
            }
        }

        return null;
    }

    private function consultationReviewHasAmdInform(): bool
    {
        if (method_exists($this, 'resolveConsultationForInformAmd')) {
            $consultation = $this->resolveConsultationForInformAmd();

            if ($consultation !== null) {
                return TelemedicineAmdInform::query()->where('telemedicine_consultation_patient_id', $consultation->id)->exists();
            }
        }

        $pendingId = ($this->pendingAmdInformId ?? null) ?? session()->get(TelemedicineAmdInformRegistrar::SESSION_PENDING_INFORM_ID);

        return filled($pendingId) && TelemedicineAmdInform::query()->whereKey((int) $pendingId)->exists();
    }
}
