<?php

namespace Tests\Feature;

use App\Models\Image;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageListTest extends TestCase
{
    public function test_lists_images_with_cursor_pagination(): void
    {
        Image::factory()->count(15)->create();

        $first = $this->getJson('/api/images')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonStructure([
                'data' => [['id', 'thumbnail_url', 'download_url', 'extension', 'size', 'width', 'height', 'uploader_name', 'temperature', 'created_at']],
                'meta' => ['next_cursor', 'prev_cursor'],
            ]);

        $this->assertSame(range(15, 6), $first->json('data.*.id'));
        $this->assertNotNull($cursor = $first->json('meta.next_cursor'));

        $second = $this->getJson('/api/images?cursor='.$cursor)
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.next_cursor', null);

        $this->assertSame(range(5, 1), $second->json('data.*.id'));
    }

    public function test_cursor_does_not_repeat_items_when_new_images_are_added(): void
    {
        Image::factory()->count(15)->create();
        $cursor = $this->getJson('/api/images')->json('meta.next_cursor');

        Image::factory()->count(3)->create();

        $this->assertSame(range(5, 1), $this->getJson('/api/images?cursor='.$cursor)->json('data.*.id'));
    }

    public function test_list_does_not_expose_uploader_email(): void
    {
        Image::factory()->create(['uploader_email' => 'secret@example.com']);

        $this->getJson('/api/images')
            ->assertOk()
            ->assertJsonMissingPath('data.0.uploader_email')
            ->assertDontSee('secret@example.com');

        $this->getJson('/api/images/1')
            ->assertOk()
            ->assertJsonMissingPath('data.uploader_email')
            ->assertDontSee('secret@example.com');
    }

    public function test_returns_absolute_urls(): void
    {
        $image = Image::factory()->create();

        $this->getJson('/api/images')
            ->assertJsonPath('data.0.download_url', url("/api/images/{$image->id}/download"))
            ->assertJsonPath('data.0.thumbnail_url', Storage::disk(Image::THUMBNAILS_DISK)->url($image->thumbnail_path));
    }

    public function test_downloads_original_with_original_name(): void
    {
        $this->fakeWeather();
        $id = $this->upload(['file' => $this->fixture('photo-with-exif.jpg')])->json('data.id');

        $response = $this->get("/api/images/{$id}/download")
            ->assertOk()
            ->assertDownload('photo-with-exif.jpg');

        $this->assertSame(file_get_contents(base_path('tests/fixtures/photo-with-exif.jpg')), $response->streamedContent());
    }

    public function test_deletes_record_and_both_files(): void
    {
        $this->fakeWeather();
        $id = $this->upload()->json('data.id');
        $image = Image::findOrFail($id);
        Storage::disk(Image::ORIGINALS_DISK)->assertExists($image->path);
        Storage::disk(Image::THUMBNAILS_DISK)->assertExists($image->thumbnail_path);

        $this->deleteJson("/api/images/{$id}")->assertNoContent();

        $this->assertModelMissing($image);
        Storage::disk(Image::ORIGINALS_DISK)->assertMissing($image->path);
        Storage::disk(Image::THUMBNAILS_DISK)->assertMissing($image->thumbnail_path);
    }

    public function test_returns_404_for_missing_image(): void
    {
        $this->getJson('/api/images/999')->assertNotFound();
        $this->get('/api/images/999/download')->assertNotFound();
        $this->deleteJson('/api/images/999')->assertNotFound();
    }

    public function test_api_responses_have_noindex_header(): void
    {
        $this->getJson('/api/images')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }
}
