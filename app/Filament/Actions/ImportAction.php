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
