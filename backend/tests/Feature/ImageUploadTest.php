<?php

namespace Tests\Feature;

use App\Models\Image;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Tests\TestCase;

class ImageUploadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeWeather();
    }

    public static function allowedFormats(): array
    {
        return [
            'jpg' => ['jpg', 'image/jpeg', 'jpg'],
            'png' => ['png', 'image/png', 'png'],
            'webp' => ['webp', 'image/webp', 'webp'],
            'tiff' => ['tiff', 'image/tiff', 'tif'],
            'bmp' => ['bmp', 'image/bmp', 'bmp'],
        ];
    }

    #[DataProvider('allowedFormats')]
    public function test_uploads_each_allowed_format(string $format, string $mime, string $extension): void
    {
        $response = $this->upload(['file' => $this->makeImage($format, 640, 520)]);

        $response->assertCreated()
            ->assertJsonPath('data.extension', $extension)
            ->assertJsonPath('data.width', 640)
            ->assertJsonPath('data.height', 520)
            ->assertJsonPath('data.uploader_name', 'Anna');

        $image = Image::sole();
        $this->assertSame($mime, $image->mime);
        $this->assertSame('photo.'.$format, $image->original_name);
        $this->assertSame('anna@example.com', $image->uploader_email);
        $this->assertMatchesRegularExpression('#^images/[0-9a-f-]{36}\.'.$extension.'$#', $image->path);
        Storage::disk(Image::ORIGINALS_DISK)->assertExists($image->path);
        Storage::disk(Image::THUMBNAILS_DISK)->assertExists($image->thumbnail_path);

        // Thumbnail is a WebP that fits in 300 x 300.
        [$width, $height, $type] = getimagesize(Storage::disk(Image::THUMBNAILS_DISK)->path($image->thumbnail_path));
        $this->assertSame([300, 244, IMAGETYPE_WEBP], [$width, $height, $type]);
    }

    #[TestWith([400, 400])]
    #[TestWith([499, 800])]
    #[TestWith([800, 499])]
    public function test_rejects_too_small_resolution(int $width, int $height): void
    {
        $this->upload(['file' => $this->makeImage('jpg', $width, $height)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file' => 'Obraz musi mieć co najmniej 500 × 500 px.']);

        $this->assertDatabaseEmpty('images');
    }

    public function test_rejects_file_larger_than_5_mb(): void
    {
        // Uncompressed 1400 x 1400 BMP with noise is about 5.9 MB.
        $file = $this->makeImage('bmp', 1400, 1400, 'plasma:');
        $this->assertGreaterThan(5 * 1024 * 1024, $file->getSize());

        $this->upload(['file' => $file])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file' => 'Plik może mieć maksymalnie 5 MB.']);
    }

    public function test_rejects_disallowed_type(): void
    {
        $this->upload(['file' => $this->makeImage('gif')])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file' => 'Dozwolone typy plików: JPG, PNG, WebP, TIFF, BMP.']);
    }

    public function test_rejects_non_image_renamed_to_jpg(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'txt');
        file_put_contents($path, str_repeat('not an image ', 100));

        $this->upload(['file' => new UploadedFile($path, 'fake.jpg', null, null, true)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    }

    public function test_requires_name_email_and_file(): void
    {
        $this->postJson('/api/images', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'file' => 'Wybierz plik do przesłania.',
                'name' => 'Podaj imię.',
                'email' => 'Podaj adres e-mail.',
            ]);
    }

    public function test_rejects_invalid_email(): void
    {
        $this->upload(['email' => 'not-an-email'])
            ->assertUnprocessable()
            ->assertOnlyJsonValidationErrors(['email' => 'Podaj poprawny adres e-mail.']);
    }

    public function test_stores_exif_and_iptc_without_binary_values(): void
    {
        $this->upload(['file' => $this->fixture('photo-with-exif.jpg')])->assertCreated();

        $image = Image::sole();
        $this->assertSame('StockPress', $image->exif['IFD0']['Make']);
        $this->assertSame('Test Camera X', $image->exif['IFD0']['Model']);
        $this->assertSame('2026:10:16 12:34:56', $image->exif['EXIF']['DateTimeOriginal']);
        $this->assertArrayNotHasKey('MakerNote', $image->exif['EXIF']);
        $this->assertArrayNotHasKey('FILE', $image->exif);
        $this->assertSame(['Zażółć gęślą jaźń'], $image->iptc['2#120']);
    }

    public function test_stores_null_metadata_for_image_without_it(): void
    {
        $this->upload(['file' => $this->makeImage('png')])->assertCreated();

        $image = Image::sole();
        $this->assertNull($image->exif);
        $this->assertNull($image->iptc);
    }

    public function test_response_does_not_contain_email(): void
    {
        $this->upload()
            ->assertCreated()
            ->assertJsonMissingPath('data.uploader_email')
            ->assertDontSee('anna@example.com');
    }

    public function test_thumbnail_respects_exif_orientation(): void
    {
        // Fixture is 600 x 500 with Orientation = 1; patch the IFD entry to 6 (rotate 90° CW).
        $file = $this->fixture('photo-with-exif.jpg');
        $entry = pack('vvVv', 0x0112, 3, 1, 1);
        $jpeg = file_get_contents($file->getRealPath());
        $this->assertSame(1, substr_count($jpeg, $entry));
        file_put_contents($file->getRealPath(), str_replace($entry, pack('vvVv', 0x0112, 3, 1, 6), $jpeg));

        $this->upload(['file' => $file])->assertCreated();

        $image = Image::sole();
        [$width, $height] = getimagesize(Storage::disk(Image::THUMBNAILS_DISK)->path($image->thumbnail_path));
        $this->assertSame([250, 300], [$width, $height]);
    }

    public function test_removes_written_files_when_saving_fails(): void
    {
        Image::creating(fn () => throw new RuntimeException('Database is down'));

        $this->upload()->assertServerError();

        $this->assertDatabaseEmpty('images');
        $this->assertEmpty(Storage::disk(Image::ORIGINALS_DISK)->allFiles());
        $this->assertEmpty(Storage::disk(Image::THUMBNAILS_DISK)->allFiles());
    }
}
