<?php

declare(strict_types=1);

use App\Support\HelpdeskPlainText;

it('convierte html de descripcion a texto plano sin etiquetas', function (): void {
    expect(HelpdeskPlainText::fromHtml('<p>sdfsffdssfv svdfvs</p>'))->toBe('sdfsffdssfv svdfvs')
        ->and(HelpdeskPlainText::fromHtml(null))->toBe('');
});
