<?php

declare(strict_types=1);

namespace App\Support\Affiliations\PersonIdentity;

use App\Enums\PersonIdentityFindingKind;
use RuntimeException;

/**
 * Se intentó registrar (o reactivar, o corregir hacia) una persona que ya está vigente en otra
 * afiliación o en Nuevos Negocios, o con el nombre cargado dos veces.
 *
 * `getMessage()` trae el detalle completo para el analista interno. `publicMessage()` es el texto
 * para los formularios públicos: no revela datos de la otra afiliación.
 */
final class DuplicatePersonIdentityException extends RuntimeException
{
    public const SHOWN_CONFLICTS = 5;

    /**
     * @param  list<array{candidate: PersonIdentityRecord, existing: PersonIdentityRecord, kind: PersonIdentityFindingKind}>  $conflicts
     */
    public function __construct(
        public readonly array $conflicts,
        public readonly ?PersonIdentityRecord $repeatedName = null,
    ) {
        parent::__construct($this->buildMessage());
    }

    public static function repeatedName(PersonIdentityRecord $record): self
    {
        return new self([], $record);
    }

    public function title(): string
    {
        return $this->conflicts === []
            ? 'El nombre está cargado dos veces'
            : 'Esta persona ya está registrada';
    }

    public function publicMessage(): string
    {
        if ($this->conflicts === []) {
            return 'El nombre parece estar escrito dos veces. Escriba los nombres en su campo y los apellidos en el suyo, una sola vez.';
        }

        return 'Esta persona ya tiene una cobertura vigente registrada con Tu Dr. En Casa, por lo que no se puede registrar de nuevo. '
            .'Si cree que es un error, comuníquese con su asesor o con nuestro equipo de atención.';
    }

    private function buildMessage(): string
    {
        if ($this->conflicts === []) {
            $record = $this->repeatedName;

            return 'No se guardó: el nombre «'.trim((string) $record?->name).'» está cargado dos veces (los nombres y los apellidos traen lo mismo, '
                .'o el nombre completo está escrito dos veces). Escriba los nombres y los apellidos una sola vez, cada uno en su campo.';
        }

        $byCandidate = [];

        foreach ($this->conflicts as $conflict) {
            $byCandidate[$conflict['candidate']->reference().'|'.$conflict['candidate']->name][] = $conflict;
        }

        $parts = [];
        $shown = 0;

        foreach ($byCandidate as $conflicts) {
            if ($shown >= self::SHOWN_CONFLICTS) {
                break;
            }

            $candidate = $conflicts[0]['candidate'];
            $lines = [];

            foreach ($conflicts as $conflict) {
                $existing = $conflict['existing'];
                $lines[] = $existing->whereDescription().', registrado como «'.$existing->name.'»'
                    .($existing->document !== '' ? ', cédula '.$existing->document : ', sin cédula')
                    .($existing->birthDate !== '' ? ', nacimiento '.$existing->birthDate : '')
                    .'. Coincide: '.PersonIdentityMatcher::reason($conflict['kind']).'.';
            }

            $parts[] = '«'.$candidate->name.'»'.($candidate->document !== '' ? ' (cédula '.$candidate->document.')' : '')
                .' ya está registrado y vigente: '.implode(' ', $lines);
            $shown++;
        }

        $remaining = count($byCandidate) - $shown;

        return 'No se guardó nada. '.implode(' · ', $parts)
            .($remaining > 0 ? ' · Y '.$remaining.' persona(s) más en la misma situación.' : '')
            .' Una persona no puede estar vigente dos veces (ni en dos afiliaciones, ni en una afiliación y Nuevos Negocios). '
            .'Si el registro existente está mal, corríjalo o dé de baja el duplicado desde Negocios → Afiliaciones → Personas duplicadas.';
    }
}
