<?php

declare(strict_types=1);

use App\Support\Rrhh\RrhhPrestamoCuotaCalculo;

it('calcula el monto de cuota desde porcentaje sobre sueldo base', function (): void {
    expect(RrhhPrestamoCuotaCalculo::montoCuotaDesdePorcentaje(1000, 15))->toBe(150.0);
});

it('suma el total de descuentos por cuotas', function (): void {
    expect(RrhhPrestamoCuotaCalculo::totalDescuentos(5, 150))->toBe(750.0);
});

it('detecta cuando las cuotas no cuadran con el monto del prestamo', function (): void {
    expect(RrhhPrestamoCuotaCalculo::cuadraExacto(500, 5, 150))->toBeFalse()
        ->and(RrhhPrestamoCuotaCalculo::diferencia(500, 5, 150))->toBe(250.0);
});

it('acepta cuando las cuotas suman exactamente el prestamo', function (): void {
    expect(RrhhPrestamoCuotaCalculo::cuadraExacto(500, 5, 100))->toBeTrue()
        ->and(RrhhPrestamoCuotaCalculo::diferencia(500, 5, 100))->toBe(0.0);
});

it('explica el error de calculo al analista', function (): void {
    $mensaje = RrhhPrestamoCuotaCalculo::mensajeError(500, 5, 150);

    expect($mensaje)
        ->toContain('Error en el cálculo de las cuotas')
        ->toContain('US$ 750.00')
        ->toContain('US$ 500.00')
        ->toContain('excede el préstamo')
        ->toContain('porcentaje de descuento')
        ->toContain('monto de cada descuento');
});

it('expone validacion de cuotas en el formulario de prestamos', function (): void {
    $form = file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Administration/Resources/RrhhPrestamos/Schemas/RrhhPrestamoForm.php'
    );
    $migration = file_get_contents(
        dirname(__DIR__, 2).'/database/migrations/2026_07_23_095733_add_monto_cuota_to_rrhh_prestamos_table.php'
    );

    expect($form)
        ->toContain("TextInput::make('monto_cuota')")
        ->toContain("Placeholder::make('validacion_cuotas')")
        ->toContain('RrhhPrestamoCuotaCalculo::cuadraExacto')
        ->toContain('RrhhPrestamoCuotaCalculo::mensajeError')
        ->toContain('reglaCuotasCuadranExacto')
        ->toContain('sincronizarMontoCuotaDesdePorcentaje');

    expect($migration)
        ->toContain("hasColumn('rrhh_prestamos', 'monto_cuota')")
        ->toContain("->decimal('monto_cuota', 10, 2)->nullable()");
});
