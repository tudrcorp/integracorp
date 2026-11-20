<?php

declare(strict_types=1);

use App\Support\Quotes\Design\QuoteDesignHtmlImporter;
use App\Support\Quotes\Design\QuoteDesignImageNormalizer;
use App\Support\Quotes\Design\QuoteDesignPage;
use App\Support\Quotes\Design\QuoteDesignTheme;

/**
 * Lógica pura del diseño de cotización: sin contenedor ni base de datos.
 */
function renacerDesignHtml(): string
{
    return <<<'HTML'
<!doctype html>
<html lang="es">
<head>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat">
<style>
  :root{
    --verde-renacer:#17612F;
    --ocre-dorado:#C89A2B;
    --musgo:#2F7D4E;
    --blanco-lino:#FBFAF7;
    --arena:#EDE7DA;
    --piedra:#D8D5CD;
    --grafito:#5A605A;
    --tinta:#171C18;
    --blanco:#FFFFFF;
    --font-corporativa:"Montserrat",Arial,Helvetica,sans-serif;
    --font-servicio:"IBM Plex Mono",ui-monospace,"Courier New",monospace;
  }
  body{font-family:var(--font-corporativa);color:var(--tinta)}
  .hoja{width:816px;background:var(--blanco-lino);display:flex}
  .label{color:var(--grafito)}
  .mono{font-family:var(--font-servicio)}
  .filete{height:1px;background:var(--ocre-dorado)}
  h1{color:var(--verde-renacer)}
  .datos{display:grid;background:var(--arena)}
  thead tr{background:var(--verde-renacer);color:var(--blanco)}
  .beneficios tbody{background:var(--blanco)}
  .beneficios tbody tr{border-bottom:1px solid var(--piedra)}
  @page{size:letter;margin:0}
  @media print{ html,body{background:none} .hoja{margin:0} }
</style>
</head>
<body>
<main class="hoja">
  <header class="cabecera"><img src="logo-ecofuneral-full.png" alt="Renacer"></header>
  <div class="titulo"><span class="ante">Propuesta económica</span><h1>Plan Funerario 5K</h1></div>
  <p class="vigencia">Propuesta válida por 30 días a partir de la fecha de emisión.</p>
  <table class="beneficios"><thead><tr><th>Beneficios del plan</th><th>Plan</th></tr></thead>
  <tbody><tr><td>Tanatopraxia</td><td><svg stroke="#2F7D4E"><path d="M4 12"/></svg></td></tr></tbody></table>
  <ul class="notas"><li>Cobertura nacional.</li><li>Pago trimestral, semestral y anual.</li></ul>
  <footer class="pie"><span class="mono">Asistencia Funeraria · Venezuela</span><div class="endoso"><span>Un producto de</span><span>Tu Dr. Group</span></div></footer>
</main>
</body>
</html>
HTML;
}

describe('QuoteDesignTheme', function (): void {
    it('normaliza colores hexadecimales, cortos y rgb', function (mixed $input, ?string $expected): void {
        expect(QuoteDesignTheme::normalizeColor($input))->toBe($expected);
    })->with([
        'largo' => ['#17612f', '#17612F'],
        'sin numeral' => ['c89a2b', '#C89A2B'],
        'corto' => ['#fff', '#FFFFFF'],
        'rgb' => ['rgb(23, 97, 47)', '#17612F'],
        'rgb fuera de rango' => ['rgb(300, 0, 0)', '#FF0000'],
        'nombre de color' => ['red', null],
        'inyección' => ['#fff;} body{display:none', null],
        'no es texto' => [123, null],
    ]);

    it('completa con los valores por defecto lo que falta o es inválido', function (): void {
        $clean = QuoteDesignTheme::sanitizeThemeData([
            'primary' => 'no-es-color',
            'accent' => '#c89a2b',
            'sans_family' => 'Comic Sans',
            'benefits_heading' => '   ',
            'validity_note' => '',
        ]);

        expect($clean['primary'])->toBe(QuoteDesignTheme::COLORS['primary']['default'])
            ->and($clean['accent'])->toBe('#C89A2B')
            ->and($clean['sans_family'])->toBe('Helvetica')
            ->and($clean['benefits_heading'])->toBe('Beneficios del plan')
            ->and($clean['validity_note'])->not->toBe('');
    });

    it('limpia etiquetas HTML de los textos y acota su largo', function (): void {
        $clean = QuoteDesignTheme::sanitizeThemeData([
            'footer_left' => '<script>alert(1)</script>Asistencia   Funeraria',
            'overline' => str_repeat('a', 300),
        ]);

        expect($clean['footer_left'])->toBe('alert(1)Asistencia Funeraria')
            ->and(mb_strlen($clean['overline']))->toBe(60);
    });

    it('acepta condiciones como texto plano o como filas del repetidor, y descarta las vacías', function (): void {
        $clean = QuoteDesignTheme::sanitizeThemeData([
            'conditions' => ['Primera', ['text' => 'Segunda'], '   ', ['text' => '']],
        ]);

        expect($clean['conditions'])->toBe(['Primera', 'Segunda']);
    });

    it('no deja pasar más de quince condiciones', function (): void {
        $clean = QuoteDesignTheme::sanitizeThemeData(['conditions' => array_map(fn (int $n): string => "Condición {$n}", range(1, 30))]);

        expect($clean['conditions'])->toHaveCount(QuoteDesignTheme::MAX_CONDITIONS);
    });

    it('permite apagar la nota y dejar el pie vacío a propósito', function (): void {
        $clean = QuoteDesignTheme::sanitizeThemeData(['show_note' => '0', 'footer_left' => '']);

        expect($clean['show_note'])->toBeFalse()
            ->and($clean['footer_left'])->toBe('');
    });
});

describe('QuoteDesignHtmlImporter', function (): void {
    it('toma los diez colores del HTML de diseño', function (): void {
        $theme = QuoteDesignHtmlImporter::analyze(renacerDesignHtml())['theme'];

        expect($theme)->toMatchArray([
            'primary' => '#17612F',
            'header_text' => '#FFFFFF',
            'accent' => '#C89A2B',
            'check' => '#2F7D4E',
            'page_background' => '#FBFAF7',
            'band_background' => '#EDE7DA',
            'surface' => '#FFFFFF',
            'border' => '#D8D5CD',
            'text' => '#171C18',
            'muted' => '#5A605A',
        ]);
    });

    it('toma los textos fijos y el pie de página', function (): void {
        $theme = QuoteDesignHtmlImporter::analyze(renacerDesignHtml())['theme'];

        expect($theme['overline'])->toBe('Propuesta económica')
            ->and($theme['benefits_heading'])->toBe('Beneficios del plan')
            ->and($theme['validity_note'])->toBe('Propuesta válida por 30 días a partir de la fecha de emisión.')
            ->and($theme['footer_left'])->toBe('Asistencia Funeraria · Venezuela')
            ->and($theme['footer_right_prefix'])->toBe('Un producto de')
            ->and($theme['footer_right_brand'])->toBe('Tu Dr. Group')
            ->and($theme['conditions'])->toBe([['text' => 'Cobertura nacional.'], ['text' => 'Pago trimestral, semestral y anual.']]);
    });

    it('avisa lo que el analista debe completar: logo aparte, fuentes y tamaño carta', function (): void {
        $result = QuoteDesignHtmlImporter::analyze(renacerDesignHtml());
        $warnings = implode("\n", $result['warnings']);

        expect($result['logo'])->toBeNull()
            ->and($warnings)->toContain('logo-ecofuneral-full.png')
            ->and($warnings)->toContain('Montserrat')
            ->and($warnings)->toContain('IBM Plex Mono')
            ->and($warnings)->toContain('tamaño carta');
    });

    it('importa un logo embebido en el HTML', function (): void {
        $image = imagecreatetruecolor(20, 10);
        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();
        imagedestroy($image);

        $html = '<html><body><header><img src="data:image/png;base64,'.base64_encode($png).'"></header></body></html>';
        $result = QuoteDesignHtmlImporter::analyze($html);

        expect($result['logo'])->not->toBeNull()
            ->and($result['logo']['extension'])->toBe('png')
            ->and($result['logo']['contents'])->toBe($png);
    });

    it('no se rompe con un archivo vacío o sin estilos', function (): void {
        $empty = QuoteDesignHtmlImporter::analyze('');
        $plain = QuoteDesignHtmlImporter::analyze('<p>hola</p>');

        expect($empty['theme'])->toBe([])
            ->and($plain['theme'])->not->toHaveKey('primary')
            ->and($plain['warnings'])->not->toBeEmpty();
    });

    it('no ejecuta ni conserva el HTML: solo devuelve valores saneados', function (): void {
        $html = '<style>h1{color:#123456}</style><h1>x</h1><footer><span>"><script>alert(1)</script>Pie</span></footer>';
        $theme = QuoteDesignHtmlImporter::analyze($html)['theme'];

        expect($theme['primary'])->toBe('#123456')
            ->and(QuoteDesignTheme::sanitizeThemeData($theme)['footer_left'])->not->toContain('<script>');
    });
});

describe('QuoteDesignImageNormalizer', function (): void {
    it('recorta centrado para llenar la proporción A4 sin deformar', function (): void {
        [$x, $y, $width, $height] = QuoteDesignImageNormalizer::coverCrop(816, 1056);

        expect($y)->toBe(0)
            ->and($height)->toBe(1056)
            ->and($x)->toBeGreaterThan(0)
            ->and(abs($width / $height - QuoteDesignImageNormalizer::WIDTH / QuoteDesignImageNormalizer::HEIGHT))->toBeLessThan(0.01);
    });

    it('recorta arriba y abajo una imagen más alta que A4', function (): void {
        [$x, $y, $width, $height] = QuoteDesignImageNormalizer::coverCrop(1000, 2000);

        expect($x)->toBe(0)->and($width)->toBe(1000)->and($y)->toBeGreaterThan(0);
    });

    it('convierte un PNG carta en un JPEG A4 exacto', function (): void {
        $image = imagecreatetruecolor(816, 1056);
        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();
        imagedestroy($image);

        $info = getimagesizefromstring(QuoteDesignImageNormalizer::toA4Jpeg($png));

        expect($info[0])->toBe(QuoteDesignImageNormalizer::WIDTH)
            ->and($info[1])->toBe(QuoteDesignImageNormalizer::HEIGHT)
            ->and($info[2])->toBe(IMAGETYPE_JPEG);
    });

    it('rechaza lo que no es una imagen PNG o JPG', function (): void {
        QuoteDesignImageNormalizer::toA4Jpeg('<?php echo 1; ?>');
    })->throws(RuntimeException::class, 'PNG o JPG');

    it('rechaza una imagen demasiado pequeña para una hoja', function (): void {
        $image = imagecreatetruecolor(100, 100);
        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();
        imagedestroy($image);

        QuoteDesignImageNormalizer::toA4Jpeg($png);
    })->throws(RuntimeException::class, 'demasiado pequeña');

    it('reconoce una página ya normalizada para no procesarla dos veces', function (): void {
        expect(QuoteDesignImageNormalizer::isNormalized('quote-designs/pages/abc-a4.jpg'))->toBeTrue()
            ->and(QuoteDesignImageNormalizer::isNormalized('quote-designs/uploads/abc.png'))->toBeFalse();
    });
});

describe('QuoteDesignPage::summary', function (): void {
    it('con una sola cobertura arma las tarjetas anual, semestral y trimestral del grupo', function (): void {
        $summary = QuoteDesignPage::summary(
            [4989.0],
            [['age_range' => '18 a 64', 'total_persons' => 2, 'amounts' => ['4989' => 150.0]]],
            ['4989' => 150.0],
            [],
        );

        expect($summary)->not->toBeNull()
            ->and($summary['annual'])->toBe(150.0)
            ->and($summary['biannual'])->toBe(75.0)
            ->and($summary['quarterly'])->toBe(37.5)
            ->and($summary['rows'][0])->toBe(['age_range' => '18 a 64', 'persons' => 2, 'annual' => 150.0]);
    });

    it('en un paquete suma las tarifas de cada rango', function (): void {
        $summary = QuoteDesignPage::summary([], [], [], [
            ['age_range' => '0 a 17', 'total_persons' => 1, 'annual' => 100.0, 'biannual' => 52.0, 'quarterly' => 27.0],
            ['age_range' => '18 a 64', 'total_persons' => 2, 'annual' => 300.0, 'biannual' => 156.0, 'quarterly' => 81.0],
        ]);

        expect($summary['annual'])->toBe(400.0)
            ->and($summary['biannual'])->toBe(208.0)
            ->and($summary['quarterly'])->toBe(108.0)
            ->and($summary['coverage_label'])->toBeNull();
    });

    it('con varias coberturas no inventa un total único: se dibuja la matriz', function (): void {
        expect(QuoteDesignPage::summary([1000.0, 5000.0], [], [], []))->toBeNull();
    });

    it('formatea montos sin decimales y con punto de miles', function (): void {
        expect(QuoteDesignPage::money(1249.6))->toBe('1.250')
            ->and(QuoteDesignPage::money(37.5))->toBe('38');
    });
});
