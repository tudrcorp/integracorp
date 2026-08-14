<?php

declare(strict_types=1);

namespace App\Enums;

enum ViveplusDocumentType: string
{
    case Certificado = 'certificado';
    case Carnet = 'carnet';
}
