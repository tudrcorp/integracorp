<?php

declare(strict_types=1);

namespace App\Support\AffiliationCorporates\Population;

use App\Models\AffiliateCorporate;
use App\Models\AfilliationCorporatePlan;
use App\Models\CorporatePopulationUpdateItem as Item;
use Illuminate\Support\Collection;

/**
 * Explica en lenguaje de analista qué hizo el sistema con una fila de la carga:
 * qué buscó, con qué regla comparó, qué coincidió y qué no, por qué la clasificó
 * así y qué tiene que hacer el analista.
 *
 * Se arma al mostrar (no se guarda), con la ficha tal como está hoy: después de
 * una corrección la explicación ya refleja el dato nuevo.
 */
final class PopulationItemExplainer
{
    /**
     * @param  Collection<int, AfilliationCorporatePlan>  $planRows  filas de plan de la afiliación (con ageRange)
     * @return array{headline: string, tone: string, searched: string|null, rule: string|null, compared_with: string|null, labels: array{0: string, 1: string}, fields: list<array<string, mixed>>, conclusion: string, next: string|null, history: list<string>}
     */
    public static function explain(Item $item, ?AffiliateCorporate $affiliate, ?AffiliateCorporate $original, Collection $planRows, int $baseTotal): array
    {
        $person = is_array($item->file_data) ? $item->file_data : null;
        $details = (array) $item->details;
        $fields = [];
        $comparedWith = null;
        $labels = ['Archivo', 'Base'];

        if ($person !== null && $affiliate instanceof AffiliateCorporate) {
            $fields = array_values(PopulationFieldComparison::forPerson($person, $affiliate));
            $comparedWith = 'Fila '.$item->file_row.' del archivo frente a la ficha #'.$affiliate->getKey().' de la base.';
        } elseif ($person === null && $affiliate instanceof AffiliateCorporate && $original instanceof AffiliateCorporate) {
            $fields = array_values(PopulationFieldComparison::compare(PopulationFieldComparison::baseValues($original), PopulationFieldComparison::baseValues($affiliate)));
            $comparedWith = 'La ficha #'.$original->getKey().', que sí está en el archivo, frente a esta ficha #'.$affiliate->getKey().', que sobra.';
            $labels = ['Se conserva (#'.$original->getKey().')', 'Copia (#'.$affiliate->getKey().')'];
        }

        $accepted = (array) ($details['accepted'] ?? []);
        foreach ($fields as $i => $row) {
            $fields[$i]['accepted'] = $row['counted'] && array_key_exists($row['field'], $accepted);
            $fields[$i]['accepted_note'] = $accepted[$row['field']]['note'] ?? null;
        }

        $explanation = match ($item->action) {
            Item::ACTION_MATCH => self::match($item, $fields),
            Item::ACTION_ADD => self::add($item, $details),
            Item::ACTION_REVIEW => $affiliate instanceof AffiliateCorporate ? self::reviewPaired($item, $affiliate, $fields) : self::reviewUnpaired($item, $person, $planRows),
            Item::ACTION_REACTIVATE => [
                'headline' => 'Vuelve a la afiliación: en la base estaba '.($affiliate?->status ?? 'inactivo').'.',
                'tone' => 'success',
                'conclusion' => 'Es la misma persona ('.self::viaText($item->match_via).'), está en el archivo y en la base figura '.($affiliate?->status ?? 'inactiva').'. Al aplicar vuelve a ACTIVO y su tarifa vuelve a sumar en los totales de la afiliación.',
                'next' => 'Deje «Incluir» encendido si la empresa confirma que debe volver.',
            ],
            Item::ACTION_FIX_DOCUMENT => [
                'headline' => 'Misma persona con otra cédula: se guardará la del archivo.',
                'tone' => 'info',
                'conclusion' => 'Se confirmó que es la misma persona ('.self::viaText($item->match_via).'), pero la cédula de la ficha es distinta. La del archivo («'.($details['fix_document_to'] ?? '').'») es una cédula de adulto que nadie más usa en la afiliación, así que al aplicar se guarda en la ficha y en su paciente de telemedicina.',
                'next' => 'Si la cédula correcta es la de la base, apague «Incluir».',
            ],
            Item::ACTION_DEACTIVATE => self::deactivate($item, $person, $details),
            Item::ACTION_DUPLICATE_BASE => self::duplicateBase($item, $affiliate, $original, $details),
            Item::ACTION_DUPLICATE_FILE => [
                'headline' => 'Fila repetida en el archivo: se toma una sola vez.',
                'tone' => 'warning',
                'conclusion' => 'La fila '.$item->file_row.' trae a la misma persona que la fila '.($details['first_row'] ?? '¿?').': misma cédula y mismo nombre. Si se tomaran las dos, la persona quedaría dos veces y pagaría dos tarifas. Por eso la segunda se ignora; la primera ya se cruzó con la base.',
                'next' => isset($details['reviewed'])
                    ? 'Revisado por '.$details['reviewed']['by_name'].': «'.$details['reviewed']['note'].'».'
                    : 'Confírmelo con la empresa (puede ir en dos grupos familiares por error) y márquelo «Revisado».',
            ],
            Item::ACTION_BLOCKED_OPEN_CASE => [
                'headline' => 'No se da de baja: tiene actividad clínica abierta.',
                'tone' => 'danger',
                'conclusion' => 'Esta ficha no está en el archivo, así que correspondería darla de baja, pero tiene actividad abierta: '.implode(' · ', (array) ($details['open_activity'] ?? [])).'. Darla de baja dejaría ese caso sin cobertura a mitad de la atención.',
                'next' => 'Operaciones debe cerrar o reasignar el caso. Después pulse «Volver a analizar».',
            ],
            Item::ACTION_INVALID => [
                'headline' => (string) $item->reason,
                'tone' => 'gray',
                'conclusion' => 'La fila no trae datos suficientes para identificar a la persona, o no pide ningún cambio. No se hace nada con ella.',
                'next' => 'Si la persona debe estar, pida a la empresa la fila completa.',
            ],
            default => ['headline' => (string) $item->reason, 'tone' => 'gray', 'conclusion' => (string) $item->reason, 'next' => null],
        };

        return [
            'headline' => $explanation['headline'],
            'tone' => $explanation['tone'],
            'searched' => $person !== null ? self::searched($item, $person, $baseTotal) : null,
            'rule' => $explanation['rule'] ?? ($person !== null && $affiliate instanceof AffiliateCorporate ? self::ruleText($item->match_via) : null),
            'compared_with' => $comparedWith,
            'labels' => $labels,
            'fields' => $fields,
            'conclusion' => $explanation['conclusion'],
            'next' => $explanation['next'],
            'history' => self::history($details),
        ];
    }

    /**
     * Texto corto de la regla con la que se emparejó.
     */
    public static function viaText(?string $via): string
    {
        return match ($via) {
            'cedula' => 'misma cédula, confirmada con el nombre o la fecha de nacimiento',
            'nombre_y_fecha' => 'misma fecha de nacimiento y nombre casi igual',
            'solo_nombre' => 'solo por el nombre',
            'prefijo_y_fecha' => 'misma fecha y cédula parecida',
            'analista' => 'lo confirmó el analista',
            default => 'sin emparejar',
        };
    }

    /**
     * @param  array<string, mixed>  $person
     */
    private static function searched(Item $item, array $person, int $baseTotal): string
    {
        $birth = $person['birth_date'] ?? null;

        return 'Fila '.$item->file_row.' del archivo: '.($person['full_name'] ?? '—')
            .', cédula '.(($person['document'] ?? '') !== '' ? '«'.$person['document'].'»'.(($person['minor_document'] ?? false) ? ' (cédula de menor)' : '') : 'vacía')
            .', nacimiento '.($birth ? date('d/m/Y', (int) strtotime((string) $birth)) : 'sin fecha')
            .'. Se buscó entre los '.$baseTotal.' afiliados de la afiliación, vigentes e inactivos.';
    }

    private static function ruleText(?string $via): string
    {
        return match ($via) {
            'cedula' => 'Se emparejó por cédula. La cédula se compara solo por sus dígitos: sin letra, sin puntos ni guiones y sin ceros a la izquierda («V-010.444.385» es igual a «10444385»). Para aceptar el emparejamiento, además el nombre debe parecerse al menos un 60 % o la fecha de nacimiento debe ser la misma: una cédula sola no basta, porque hay menores registrados con la del representante.',
            'nombre_y_fecha' => 'No había una cédula que identificara (los menores traen la cédula del representante con un sufijo y en la base suelen tener un número de relleno). Se emparejó por fecha de nacimiento idéntica y un nombre parecido en un 80 % o más, sin otro candidato cercano. El nombre se compara sin tildes, sin importar el orden de las palabras ni si están cruzados nombres y apellidos.',
            'solo_nombre' => 'Ni la cédula ni la fecha de nacimiento coincidieron con nadie. Solo apareció una ficha con exactamente las mismas palabras en el nombre (sin importar el orden). Un nombre solo no prueba que sea la misma persona: puede ser un homónimo. Por eso el sistema no decide.',
            'prefijo_y_fecha' => 'No coincidió la cédula completa, pero hay una ficha con la misma fecha de nacimiento, una cédula que empieza con los mismos 7 dígitos (típico de un error de tipeo o de un sufijo de menor) y al menos un apellido en común. Es muy probable que sea la misma persona, pero con datos mal escritos en algún lado.',
            'analista' => 'El analista confirmó que la fila y la ficha son la misma persona.',
            default => 'Se emparejó por la cédula o por la fecha de nacimiento y el nombre.',
        };
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     * @return array{headline: string, tone: string, conclusion: string, next: string|null}
     */
    private static function match(Item $item, array $fields): array
    {
        $pending = array_values(array_filter($fields, static fn (array $row): bool => $row['counted'] && ! $row['accepted']));
        $accepted = array_values(array_filter($fields, static fn (array $row): bool => $row['accepted']));

        if ($pending === []) {
            return [
                'headline' => $accepted === [] ? 'Coincide: es la misma persona y sus datos cuadran.' : 'Coincide. Diferencias revisadas y aceptadas por el analista.',
                'tone' => 'success',
                'conclusion' => 'Es la misma persona ('.self::viaText($item->match_via).') y está vigente en los dos lados. No se cambia nada.'
                    .($accepted !== [] ? ' Quedan diferencias aceptadas en: '.mb_strtolower(implode(', ', array_column($accepted, 'label'))).'.' : ''),
                'next' => null,
            ];
        }

        $labels = mb_strtolower(implode(', ', array_column($pending, 'label')));

        return [
            'headline' => 'Coincide, pero la ficha difiere del archivo en: '.$labels.'.',
            'tone' => 'warning',
            'conclusion' => 'Es la misma persona ('.self::viaText($item->match_via).') y está vigente, así que la carga no la toca. Pero la ficha de la base no dice lo mismo que el archivo en: '.$labels.'. No impide aplicar la carga, pero esos datos salen en el carnet, en telemedicina y en los reportes a la empresa.',
            'next' => 'Pulse «Corregir datos» para elegir el valor correcto de cada campo, o selecciónela con otras y use «Sincronizar con el archivo».',
        ];
    }

    /**
     * @param  array<string, mixed>  $details
     * @return array{headline: string, tone: string, rule: string, conclusion: string, next: string|null}
     */
    private static function add(Item $item, array $details): array
    {
        $plan = isset($details['fee'])
            ? 'Por su edad'.(isset($details['age']) ? ' ('.$details['age'].' años)' : ' (sin fecha: plan único de la empresa)').' le corresponde la tarifa anual de US$ '.number_format((float) $details['fee'], 2, ',', '.').'.'
            : '';

        return [
            'headline' => 'Persona nueva: se agrega a la afiliación.',
            'tone' => 'success',
            'rule' => 'Se buscó por cédula, por fecha de nacimiento con nombre parecido y por nombre exacto: no apareció en ninguna ficha de la afiliación. También se revisó que no esté vigente en otra afiliación (individual, corporativa o Nuevos Negocios).'
                .(isset($details['not_same_as']) ? ' El analista indicó que no es «'.$details['not_same_as']['name'].'» (#'.$details['not_same_as']['affiliate_id'].').' : ''),
            'conclusion' => 'Al aplicar se crea su ficha como ACTIVO. '.$plan.' La tarifa suma en los totales de la afiliación.',
            'next' => 'Deje «Incluir» encendido si la empresa confirma el alta.',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     * @return array{headline: string, tone: string, conclusion: string, next: string|null}
     */
    private static function reviewPaired(Item $item, AffiliateCorporate $affiliate, array $fields): array
    {
        $ok = array_values(array_filter($fields, static fn (array $row): bool => in_array($row['status'], [PopulationFieldComparison::SAME, PopulationFieldComparison::FORMAT], true)));
        $bad = array_values(array_filter($fields, static fn (array $row): bool => $row['counted']));
        $name = trim($affiliate->first_name.' '.$affiliate->last_name);

        $why = match ($item->match_via) {
            'cedula' => 'tienen la misma cédula pero el nombre es muy distinto: puede ser otra persona usando esa cédula (un hijo registrado con la del representante) o un nombre mal cargado',
            'solo_nombre' => 'solo coincide el nombre; la cédula y la fecha no lo confirman',
            'prefijo_y_fecha' => 'la fecha es la misma y la cédula muy parecida, pero no idéntica',
            default => 'los datos de identidad no alcanzan para confirmarlo',
        };

        return [
            'headline' => 'Posiblemente es «'.$name.'» (#'.$affiliate->getKey().'), pero hay que confirmarlo.',
            'tone' => 'warning',
            'conclusion' => 'El sistema no lo decide solo porque '.$why.'.'
                .($ok !== [] ? ' Coinciden: '.mb_strtolower(implode(', ', array_column($ok, 'label'))).'.' : '')
                .($bad !== [] ? ' No coinciden: '.mb_strtolower(implode(', ', array_column($bad, 'label'))).'.' : '')
                .' Mientras siga en «Revisar» no se agrega ni se da de baja a nadie.',
            'next' => 'Si es la misma persona, pulse «Corregir datos»: confirme y elija los valores correctos; el sistema vuelve a comparar en el acto. Si no lo es, pulse «No es la misma persona»: la fila pasa a alta nueva y la ficha #'.$affiliate->getKey().' se propone de baja porque no está en el archivo.',
        ];
    }

    /**
     * @param  array<string, mixed>|null  $person
     * @param  Collection<int, AfilliationCorporatePlan>  $planRows
     * @return array{headline: string, tone: string, rule: string|null, conclusion: string, next: string|null}
     */
    private static function reviewUnpaired(Item $item, ?array $person, Collection $planRows): array
    {
        $reason = (string) $item->reason;

        if (str_contains($reason, 'no se le puede asignar plan')) {
            $age = PopulationPerson::ageAt($person['birth_date'] ?? null);
            $ranges = $planRows->map(static fn (AfilliationCorporatePlan $row): string => 'de '.$row->ageRange?->age_init.' a '.$row->ageRange?->age_end.' años (US$ '.number_format((float) $row->fee, 2, ',', '.').')')->implode('; ');

            return [
                'headline' => 'Persona nueva sin plan para su edad'.($age !== null ? ' ('.$age.' años)' : '').': no se puede agregar todavía.',
                'tone' => 'warning',
                'rule' => 'Se buscó por cédula, por fecha de nacimiento con nombre parecido y por nombre exacto: no está en la afiliación, así que sería un alta. Para agregarla el sistema necesita el plan y la tarifa que le tocan por edad.',
                'conclusion' => ($age !== null ? 'Tiene '.$age.' años (nació el '.date('d/m/Y', (int) strtotime((string) $person['birth_date'])).'). ' : 'No trae fecha de nacimiento. ')
                    .($planRows->isEmpty() ? 'La afiliación no tiene planes contratados.' : 'Los planes contratados cubren: '.$ranges.'. Ninguno incluye esa edad')
                    .', por eso no se puede calcular su tarifa y no se agrega a ciegas.',
                'next' => 'Agregue a la afiliación un plan para esa edad (o confírmelo con la empresa) y luego pulse «Volver a evaluar» en esta fila.',
            ];
        }

        return [
            'headline' => 'Persona nueva que ya está vigente en otra parte: no se agrega.',
            'tone' => 'warning',
            'rule' => 'No está en esta afiliación, pero el candado de personas duplicadas la encontró en otro registro vigente. Misma persona es: misma cédula (comparada sin letra ni ceros) y mismo nombre, o mismo nombre y misma fecha de nacimiento.',
            'conclusion' => $reason,
            'next' => 'Revise el caso en Negocios → «Personas duplicadas». Si se resuelve allá, pulse «Volver a evaluar».',
        ];
    }

    /**
     * @param  array<string, mixed>|null  $person
     * @param  array<string, mixed>  $details
     * @return array{headline: string, tone: string, conclusion: string, next: string|null}
     */
    private static function deactivate(Item $item, ?array $person, array $details): array
    {
        if ($person !== null) {
            return [
                'headline' => 'El archivo lo marca de baja.',
                'tone' => 'danger',
                'conclusion' => 'Es la misma persona ('.self::viaText($item->match_via).') y el archivo la trae con la marca de baja. Al aplicar queda INACTIVO (no se borra) y su tarifa deja de sumar.',
                'next' => 'Deje «Incluir» encendido si la empresa confirma la baja.',
            ];
        }

        $freed = isset($details['freed_from_item']) ? ' El analista indicó que la fila del archivo que se le parecía no es esta persona.' : '';
        $notDuplicate = isset($details['duplicate_resolution']['not_duplicate']) ? ' El analista indicó que no es un duplicado.' : '';

        return [
            'headline' => 'No está en el archivo: se propone darla de baja.',
            'tone' => 'danger',
            'rule' => 'Se buscó esta ficha en todo el archivo por cédula, por fecha y nombre, y por nombre exacto, y no apareció.'.$freed.$notDuplicate,
            'conclusion' => 'Está vigente en la base, pero el archivo es la población COMPLETA de la empresa y no la incluye. Al aplicar queda INACTIVO (no se borra, se puede revertir) y su tarifa deja de sumar.',
            'next' => 'Si la empresa confirma que sigue, apague «Incluir» y pídale que corrija su archivo.',
        ];
    }

    /**
     * @param  array<string, mixed>  $details
     * @return array{headline: string, tone: string, rule: string, conclusion: string, next: string|null}
     */
    private static function duplicateBase(Item $item, ?AffiliateCorporate $copy, ?AffiliateCorporate $original, array $details): array
    {
        $originalName = $original instanceof AffiliateCorporate ? trim($original->first_name.' '.$original->last_name) : 'otra ficha';
        $originalId = $details['original_id'] ?? '¿?';
        $sameDoc = $copy instanceof AffiliateCorporate && $original instanceof AffiliateCorporate
            && PopulationPerson::documentKey((string) $copy->nro_identificacion) === PopulationPerson::documentKey((string) $original->nro_identificacion);
        $resolution = (array) ($details['duplicate_resolution'] ?? []);

        return [
            'headline' => 'Copia de «'.$originalName.'» (#'.$originalId.'): sobra una ficha.',
            'tone' => 'warning',
            'rule' => 'Esta ficha no apareció en el archivo. Al buscar por qué, el sistema la comparó con las fichas que sí se emparejaron y encontró a «'.$originalName.'» (#'.$originalId.'): '
                .($sameDoc ? 'tienen la misma cédula' : 'sus cédulas comparten los primeros 7 dígitos')
                .' y al menos dos palabras del nombre en común.',
            'conclusion' => 'Dos fichas para una misma persona duplican su tarifa, su cupo clínico y su carnet. '
                .($sameDoc ? 'Como la cédula es la misma, la copia se propone de baja.' : 'Como la cédula es distinta, el sistema no decide cuál vale: «Incluir» queda apagado hasta que usted lo resuelva.'),
            'next' => $resolution !== []
                ? (isset($resolution['not_duplicate']) ? null : 'Resuelto por '.$resolution['by_name'].': se conserva #'.$resolution['kept_id'].' y esta copia se da de baja al aplicar'.(($resolution['copied'] ?? []) !== [] ? '; se pasaron a la ficha que queda: '.mb_strtolower(implode(', ', array_map(PopulationFieldComparison::label(...), $resolution['copied']))) : '').'.')
                : 'Pulse «Resolver duplicado»: confirme que se conserva #'.$originalId.' y, si quiere, pase a esa ficha los datos que le faltan (por ejemplo, la fecha de nacimiento). Si no es la misma persona, pulse «No es un duplicado».',
        ];
    }

    /**
     * @param  array<string, mixed>  $details
     * @return list<string>
     */
    private static function history(array $details): array
    {
        $out = [];
        $at = static fn (?string $iso): string => $iso ? date('d/m/Y H:i', (int) strtotime($iso)) : '';

        foreach ((array) ($details['corrections'] ?? []) as $correction) {
            $changes = [];
            foreach ((array) ($correction['after'] ?? []) as $column => $value) {
                $changes[] = $column.': «'.($correction['before'][$column] ?? '—').'» → «'.$value.'»';
            }

            $out[] = $at($correction['at'] ?? null).' · '.($correction['by_name'] ?? '').' corrigió '.implode(', ', $changes).'. Motivo: '.($correction['note'] ?? '')
                .(($correction['undone_at'] ?? null) ? ' — Deshecho el '.$at($correction['undone_at']).' por '.($correction['undone_by_name'] ?? '').': '.($correction['undo_note'] ?? '') : '');
        }

        if (isset($details['confirmed_same_person']['by_name'])) {
            $out[] = $at($details['confirmed_same_person']['at'] ?? null).' · '.$details['confirmed_same_person']['by_name'].' confirmó que es la misma persona.';
        }

        foreach ((array) ($details['accepted'] ?? []) as $field => $accepted) {
            $out[] = $at($accepted['at'] ?? null).' · '.($accepted['by_name'] ?? '').' aceptó la diferencia en '.mb_strtolower(PopulationFieldComparison::label((string) $field)).': '.($accepted['note'] ?? '');
        }

        if (isset($details['not_same_as']['by_name'])) {
            $out[] = $at($details['not_same_as']['at'] ?? null).' · '.$details['not_same_as']['by_name'].' indicó que no es «'.$details['not_same_as']['name'].'»: '.$details['not_same_as']['note'];
        }

        if (isset($details['reviewed']['by_name'])) {
            $out[] = $at($details['reviewed']['at'] ?? null).' · '.$details['reviewed']['by_name'].' lo marcó revisado: '.$details['reviewed']['note'];
        }

        if (isset($details['duplicate_resolution']['by_name'])) {
            $out[] = $at($details['duplicate_resolution']['at'] ?? null).' · '.$details['duplicate_resolution']['by_name'].' resolvió el duplicado: '.$details['duplicate_resolution']['note'];
        }

        return $out;
    }
}
