<?php

/**
 * El worker de producción no escucha la cola «certificates»: el certificado de
 * afiliación individual debe salir por la cola por defecto o se queda esperando.
 */
it('despacha el certificado de afiliación individual por la cola por defecto', function (): void {
    $source = (string) file_get_contents(dirname(__DIR__, 2).'/app/Models/Affiliation.php');

    expect(substr_count($source, 'SendNotificacionAfiliacionIndividual::dispatch('))->toBe(2)
        ->and($source)->not->toContain("onQueue('certificates')");
});

it('no deja ningún trabajo del código apuntando a la cola certificates', function (): void {
    $root = dirname(__DIR__, 2);
    $offenders = [];

    foreach (['app', 'routes'] as $directory) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$directory, FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            if (preg_match('/onQueue\(\s*[\'"]certificates[\'"]\s*\)|\$queue\s*=\s*[\'"]certificates[\'"]/', (string) file_get_contents($file->getPathname())) === 1) {
                $offenders[] = str_replace($root.'/', '', $file->getPathname());
            }
        }
    }

    expect($offenders)->toBe([]);
});

it('el job no fija una cola propia en su definición', function (): void {
    $source = (string) file_get_contents(dirname(__DIR__, 2).'/app/Jobs/SendNotificacionAfiliacionIndividual.php');

    expect($source)->not->toMatch('/onQueue\(|\$queue\s*=/');
});
