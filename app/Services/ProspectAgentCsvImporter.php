<?php

namespace App\Services;

use App\Models\ProspectAgent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;

class ProspectAgentCsvImporter
{
    private const CSV_FIELD_COUNT = 11;

    /**
     * @return list<string>
     */
    public static function expectedAttributeKeys(): array
    {
        return [
            'name',
            'phone_1',
            'email',
            'country_id',
            'state_id',
            'status',
            'initial_observ',
            'type',
            'instagram',
            'reference_by',
            'classification',
        ];
    }

    /**
     * Trunca tablas relacionadas y prospect_agents, luego importa el CSV.
     * Solo se usan las primeras 11 columnas de cada fila (coinciden con el encabezado del archivo).
     *
     * @return int Número de filas insertadas
     */
    public function importFromPath(string $absolutePath): int
    {
        if (! is_readable($absolutePath)) {
            throw new InvalidArgumentException("No se puede leer el archivo: {$absolutePath}");
        }

        $handle = fopen($absolutePath, 'rb');
        if ($handle === false) {
            throw new RuntimeException("No se pudo abrir el archivo: {$absolutePath}");
        }

        try {
            $headerRow = fgetcsv($handle, 0, ',', '"', '');
            if ($headerRow === false || $headerRow === []) {
                throw new InvalidArgumentException('El CSV está vacío o no tiene encabezado.');
            }

            $attributeKeys = $this->normalizeHeaderKeys(array_slice($headerRow, 0, self::CSV_FIELD_COUNT));
            $this->assertHeaderMatchesExpected($attributeKeys);

            $now = CarbonImmutable::now();

            Schema::disableForeignKeyConstraints();
            try {
                DB::table('prospect_agent_observations')->truncate();
                DB::table('prospect_agent_tasks')->truncate();
                DB::table('prospect_agents')->truncate();
            } finally {
                Schema::enableForeignKeyConstraints();
            }

            $batch = [];
            $inserted = 0;
            $batchSize = 75;

            while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                $cells = array_slice($row, 0, self::CSV_FIELD_COUNT);
                if ($this->rowIsEmpty($cells)) {
                    continue;
                }

                while (count($cells) < self::CSV_FIELD_COUNT) {
                    $cells[] = '';
                }

                /** @var array<string, string> $assoc */
                $assoc = array_combine($attributeKeys, $cells);
                if ($assoc === false) {
                    continue;
                }

                if (trim((string) ($assoc['name'] ?? '')) === '') {
                    continue;
                }

                $batch[] = $this->mapToDatabaseRow($assoc, $now);
                $inserted++;

                if (count($batch) >= $batchSize) {
                    ProspectAgent::query()->insert($batch);
                    $batch = [];
                }
            }

            if ($batch !== []) {
                ProspectAgent::query()->insert($batch);
            }

            return $inserted;
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  list<string|null>  $headerCells
     * @return list<string>
     */
    private function normalizeHeaderKeys(array $headerCells): array
    {
        $keys = [];
        foreach ($headerCells as $cell) {
            $raw = strtolower(trim((string) $cell));
            if ($raw === 'nanme') {
                $keys[] = 'name';
            } else {
                $keys[] = $raw;
            }
        }

        return $keys;
    }

    /**
     * @param  list<string>  $attributeKeys
     */
    private function assertHeaderMatchesExpected(array $attributeKeys): void
    {
        $expected = self::expectedAttributeKeys();
        if ($attributeKeys !== $expected) {
            throw new InvalidArgumentException(
                'Las columnas del CSV no coinciden con el formato esperado. Encabezado recibido: '
                .implode(', ', $attributeKeys)
                .' | Esperado: '
                .implode(', ', $expected)
            );
        }
    }

    /**
     * @param  list<string|null>  $cells
     */
    private function rowIsEmpty(array $cells): bool
    {
        foreach ($cells as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, string>  $assoc
     * @return array<string, mixed>
     */
    private function mapToDatabaseRow(array $assoc, CarbonImmutable $now): array
    {
        $trim = static fn (?string $v): string => trim((string) $v);

        return [
            'name' => $trim($assoc['name'] ?? ''),
            'phone_1' => $trim($assoc['phone_1'] ?? ''),
            'phone_2' => '',
            'email' => $trim($assoc['email'] ?? ''),
            'country_id' => $trim($assoc['country_id'] ?? ''),
            'state_id' => $trim($assoc['state_id'] ?? ''),
            'city_id' => '',
            'status' => $trim($assoc['status'] ?? ''),
            'initial_observ' => $trim($assoc['initial_observ'] ?? ''),
            'type' => $trim($assoc['type'] ?? ''),
            'instagram' => $trim($assoc['instagram'] ?? ''),
            'reference_by' => $trim($assoc['reference_by'] ?? ''),
            'classification' => $trim($assoc['classification'] ?? ''),
            'created_by' => '',
            'updated_by' => '',
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
}
