<?php

declare(strict_types=1);

use Illuminate\Mail\Mailable;

uses(Tests\TestCase::class);

/**
 * Illuminate\Mail\Mailable declara sin tipo `$from`, `$to`, `$subject`, `$view`
 * y compañía. Un Mailable propio que promueva una propiedad con alguno de esos
 * nombres y un tipo explícito revienta con un FatalError al cargar la clase
 * ("Type of ... must not be defined"), no al enviarla: el job muere completo.
 */

/**
 * @return list<class-string<Mailable>>
 */
function appMailableClasses(): array
{
    $classes = [];

    foreach ((array) glob(base_path('app/Mail/*.php')) as $file) {
        $class = 'App\\Mail\\'.basename((string) $file, '.php');

        if (class_exists($class) && is_subclass_of($class, Mailable::class)) {
            $classes[] = $class;
        }
    }

    sort($classes);

    return $classes;
}

it('encuentra los mailables de la aplicación', function (): void {
    expect(appMailableClasses())
        ->not->toBeEmpty()
        ->toContain(App\Mail\WhiteCompanySalesReportMail::class);
});

it('ningun mailable redeclara una propiedad reservada de Mailable', function (): void {
    $reserved = array_keys((new ReflectionClass(Mailable::class))->getDefaultProperties());

    foreach (appMailableClasses() as $class) {
        $own = array_filter(
            (new ReflectionClass($class))->getProperties(ReflectionProperty::IS_PUBLIC),
            static fn (ReflectionProperty $property): bool => $property->getDeclaringClass()->getName() === $class,
        );

        foreach ($own as $property) {
            expect($reserved)->not->toContain(
                $property->getName(),
                $class.'::$'.$property->getName().' choca con una propiedad de Mailable. Renómbrala.'
            );
        }
    }
});

it('el mailable del reporte de aliadas se instancia y arma asunto y vista', function (): void {
    $mail = new App\Mail\WhiteCompanySalesReportMail(
        companyName: 'Empresa Aliada C.A.',
        fromDate: '01/08/2026',
        toDate: '20/08/2026',
        totals: ['sale_price' => 100.0, 'neta_tdg' => 60.0, 'neta_partner' => 40.0, 'affiliates' => 3],
        rowsCount: 2,
        securityKey: 'ABC123',
        attachmentPath: '/ruta/que/no/existe.pdf',
    );

    expect($mail->envelope()->subject)->toBe(
        'Estado de cuenta y conciliación de afiliaciones — 01/08/2026 al 20/08/2026'
    );

    $content = $mail->content();

    expect($content->view)->toBe('mails.whiteCompanySalesReport')
        ->and($content->with['from'])->toBe('01/08/2026')
        ->and($content->with['to'])->toBe('20/08/2026')
        ->and($content->with['companyName'])->toBe('Empresa Aliada C.A.');
});

it('no adjunta nada cuando el pdf no existe en disco', function (): void {
    $mail = new App\Mail\WhiteCompanySalesReportMail(
        companyName: 'Empresa Aliada C.A.',
        fromDate: '01/08/2026',
        toDate: '20/08/2026',
        totals: ['sale_price' => 0.0, 'neta_tdg' => 0.0, 'neta_partner' => 0.0, 'affiliates' => 0],
        rowsCount: 0,
        securityKey: 'ABC123',
        attachmentPath: '/ruta/que/no/existe.pdf',
    );

    expect($mail->attachments())->toBe([]);
});
