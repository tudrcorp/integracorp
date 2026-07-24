<?php

declare(strict_types=1);

namespace App\Support\Rrhh;

use App\Models\RrhhAsignacion;
use App\Models\RrhhColaborador;
use App\Models\RrhhDeduccion;
use App\Models\RrhhDetalleNomina;
use App\Models\RrhhNomina;
use App\Models\RrhhPrestamo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class RrhhNominaCalculator
{
    /**
     * @param  array{anio: int|string, periodo: int|string, tasa_bcv: float|int|string}  $input
     */
    public function calculate(array $input): RrhhNomina
    {
        $periodo = RrhhNominaPeriodo::resolve((int) $input['anio'], (int) $input['periodo']);
        $tasaBcv = round((float) $input['tasa_bcv'], 4);

        if ($tasaBcv <= 0) {
            throw new \InvalidArgumentException('La tasa BCV debe ser mayor a cero.');
        }

        $yaCalculada = RrhhNomina::query()
            ->where('anio', $periodo['anio'])
            ->where('periodo', $periodo['periodo'])
            ->exists();

        if ($yaCalculada) {
            throw new \InvalidArgumentException(
                'Ya existe un cálculo para el período '.$periodo['label'].'. Elimínelo si desea recalcularlo.'
            );
        }

        return DB::transaction(function () use ($periodo, $tasaBcv): RrhhNomina {
            $colaboradores = RrhhColaborador::query()
                ->with(['asignaciones', 'deducciones', 'departamento', 'cargo'])
                ->where('status', 'activo')
                ->orderBy('fullName')
                ->get();

            $totalSalarios = 0.0;
            $totalDescuentos = 0.0;
            $totalAsignaciones = 0.0;
            $totalPrestamos = 0.0;
            $detalles = [];

            foreach ($colaboradores as $colaborador) {
                $sueldoMensual = round((float) ($colaborador->sueldo ?? 0), 2);
                $salario = RrhhNominaPeriodo::sueldoDelPeriodo($sueldoMensual);
                $detalleAsignaciones = $this->mapConceptos($colaborador->asignacionesAplicables(), $salario);
                $detalleDescuentos = $this->mapConceptos($colaborador->deduccionesAplicables(), $salario);
                $detallePrestamos = $this->mapPrestamosActivos((int) $colaborador->id);

                $asignaciones = round((float) collect($detalleAsignaciones)->sum('monto_calculado'), 2);
                $descuentos = round((float) collect($detalleDescuentos)->sum('monto_calculado'), 2);
                $prestamos = round((float) collect($detallePrestamos)->sum('monto_cuota'), 2);
                $neto = round($salario + $asignaciones - $descuentos - $prestamos, 2);

                $totalSalarios += $salario;
                $totalDescuentos += $descuentos;
                $totalAsignaciones += $asignaciones;
                $totalPrestamos += $prestamos;

                $detalles[] = [
                    'colaborador_id' => $colaborador->id,
                    'colaborador_nombre' => $colaborador->fullName,
                    'colaborador_cedula' => $colaborador->cedula,
                    'cargo_id' => $colaborador->cargo_id ?: 0,
                    'cargo_nombre' => $colaborador->cargo?->description,
                    'departamento_id' => $colaborador->departmento_id ?: 0,
                    'departamento_nombre' => $colaborador->departamento?->description,
                    'salario' => $salario,
                    'salario_ves' => $this->toVes($salario, $tasaBcv),
                    'monto_descuento' => $descuentos,
                    'monto_descuento_ves' => $this->toVes($descuentos, $tasaBcv),
                    'monto_bono' => $asignaciones,
                    'monto_bono_ves' => $this->toVes($asignaciones, $tasaBcv),
                    'monto_prestamo' => $prestamos,
                    'monto_prestamo_ves' => $this->toVes($prestamos, $tasaBcv),
                    'monto_otros' => 0,
                    'monto_total' => $neto,
                    'monto_total_ves' => $this->toVes($neto, $tasaBcv),
                    'detalle_asignaciones' => $detalleAsignaciones,
                    'detalle_descuentos' => $detalleDescuentos,
                    'detalle_prestamos' => $detallePrestamos,
                    'created_by' => Auth::id() ?? 0,
                ];
            }

            $totalSalarios = round($totalSalarios, 2);
            $totalDescuentos = round($totalDescuentos, 2);
            $totalAsignaciones = round($totalAsignaciones, 2);
            $totalPrestamos = round($totalPrestamos, 2);
            $totalNeto = round($totalSalarios + $totalAsignaciones - $totalDescuentos - $totalPrestamos, 2);

            $nomina = RrhhNomina::query()->create([
                'anio' => $periodo['anio'],
                'periodo' => $periodo['periodo'],
                'fecha_desde' => $periodo['fecha_desde'],
                'fecha_hasta' => $periodo['fecha_hasta'],
                'tasa_bcv' => $tasaBcv,
                'total_salarios' => $totalSalarios,
                'total_descuentos' => $totalDescuentos,
                'total_asignaciones' => $totalAsignaciones,
                'total_prestamos' => $totalPrestamos,
                'total_neto' => $totalNeto,
                'total_salarios_ves' => $this->toVes($totalSalarios, $tasaBcv),
                'total_descuentos_ves' => $this->toVes($totalDescuentos, $tasaBcv),
                'total_asignaciones_ves' => $this->toVes($totalAsignaciones, $tasaBcv),
                'total_prestamos_ves' => $this->toVes($totalPrestamos, $tasaBcv),
                'total_neto_ves' => $this->toVes($totalNeto, $tasaBcv),
                'created_by' => Auth::id() ?? 0,
            ]);

            foreach ($detalles as $detalle) {
                RrhhDetalleNomina::query()->create([
                    ...$detalle,
                    'nomina_id' => $nomina->id,
                ]);
            }

            return $nomina->fresh('detalleNomina');
        });
    }

    public function toVes(float $usd, float $tasaBcv): float
    {
        return round($usd * $tasaBcv, 2);
    }

    /**
     * @param  Collection<int, RrhhAsignacion|RrhhDeduccion>  $conceptos
     * @return array<int, array<string, mixed>>
     */
    private function mapConceptos(Collection $conceptos, float $sueldoBasePeriodo): array
    {
        return $conceptos
            ->map(function (RrhhAsignacion|RrhhDeduccion $concepto) use ($sueldoBasePeriodo): array {
                $montoCalculado = RrhhValorCalculo::calcular(
                    $concepto->tipo_valor,
                    $concepto->monto,
                    $concepto->porcentaje,
                    $sueldoBasePeriodo,
                );

                return [
                    'id' => $concepto->id,
                    'name' => $concepto->name,
                    'description' => $concepto->description,
                    'tipo_valor' => $concepto->tipo_valor,
                    'valor_referencia' => $concepto->tipo_valor === RrhhValorCalculo::TIPO_PORCENTAJE
                        ? (float) $concepto->porcentaje
                        : (float) $concepto->monto,
                    'monto_calculado' => $montoCalculado,
                    'aplicacion' => $concepto->aplicacion,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function mapPrestamosActivos(int $colaboradorId): array
    {
        return RrhhPrestamo::query()
            ->where('colaborador_id', $colaboradorId)
            ->where('status', 'activo')
            ->orderBy('id')
            ->get()
            ->map(fn (RrhhPrestamo $prestamo): array => [
                'id' => $prestamo->id,
                'descripcion' => $prestamo->descripcion,
                'monto_prestamo' => (float) $prestamo->monto,
                'interes' => (float) ($prestamo->interes ?? 0),
                'nro_cuotas' => (int) $prestamo->nro_cuotas,
                'monto_cuota' => (float) ($prestamo->monto_cuota ?? 0),
                'status' => $prestamo->status,
            ])
            ->values()
            ->all();
    }
}
