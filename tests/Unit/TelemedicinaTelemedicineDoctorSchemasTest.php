<?php

declare(strict_types=1);

use App\Filament\Telemedicina\Resources\TelemedicineDoctors\Schemas\TelemedicineDoctorForm;
use App\Filament\Telemedicina\Resources\TelemedicineDoctors\Schemas\TelemedicineDoctorInfolist;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;

it('configura el formulario de médico del panel telemedicina sin error', function (): void {
    $schema = Schema::make();
    $configured = TelemedicineDoctorForm::configure($schema);

    expect($configured)->toBeInstanceOf(Schema::class);

    $components = $configured->getComponents();
    expect($components)->toHaveCount(1)
        ->and($components[0])->toBeInstanceOf(Tabs::class);
});

it('configura el infolist de médico del panel telemedicina sin error', function (): void {
    $schema = Schema::make();
    $configured = TelemedicineDoctorInfolist::configure($schema);

    expect($configured)->toBeInstanceOf(Schema::class);

    $components = $configured->getComponents();
    expect($components)->toHaveCount(1)
        ->and($components[0])->toBeInstanceOf(Tabs::class);
});

it('aplica estilos de tabs en formulario e infolist de médico telemedicina', function (string $path): void {
    $contents = file_get_contents(dirname(__DIR__, 2).'/'.$path);

    expect($contents)
        ->toContain('private const TABS_CONTAINER')
        ->toContain('private const SECTION_CARD')
        ->toContain("'class' => self::TABS_CONTAINER")
        ->toContain("'class' => self::SECTION_CARD");
})->with([
    'form' => 'app/Filament/Telemedicina/Resources/TelemedicineDoctors/Schemas/TelemedicineDoctorForm.php',
    'infolist' => 'app/Filament/Telemedicina/Resources/TelemedicineDoctors/Schemas/TelemedicineDoctorInfolist.php',
]);

it('organiza formulario e infolist de médico en pestañas', function (): void {
    $form = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineDoctors/Schemas/TelemedicineDoctorForm.php');
    $infolist = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineDoctors/Schemas/TelemedicineDoctorInfolist.php');

    expect($form)
        ->toContain("Tabs::make('telemedicineDoctorFormTabs')")
        ->toContain("Tab::make('Información personal')")
        ->toContain("Tab::make('Información profesional')")
        ->toContain("Tab::make('Firma digital')")
        ->not->toContain('Wizard::make');

    expect($infolist)
        ->toContain("Tabs::make('telemedicineDoctorInfolistTabs')")
        ->toContain("Tab::make('Perfil del médico')")
        ->toContain("Tab::make('Credenciales profesionales')")
        ->toContain('persistTab()')
        ->toContain('IOS_DOCTOR_HERO_OUTER')
        ->toContain('SIGNATURE_INNER_CLASS')
        ->toContain("ImageEntry::make('signature')")
        ->toContain('object-contain')
        ->toContain('max-w-full');
});

it('limita el tamaño de la firma digital en la tabla de médicos', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineDoctors/Tables/TelemedicineDoctorsTable.php');

    expect($contents)
        ->toContain("ImageColumn::make('signature')")
        ->toContain('imageWidth(160)')
        ->toContain('object-contain')
        ->toContain('max-w-[10rem]');
});

it('aplica estilo ios y acciones de perfil en la tabla de médicos telemedicina', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineDoctors/Tables/TelemedicineDoctorsTable.php');

    expect($contents)
        ->toContain('telemedicine-case-table-ios')
        ->toContain('telemedicine-doctor-profile-table')
        ->toContain('Mi perfil médico')
        ->toContain('Ver perfil')
        ->toContain('FontWeight::SemiBold')
        ->not->toContain('DeleteBulkAction');
});

it('permite scroll horizontal en tablas ios de telemedicina', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 2).'/resources/css/filament/admin/theme.css');

    expect($contents)
        ->toContain('.telemedicine-case-table-ios .fi-ta-content-ctn')
        ->toContain('overflow-x-auto')
        ->toContain('telemedicine-doctor-profile-table');
});
