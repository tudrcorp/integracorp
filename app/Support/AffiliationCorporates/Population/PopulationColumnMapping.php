<?php

declare(strict_types=1);

namespace App\Support\AffiliationCorporates\Population;

use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Qué columna del archivo es cada dato. Cada corporativo manda su propio
 * formato: se sugiere solo por el nombre del encabezado y el analista confirma.
 * La plantilla confirmada se guarda por afiliación.
 */
final class PopulationColumnMapping
{
    /**
     * @return array<string, array{label: string, required: bool, help: string, aliases: list<string>}>
     */
    public static function fields(): array
    {
        return [
            'document' => [
                'label' => 'Cédula / documento',
                'required' => true,
                'help' => 'La cédula del afiliado. En menores puede venir la del representante con sufijo (010444385-16).',
                'aliases' => ['CODIGO_DOCUMENTO', 'CEDULA', 'CEDULA_IDENTIDAD', 'CI', 'C_I', 'DOCUMENTO', 'NRO_DOCUMENTO', 'NUMERO_DOCUMENTO', 'NRO_IDENTIFICACION', 'IDENTIFICACION', 'CEDULA_ASEGURADO'],
            ],
            'first_name' => [
                'label' => 'Nombres',
                'required' => true,
                'help' => 'Si el archivo trae el nombre completo en una sola columna, elíjala aquí y deje vacíos los apellidos.',
                'aliases' => ['NOMBRE_RIESGO', 'NOMBRES', 'NOMBRE', 'PRIMER_NOMBRE', 'NOMBRE_COMPLETO', 'NOMBRE_Y_APELLIDO', 'ASEGURADO'],
            ],
            'last_name' => [
                'label' => 'Apellido (o apellidos)',
                'required' => false,
                'help' => 'Primer apellido, o ambos si vienen juntos.',
                'aliases' => ['1ER_APELLIDO_RIESGO', 'APELLIDOS', 'APELLIDO', 'PRIMER_APELLIDO', 'APELLIDO_PATERNO'],
            ],
            'second_last_name' => [
                'label' => 'Segundo apellido',
                'required' => false,
                'help' => 'Solo si el archivo lo separa.',
                'aliases' => ['2DO_APELLIDO_RIESGO', 'SEGUNDO_APELLIDO', 'APELLIDO_MATERNO'],
            ],
            'birth_date' => [
                'label' => 'Fecha de nacimiento',
                'required' => false,
                'help' => 'Muy recomendada: sin ella no se identifica bien a los menores ni se asigna el plan por edad.',
                'aliases' => ['NACIMIENTO', 'FECHA_NACIMIENTO', 'FEC_NACIMIENTO', 'FECHA_NAC', 'F_NACIMIENTO', 'FECHA_DE_NACIMIENTO', 'FEC_NAC'],
            ],
            'document_type' => [
                'label' => 'Tipo de documento',
                'required' => false,
                'help' => 'CIV / CIM, V / E, etc. Ayuda a distinguir cédulas de menores.',
                'aliases' => ['TIP_DOCUMENTO', 'TIPO_DOCUMENTO', 'TIPO_DOC', 'TIPO'],
            ],
            'sex' => [
                'label' => 'Sexo',
                'required' => false,
                'help' => 'M / F, MASCULINO / FEMENINO.',
                'aliases' => ['SEXO', 'GENERO', 'GENERO_ASEGURADO'],
            ],
            'relationship' => [
                'label' => 'Parentesco',
                'required' => false,
                'help' => 'TI, CO, HI, MA, PA o el texto (Titular, Cónyuge, Hijo…).',
                'aliases' => ['PARENTESCO', 'RELACION', 'PARENTEZCO'],
            ],
            'excluded_flag' => [
                'label' => 'Marca de baja',
                'required' => false,
                'help' => 'Si el archivo marca quién está dado de baja (S/SI/X/BAJA), esas filas cuentan como fuera de la población.',
                'aliases' => ['MCA_BAJA_RIESGO', 'BAJA', 'MARCA_BAJA', 'EXCLUIDO'],
            ],
        ];
    }

    /**
     * Sugerencia por nombre de encabezado.
     *
     * @param  list<string>  $headers
     * @return array<string, string|null>
     */
    public static function guess(array $headers): array
    {
        $byKey = [];
        foreach ($headers as $header) {
            $byKey[self::key($header)] ??= $header;
        }

        $mapping = [];
        $used = [];

        foreach (self::fields() as $field => $definition) {
            $mapping[$field] = null;

            foreach ($definition['aliases'] as $alias) {
                $header = $byKey[self::key($alias)] ?? null;
                if ($header !== null && ! isset($used[$header])) {
                    $mapping[$field] = $header;
                    $used[$header] = true;

                    break;
                }
            }
        }

        return $mapping;
    }

    /**
     * Plantilla guardada si todas sus columnas siguen en el archivo; si no, la sugerencia.
     *
     * @param  list<string>  $headers
     * @param  array<string, string|null>|null  $template
     * @return array<string, string|null>
     */
    public static function suggest(array $headers, ?array $template): array
    {
        if (is_array($template) && $template !== []) {
            $valid = true;
            foreach ($template as $header) {
                if ($header !== null && $header !== '' && ! in_array($header, $headers, true)) {
                    $valid = false;

                    break;
                }
            }

            if ($valid) {
                return array_merge(array_fill_keys(array_keys(self::fields()), null), array_intersect_key($template, self::fields()));
            }
        }

        return self::guess($headers);
    }

    /**
     * @param  array<string, mixed>  $mapping
     * @param  list<string>  $headers
     * @return array<string, string|null>
     */
    public static function validate(array $mapping, array $headers): array
    {
        $clean = [];
        $used = [];

        foreach (self::fields() as $field => $definition) {
            $header = isset($mapping[$field]) && $mapping[$field] !== '' ? (string) $mapping[$field] : null;

            if ($header === null) {
                if ($definition['required']) {
                    throw new InvalidArgumentException('Indique qué columna es «'.$definition['label'].'».');
                }

                $clean[$field] = null;

                continue;
            }

            if (! in_array($header, $headers, true)) {
                throw new InvalidArgumentException('La columna «'.$header.'» no está en el archivo.');
            }

            if (isset($used[$header])) {
                throw new InvalidArgumentException('La columna «'.$header.'» está elegida para «'.$used[$header].'» y para «'.$definition['label'].'».');
            }

            $used[$header] = $definition['label'];
            $clean[$field] = $header;
        }

        return $clean;
    }

    private static function key(string $header): string
    {
        return trim((string) preg_replace('/[^A-Z0-9]+/', '_', Str::upper(Str::ascii($header))), '_');
    }
}
