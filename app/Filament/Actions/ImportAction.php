<?php

namespace App\Filament\Actions;

use Filament\Actions\ImportAction as BaseImportAction;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Csv\CharsetConverter;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ImportAction extends BaseImportAction
{
    /**
     * Encodings candidatos, en el orden en que se prueban.
     *
     * Filament trae `['UTF-8', 'SJIS-win', 'EUC-KR', 'ISO-8859-1', ...]` y
     * devuelve el primero que valide. Un CSV Latin-1 con eñes o acentos valida
     * como SJIS-win —en Shift-JIS `\xF1` es cabecera de secuencia doble—, así
     * que se convertía desde japonés y la letra se perdía junto con la
     * siguiente: «BRICEÑO SILVA» entraba a la base como «Brice Silva».
     *
     * Los archivos de este negocio salen de Excel en español: UTF-8 primero y,
     * si no, Latin. Windows-1252 antes que ISO-8859-1 porque es su superconjunto
     * y cubre las comillas tipográficas y el guion largo que mete Excel.
     *
     * @var list<string>
     */
    private const CSV_ENCODINGS = [
        'UTF-8',
        'Windows-1252',
        'ISO-8859-1',
    ];

    /**
     * Encoding de origen del CSV.
     *
     * Windows-1252 es de un byte y acepta casi cualquier secuencia, así que
     * hace de red final: es preferible interpretar mal una tilde que perder
     * caracteres por elegir un encoding multibyte asiático.
     */
    protected function detectCsvEncoding(mixed $resource): ?string
    {
        rewind($resource);

        $lineCount = 0;
        $contentSample = '';

        while ((! feof($resource)) && ($lineCount < 20)) {
            $line = fgets($resource);

            if ($line === false) {
                break;
            }

            $contentSample .= $line;
            $lineCount++;
        }

        foreach (self::CSV_ENCODINGS as $encoding) {
            if (mb_check_encoding($contentSample, $encoding)) {
                return $encoding;
            }
        }

        return 'Windows-1252';
    }

    /**
     * @return resource | false
     */
    public function getUploadedFileStream(TemporaryUploadedFile $file)
    {
        $fileDisk = invade($file)->disk; /** @phpstan-ignore-line */
        if (config("filesystems.disks.{$fileDisk}.driver") !== 's3') {
            $resource = $file->readStream();
        } else {
            /** @var AwsS3V3Adapter $s3Adapter */
            $s3Adapter = Storage::disk($fileDisk)->getAdapter();

            invade($s3Adapter)->client->registerStreamWrapper(); /** @phpstan-ignore-line */
            $fileS3Path = (string) str('s3://'.config("filesystems.disks.{$fileDisk}.bucket").'/'.$file->getRealPath())->replace('\\', '/');

            $resource = fopen($fileS3Path, mode: 'r', context: stream_context_create([
                's3' => [
                    'seekable' => true,
                ],
            ]));
        }

        $inputEncoding = $this->detectCsvEncoding($resource);
        $outputEncoding = 'UTF-8';

        // Required after detectCsvEncoding(): otherwise stream_filter_append fails on pre-buffered data.
        rewind($resource);

        if (
            filled($inputEncoding) &&
            (Str::lower($inputEncoding) !== Str::lower($outputEncoding))
        ) {
            CharsetConverter::register();

            stream_filter_append(
                $resource,
                CharsetConverter::getFiltername($inputEncoding, $outputEncoding),
                STREAM_FILTER_READ,
            );
        }

        return $resource;
    }
}
