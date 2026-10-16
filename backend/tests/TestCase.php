<?php

namespace Tests;

use App\Models\Image;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Imagick;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(Image::ORIGINALS_DISK);
        Storage::fake(Image::THUMBNAILS_DISK);

        // Tests must never reach the real Open-Meteo API.
        Http::preventStrayRequests();
    }

    /**
     * Real image file generated with Imagick (covers TIFF, which GD cannot write).
     */
    protected function makeImage(string $format = 'jpg', int $width = 600, int $height = 600, string $pattern = 'gradient:'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'img').'.'.$format;

        $imagick = new Imagick;
        $imagick->newPseudoImage($width, $height, $pattern);
        $imagick->setImageFormat($format);
        $imagick->writeImage($path);
        $imagick->clear();

        return new UploadedFile($path, "photo.{$format}", null, null, true);
    }

    protected function fixture(string $name): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'fix');
        copy(base_path("tests/fixtures/{$name}"), $path);

        return new UploadedFile($path, $name, null, null, true);
    }

    /**
     * Open-Meteo response for today (Europe/Warsaw) where each hour H has temperature H + 0.5.
     */
    protected function fakeWeather(): void
    {
        $day = CarbonImmutable::now('Europe/Warsaw')->format('Y-m-d');
        $hours = range(0, 23);

        Http::fake([
            'api.open-meteo.com/*' => Http::response([
                'timezone' => 'Europe/Warsaw',
                'hourly' => [
                    'time' => array_map(fn (int $h) => sprintf('%sT%02d:00', $day, $h), $hours),
                    'temperature_2m' => array_map(fn (int $h) => $h + 0.5, $hours),
                ],
            ]),
        ]);
    }

    protected function upload(array $overrides = []): TestResponse
    {
        return $this->postJson('/api/images', [
            'file' => $this->makeImage(),
            'name' => 'Anna',
            'email' => 'anna@example.com',
            ...$overrides,
        ]);
    }
}
