<?php

namespace App\Actions;

use App\Models\Image;
use App\Services\ImageMetadataExtractor;
use App\Services\ThumbnailGenerator;
use App\Services\WeatherService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class StoreImage
{
    public function __construct(
        private ThumbnailGenerator $thumbnails,
        private ImageMetadataExtractor $metadata,
        private WeatherService $weather,
    ) {}

    public function handle(UploadedFile $file, string $uploaderName, string $uploaderEmail): Image
    {
        $sourcePath = $file->getRealPath();
        [$width, $height] = getimagesize($sourcePath);

        // Everything that can fail without side effects runs before files are written.
        $thumbnail = $this->thumbnails->generate($sourcePath);
        $metadata = $this->metadata->extract($sourcePath);
        $weather = $this->weather->currentTemperature();

        $uuid = (string) Str::uuid();
        $extension = $file->extension();
        $path = "images/{$uuid}.{$extension}";
        $thumbnailPath = "thumbnails/{$uuid}.webp";

        try {
            Storage::disk(Image::ORIGINALS_DISK)->putFileAs('images', $file, "{$uuid}.{$extension}");
            Storage::disk(Image::THUMBNAILS_DISK)->put($thumbnailPath, $thumbnail);

            return Image::create([
                'original_name' => $file->getClientOriginalName(),
                'path' => $path,
                'thumbnail_path' => $thumbnailPath,
                'mime' => $file->getMimeType(),
                'extension' => $extension,
                'size' => $file->getSize(),
                'width' => $width,
                'height' => $height,
                'uploader_name' => $uploaderName,
                'uploader_email' => $uploaderEmail,
                'exif' => $metadata['exif'],
                'iptc' => $metadata['iptc'],
                'temperature' => $weather['temperature'] ?? null,
                // Stored in the application timezone, like the other timestamps.
                'temperature_time' => ($weather['time'] ?? null)?->setTimezone(config('app.timezone')),
            ]);
        } catch (Throwable $e) {
            Storage::disk(Image::ORIGINALS_DISK)->delete($path);
            Storage::disk(Image::THUMBNAILS_DISK)->delete($thumbnailPath);

            throw $e;
        }
    }
}
