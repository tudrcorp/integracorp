<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithQuotePdfCoverageTable;
use App\Support\QuotePdfPlanStructure;
use Livewire\Component;

/**
 * Página del plan de una propuesta económica compuesta desde la estructura del
 * plan, para los planes que no son Inicial, Ideal ni Especial.
 *
 * Esos tres siguen usando su plantilla histórica con la imagen de página
 * completa. Los demás caían antes en la imagen del Ideal, así que se imprimían
 * con el título «Plan Accidentes» y las columnas IDEAL US$ 1K…10K aunque sus
 * beneficios y coberturas fueran otros.
 *
 * Sirve tanto a la cotización individual como a la corporativa: la única
 * diferencia entre ambas es si la población se muestra cuando hay una sola
 * persona.
 */
class PlanesCotizacionEstructura extends Component
{
    use InteractsWithQuotePdfCoverageTable;

    /**
     * Sin tipar, igual que los componentes hermanos: la vista recibe la
     * colección agrupada por rango de edad tal como la arma el controlador.
     *
     * @var mixed
     */
    public $data = [];

    public ?string $name = null;

    public ?string $name_user = null;

    public ?string $number_control = null;

    public string $planTitle = 'Propuesta Económica';

    /** @var list<array{column_key: string, header_label: string}> */
    public array $benefitColumns = [];

    /** @var array<string, mixed> */
    public array $benefitRows = [];

    public bool $isDense = false;

    public bool $populationOnlyIfMultiple = false;

    public bool $compact = false;

    public bool $showConditions = true;

    public bool $storefrontFooter = false;

    /**
     * Filas de precio para un paquete de beneficios, que no tiene coberturas y
     * por lo tanto no puede desglosarse en columnas.
     *
     * @var list<array{age_range: string, total_persons: int, annual: float, biannual: float, quarterly: float}>
     */
    public array $flatRateRows = [];

    public function mount(
        mixed $data,
        ?string $name,
        ?string $name_user,
        ?string $number_control = null,
        int|string|null $planId = null,
        bool $populationOnlyIfMultiple = false,
        bool $compact = false,
        bool $showConditions = true,
        bool $storefrontFooter = false,
    ): void {
        $this->data = $data;
        $this->name = $name;
        $this->name_user = $name_user;
        $this->number_control = $number_control;
        $this->populationOnlyIfMultiple = $populationOnlyIfMultiple;
        $this->compact = $compact;
        $this->showConditions = $showConditions;
        $this->storefrontFooter = $storefrontFooter;

        $this->buildQuotePdfCoverageTable($data, $planId);

        $matrix = QuotePdfPlanStructure::benefitsMatrix($planId);

        $this->benefitColumns = $matrix['columns'];
        $this->benefitRows = $matrix['rows'];
        $this->isDense = $matrix['isDense'];
        $this->planTitle = QuotePdfPlanStructure::planTitle($planId);

        // Sin coberturas no hay columnas que desglosar: el precio se muestra
        // como una sola tarifa por rango de edad.
        if ($this->coverageColumns === []) {
            $this->flatRateRows = QuotePdfPlanStructure::flatRateRows($data);
        }
    }

    public function render()
    {
        return view('livewire.planes-cotizacion-estructura');
    }
}
