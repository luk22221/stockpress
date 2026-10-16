<?php

namespace App\Services;

use Illuminate\Container\Attributes\Config;
use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;

class ThumbnailGenerator
{
    public function __construct(
        #[Config('stockpress.thumbnail_size')] private int $size,
    ) {}

    /**
     * Create a WebP thumbnail that fits in a size x size box.
     * Imagick is required: GD cannot read TIFF files.
     *
     * @return string Binary WebP data.
     */
    public function generate(string $path): string
    {
        // EXIF orientation is applied automatically while decoding.
        $manager = ImageManager::usingDriver(Driver::class, decodeAnimation: false);

        return $manager->decodePath($path)
            ->scaleDown($this->size, $this->size)
            ->encode(new WebpEncoder(quality: 80, strip: true))
            ->toString();
    }
}
