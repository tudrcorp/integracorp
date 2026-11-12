<?php

declare(strict_types=1);

use App\Support\Filesystem\WindowsSafeFilesystem;

it('reemplaza un archivo existente sin dejar el temporal', function (): void {
    $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'integracorp-windows-fs-'.uniqid();
    mkdir($dir);

    $path = $dir.DIRECTORY_SEPARATOR.'compiled.php';
    file_put_contents($path, '<?php return 1;');

    $files = new WindowsSafeFilesystem;
    $files->replace($path, '<?php return 2;');

    expect(file_get_contents($path))->toBe('<?php return 2;')
        ->and(glob($dir.DIRECTORY_SEPARATOR.'*.tmp') ?: [])->toBe([]);

    @unlink($path);
    @rmdir($dir);
});

it('queda registrado en AppServiceProvider solo para Windows', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Providers/AppServiceProvider.php');

    expect($source)->toContain('WindowsSafeFilesystem')
        ->and($source)->toContain("PHP_OS_FAMILY === 'Windows'")
        ->and(file_exists(dirname(__DIR__, 2).'/app/Support/Filesystem/WindowsSafeFilesystem.php'))->toBeTrue();
});
