<?php

declare(strict_types=1);

namespace App\Support\OrgStructure;

/**
 * El borrador cambió desde que el analista lo cargó (otra pestaña u otro usuario).
 * La pantalla recarga el borrador en lugar de pisar el cambio ajeno.
 */
final class OrgStructureConflictException extends OrgStructureException {}
