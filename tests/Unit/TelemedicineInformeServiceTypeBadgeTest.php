<?php

declare(strict_types=1);

use App\Models\TelemedicineConsultationPatient;
use App\Models\TelemedicineServiceList;
use App\Support\Telemedicine\TelemedicineInformeServiceType;

uses(Tests\TestCase::class);

/**
 * El informe médico (inicial y seguimiento) muestra el servicio real como badge en la
 * cabecera, junto a la etapa «Inicial» / «Seguimiento». Solo lee la base.
 */
function informeTemplate(): string
{
    return (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/documents/partials/informe-medico-homologado.blade.php');
}

it('quita el tipo de servicio de los datos del paciente y lo pone como badge antes de la etapa', function (): void {
    $template = informeTemplate();
    $serviceBadge = strpos($template, "\$data['service_type_label']");
    $stageBadge = strpos($template, "'Seguimiento' : 'Inicial'");

    expect($template)->not->toContain('<div class="label">Tipo de servicio</div>')
        ->and($template)->not->toContain("'Consulta inicial'")
        ->and($serviceBadge)->not->toBeFalse()
        ->and($stageBadge)->not->toBeFalse()
        ->and($serviceBadge)->toBeLessThan($stageBadge);
});

it('el render resuelve el servicio en el paso único de los informes', function (): void {
    expect((string) file_get_contents(dirname(__DIR__, 2).'/app/Support/Telemedicine/TelemedicineInformePdfRenderer.php'))
        ->toContain("\$data['service_type_label'] = TelemedicineInformeServiceType::forInformeData(\$data);");
});

it('toma el servicio que eligió el médico en la consulta', function (): void {
    $consultation = TelemedicineConsultationPatient::query()->whereNotNull('telemedicine_service_list_id')->latest('id')->first();

    if ($consultation === null) {
        $this->markTestSkipped('No hay consultas con tipo de servicio.');
    }

    $name = (string) TelemedicineServiceList::query()->whereKey($consultation->telemedicine_service_list_id)->value('name');

    expect(TelemedicineInformeServiceType::forInformeData(['telemedicine_consultation_id' => $consultation->id]))->toBe(trim($name))
        ->and(TelemedicineInformeServiceType::forInformeData(['telemedicine_service_list_id' => $consultation->telemedicine_service_list_id]))->toBe(trim($name));
});

it('respeta un valor ya resuelto y cae en Telemedicina si no hay servicio', function (): void {
    expect(TelemedicineInformeServiceType::forInformeData(['service_type_label' => ' HOME CARE ']))->toBe('HOME CARE')
        ->and(TelemedicineInformeServiceType::forInformeData([]))->toBe('Telemedicina')
        ->and(TelemedicineInformeServiceType::forInformeData(['telemedicine_service_list_id' => 999999999]))->toBe('Telemedicina')
        ->and(TelemedicineInformeServiceType::forInformeData(['telemedicine_consultation_id' => 999999999]))->toBe('Telemedicina');
});

it('el récipe muestra el servicio en vez de Original/Copia y ya no lo repite en los datos del paciente', function (): void {
    $recipe = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/documents/partials/telemedicine-recipe-homologado.blade.php');

    expect($recipe)->toContain('TelemedicineInformeServiceType::forInformeData(')
        ->and($recipe)->toContain('<span class="badge">{{ $serviceTypeLabel }}</span>')
        ->and($recipe)->not->toContain('<span class="badge">{{ $copyLabel }}</span>')
        ->and($recipe)->not->toContain('<div class="label">Tipo de servicio</div>')
        ->and(strpos($recipe, '$serviceTypeLabel }}</span>'))->toBeLessThan(strpos($recipe, '$coverageGroup }}</span>'));
});
