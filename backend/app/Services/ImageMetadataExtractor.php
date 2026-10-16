<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Imagick;
use Throwable;

class ImageMetadataExtractor
{
    /**
     * Read raw EXIF and IPTC data. Formats without such metadata yield null.
     * Never throws: a metadata problem must not block the upload.
     *
     * @return array{exif: ?array, iptc: ?array}
     */
    public function extract(string $path): array
    {
        return [
            'exif' => $this->safely(fn () => $this->readExif($path), 'EXIF', $path),
            'iptc' => $this->safely(fn () => $this->readIptc($path), 'IPTC', $path),
        ];
    }

    private function readExif(string $path): ?array
    {
        // Native reader supports JPEG and TIFF; it emits warnings for other formats.
        $exif = @exif_read_data($path, null, true);

        if (is_array($exif)) {
            // The FILE section describes the temporary upload, not the image.
            unset($exif['FILE']);
        }

        if (! $exif) {
            // PNG and WebP can carry EXIF too. Imagick parses it only on a full read
            // (pingImage() skips the profile), so this runs just as a fallback.
            $imagick = new Imagick($path);
            $exif = $imagick->getImageProperties('exif:*') ?: null;
            $imagick->clear();
        }

        return $this->clean($exif);
    }

    private function readIptc(string $path): ?array
    {
        getimagesize($path, $info);

        if (empty($info['APP13'])) {
            return null;
        }

        return $this->clean(iptcparse($info['APP13']) ?: null);
    }

    /**
     * Drop binary and non-UTF-8 values (MakerNote, embedded thumbnails, etc.)
     * so the result can always be stored as JSON.
     */
    private function clean(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }

        $clean = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $value = $this->clean($value);
            } elseif (is_string($value)) {
                // EXIF strings are often padded with NUL bytes.
                $value = rtrim($value, "\0");

                if (! $this->isText($value)) {
                    continue;
                }
            }

            if ($value !== null && $this->isText((string) $key)) {
                $clean[$key] = $value;
            }
        }

        return $clean ?: null;
    }

    private function isText(string $value): bool
    {
        // Valid UTF-8 without control characters (tab and new lines are fine).
        return mb_check_encoding($value, 'UTF-8')
            && ! preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value);
    }

    private function safely(callable $read, string $type, string $path): ?array
    {
        try {
            return $read();
        } catch (Throwable $e) {
            Log::warning("Could not read {$type} metadata.", ['path' => $path, 'error' => $e->getMessage()]);

            return null;
        }
    }
}
