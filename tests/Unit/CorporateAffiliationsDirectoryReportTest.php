<?php

declare(strict_types=1);

use App\Enums\AdministrationReportType;
use App\Enums\OperationReportFormat;
use App\Filament\Administration\Pages\CentroDeReportes;
use App\Models\AffiliationCorporate;
use App\Models\City;
use App\Models\Country;
use App\Models\State;
use App\Models\User;
use App\Support\Administration\Reports\CollectionReportBuilder;
use App\Support\Administration\Reports\CollectionReportData;
use App\Support\Administration\Reports\CollectionReportFilters;
use App\Support\Administration\Reports\CollectionReportWriter;
use App\Support\Administration\Reports\CorporateAffiliationsDirectoryReport;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * Esquema mínimo en sqlite `:memory:`. El reporte lee afiliaciones reales y el
 * test no debe escribir en la base de desarrollo, ni con la config cacheada.
 */
beforeEach(function (): void {
    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite.database' => ':memory:',
        'session.driver' => 'array',
    ]);

    DB::purge('sqlite');
    DB::reconnect('sqlite');

    $schema = Schema::connection('sqlite');

    $schema->create('countries', function (Blueprint $table): void {
        $table->id();
        $table->string('name')->nullable();
    });

    $schema->create('states', function (Blueprint $table): void {
        $table->id();
        $table->string('definition')->nullable();
    });

    $schema->create('cities', function (Blueprint $table): void {
        $table->id();
        $table->string('definition')->nullable();
    });

    $schema->create('affiliation_corporates', function (Blueprint $table): void {
        $table->id();
        $table->string('code')->nullable();
        $table->string('name_corporate')->nullable();
        $table->string('document_type_rif', 1)->nullable();
        $table->string('rif')->nullable();
        $table->string('address')->nullable();
        $table->unsignedBigInteger('city_id')->nullable();
        $table->unsignedBigInteger('state_id')->nullable();
        $table->unsignedBigInteger('country_id')->nullable();
        $table->timestamp('deleted_at')->nullable();
        $table->timestamps();
    });

    $schema->create('suppliers', function (Blueprint $table): void {
        $table->id();
        $table->string('name')->nullable();
        $table->boolean('is_outsourcing_medical_department')->default(false);
        $table->string('integracorp_alias')->nullable();
        $table->timestamp('deleted_at')->nullable();
        $table->timestamps();
    });
});

it('arma razón social, RIF y dirección completa, y omite lo que está vacío', function (): void {
    $empresa = new AffiliationCorporate([
        'code' => 'TDEC-COR-QA-DIR',
        'name_corporate' => 'CLINICA QA',
        'document_type_rif' => 'j',
        'rif' => '123456789',
        'address' => 'AV. PRINCIPAL',
    ]);
    $empresa->setRelation('city', new City(['definition' => 'CARACAS']));
    $empresa->setRelation('state', new State(['definition' => 'MIRANDA']));
    $empresa->setRelation('country', new Country(['name' => 'Venezuela']));

    $sinDatos = new AffiliationCorporate([
        'code' => '   ',
        'name_corporate' => '',
        'document_type_rif' => '',
        'rif' => '',
        'address' => '   ',
    ]);
    $sinDatos->setRelation('city', null);
    $sinDatos->setRelation('state', null);
    $sinDatos->setRelation('country', null);

    $yaConLetra = new AffiliationCorporate([
        'document_type_rif' => 'J',
        'rif' => 'J-987654321',
    ]);

    expect(CorporateAffiliationsDirectoryReport::row($empresa))->toBe([
        'TDEC-COR-QA-DIR',
        'CLINICA QA',
        'J-123456789',
        'AV. PRINCIPAL, CARACAS, MIRANDA, Venezuela',
    ])
        ->and(CorporateAffiliationsDirectoryReport::row($sinDatos))->toBe([null, null, null, null])
        ->and(CorporateAffiliationsDirectoryReport::rif($yaConLetra))->toBe('J-987654321')
        ->and(CorporateAffiliationsDirectoryReport::headings())->toBe(['CODIGO', 'RAZON SOCIAL', 'RIF', 'DIRECCION']);
});

it('lista las afiliaciones corporativas vivas e ignora el periodo y el estatus de cobro', function (): void {
    $codigo = 'QA-DIR-'.bin2hex(random_bytes(4));

    DB::table('affiliation_corporates')->insert([
        'code' => $codigo,
        'name_corporate' => 'QA DIRECTORIO VIVA',
        'document_type_rif' => 'J',
        'rif' => '401112223',
        'address' => 'CALLE QA',
    ]);

    DB::table('affiliation_corporates')->insert([
        'code' => $codigo.'-BORRADA',
        'name_corporate' => 'QA DIRECTORIO BORRADA',
        'rif' => '409998887',
        'address' => 'CALLE BORRADA',
        'deleted_at' => now(),
    ]);

    $filtros = CollectionReportFilters::fromArray([
        'type' => AdministrationReportType::CorporateDirectory->value,
        'period' => CollectionReportFilters::PERIOD_THIS_MONTH,
        'collection_status' => 'por_pagar',
        'scope' => CollectionReportFilters::SCOPE_AGENCY,
        'agency_code' => 'QA-NO-APLICA',
    ]);

    $reporte = CollectionReportBuilder::build($filtros, 1);

    expect($filtros->from)->toBeNull()
        ->and($filtros->to)->toBeNull()
        ->and($filtros->collectionStatus)->toBeNull()
        ->and($filtros->describe())->toBe(['Todas las afiliaciones corporativas: razón social, RIF y dirección.'])
        ->and(CorporateAffiliationsDirectoryReport::count())->toBe(1)
        ->and(CorporateAffiliationsDirectoryReport::query()->where('code', $codigo)->exists())->toBeTrue()
        ->and(CorporateAffiliationsDirectoryReport::query()->where('code', $codigo.'-BORRADA')->exists())->toBeFalse()
        ->and($reporte->title)->toBe('Afiliaciones corporativas')
        ->and($reporte->headings)->toBe(CorporateAffiliationsDirectoryReport::headings())
        ->and($reporte->columnWidths)->toBe([22, 42, 18, 64]);
});

it('el CSV del directorio trae el encabezado y la fila, sin el total', function (): void {
    $empresa = new AffiliationCorporate([
        'code' => 'TDEC-COR-QA-CSV',
        'name_corporate' => 'EMPRESA CSV',
        'document_type_rif' => 'G',
        'rif' => '200112233',
        'address' => 'SECTOR QA',
    ]);
    $empresa->setRelation('city', null);
    $empresa->setRelation('state', null);
    $empresa->setRelation('country', null);

    $datos = new CollectionReportData(
        title: AdministrationReportType::CorporateDirectory->label(),
        headings: CorporateAffiliationsDirectoryReport::headings(),
        lines: [['kind' => CollectionReportData::LINE_ROW, 'values' => CorporateAffiliationsDirectoryReport::row($empresa)]],
        numericColumns: [],
        criteria: ['Todas las afiliaciones corporativas: razón social, RIF y dirección.'],
        pdfColumns: [0, 1, 2, 3],
        moneyColumns: [],
        columnWidths: CorporateAffiliationsDirectoryReport::columnWidths(),
    );

    $ruta = tempnam(sys_get_temp_dir(), 'qa-dir-');
    expect($ruta)->not->toBeFalse();

    try {
        CollectionReportWriter::write($datos, OperationReportFormat::Csv, $ruta);
        $contenido = (string) file_get_contents($ruta);

        expect($contenido)->toStartWith("\xEF\xBB\xBF")
            ->and($contenido)->toContain('RAZON SOCIAL')
            ->and($contenido)->toContain('EMPRESA CSV')
            ->and($contenido)->toContain('G-200112233')
            ->and($contenido)->toContain('SECTOR QA')
            ->and($contenido)->not->toContain('TOTAL GENERAL');
    } finally {
        @unlink($ruta);
    }
});

it('el Centro de reportes ofrece el directorio sin periodo ni estatus de cobro', function (): void {
    $usuario = User::factory()->make([
        'id' => 999_999_441,
        'name' => 'QA DIRECTORIO',
        'email' => 'qa.directorio.corporativo@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['ADMINISTRACION'],
    ]);

    $this->actingAs($usuario);
    Filament::setCurrentPanel('administration');

    expect(AdministrationReportType::CorporateDirectory->label())->toBe('Afiliaciones corporativas')
        ->and(AdministrationReportType::CorporateDirectory->fileSlug())->toBe('afiliaciones-corporativas')
        ->and(CentroDeReportes::availableTypes())->toContain(AdministrationReportType::CorporateDirectory);

    Livewire::withQueryParams(['reporte' => AdministrationReportType::CorporateDirectory->value])
        ->test(CentroDeReportes::class)
        ->assertSee('Afiliaciones corporativas')
        ->assertSee('Una fila por afiliación corporativa con su razón social, RIF y dirección (calle, ciudad, estado y país).')
        ->assertSee('2. Formato')
        ->assertSee('No hay afiliaciones corporativas registradas.')
        ->assertDontSee('Estatus de cobro')
        ->assertDontSee('Por fecha de vencimiento de la cuota pendiente.');
});
