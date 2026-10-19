<?php

declare(strict_types=1);

use App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\Concerns\HasInformAmdModal;
use Illuminate\Validation\ValidationException;

uses(Tests\TestCase::class);

/*
 * «Informar AMD» no abría su modal si la consulta tenía un campo obligatorio sin
 * llenar en otro paso (p. ej. el servicio de deriva): leer el formulario con
 * getState() valida la consulta entera y la excepción cortaba la acción.
 */

/**
 * Página que usa el trait, con un formulario incompleto: validarlo lanza la
 * excepción; leerlo en crudo devuelve lo que el médico lleva escrito.
 */
function informAmdPageWithIncompleteForm(): object
{
    $form = new class
    {
        public function getState(): array
        {
            throw ValidationException::withMessages([
                'data.telemedicine_service_list_drift_id' => 'El campo tipo de servicio de deriva es obligatorio.',
            ]);
        }

        /**
         * @return array<string, mixed>
         */
        public function getRawState(): array
        {
            return [
                'telemedicine_service_list_id' => '2',
                'telemedicine_service_list_drift_id' => null,
                'reason_consultation' => 'FIEBRE',
                'actual_phatology' => 'TOS DE 5 DÍAS',
                'pa' => '120/80',
                'fc' => '88',
            ];
        }
    };

    return new class($form)
    {
        use HasInformAmdModal;

        public function __construct(public object $form) {}
    };
}

it('precarga el modal de Informar AMD aunque la consulta tenga campos obligatorios vacíos', function (): void {
    $page = informAmdPageWithIncompleteForm();

    $defaults = (fn (): array => $this->informAmdFormDefaults())->call($page);

    expect($defaults)
        ->toMatchArray([
            'reason_consultation' => 'FIEBRE',
            'actual_phatology' => 'TOS DE 5 DÍAS',
            'pa' => '120/80',
            'fc' => '88',
            'background' => null,
        ]);
});

it('las acciones AMD nunca validan la consulta entera al abrirse o guardarse', function (): void {
    $source = (string) file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Concerns/HasInformAmdModal.php'
    );
    $code = preg_replace('#/\*.*?\*/#s', '', $source);

    expect($code)->not->toContain('->getState()')
        ->and(substr_count($code, '$this->form->getRawState()'))->toBe(3);
});
