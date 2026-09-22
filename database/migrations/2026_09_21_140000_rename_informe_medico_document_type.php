<?php

declare(strict_types=1);

use App\Models\OperationDocumentList;
use App\Models\TelemedicineConsultationPatient;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * El informe médico de la consulta inicial deja de llamarse «largo»: es el
 * único informe que emite la consulta, así que pasa a ser «INFORME MEDICO».
 *
 * Se renombra el tipo del catálogo y la etiqueta ya congelada en
 * `uploaded_documents` de las consultas históricas, que es de donde la lee el
 * hub de documentos del caso. El informe corto (tipo 14) no se toca: sus
 * documentos anteriores conservan su nombre para poder distinguirlos.
 */
return new class extends Migration
{
    private const DOCUMENT_TYPE_ID = 9;

    private const OLD_NAME = 'INFORME MEDICO CONSULTA INICIAL (LARGO)';

    private const NEW_NAME = 'INFORME MEDICO';

    public function up(): void
    {
        $this->rename(self::OLD_NAME, self::NEW_NAME);
    }

    public function down(): void
    {
        $this->rename(self::NEW_NAME, self::OLD_NAME);
    }

    private function rename(string $from, string $to): void
    {
        if (Schema::hasTable('operation_document_lists')) {
            OperationDocumentList::query()
                ->whereKey(self::DOCUMENT_TYPE_ID)
                ->where('name', $from)
                ->update(['name' => $to]);
        }

        if (! Schema::hasColumn('telemedicine_consultation_patients', 'uploaded_documents')) {
            return;
        }

        TelemedicineConsultationPatient::query()
            ->where('uploaded_documents', 'like', '%'.$from.'%')
            ->chunkById(200, function ($consultations) use ($from, $to): void {
                foreach ($consultations as $consultation) {
                    $documents = is_array($consultation->uploaded_documents)
                        ? $consultation->uploaded_documents
                        : [];

                    $changed = false;

                    foreach ($documents as $index => $document) {
                        if (! is_array($document) || ! is_array($document['document_types'] ?? null)) {
                            continue;
                        }

                        foreach ($document['document_types'] as $typeIndex => $typeName) {
                            if ((string) $typeName !== $from) {
                                continue;
                            }

                            $documents[$index]['document_types'][$typeIndex] = $to;
                            $changed = true;
                        }
                    }

                    if (! $changed) {
                        continue;
                    }

                    $consultation->timestamps = false;
                    $consultation->uploaded_documents = $documents;
                    $consultation->save();
                }
            });
    }
};
