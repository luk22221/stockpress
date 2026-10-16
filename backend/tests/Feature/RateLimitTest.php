<?php

namespace Tests\Feature;

use App\Models\Image;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    public function test_upload_rate_limit_returns_429(): void
    {
        config(['stockpress.rate_limits.uploads' => 2]);

        $this->postJson('/api/images')->assertUnprocessable();
        $this->postJson('/api/images')->assertUnprocessable();

        $this->postJson('/api/images')
            ->assertTooManyRequests()
            ->assertHeader('Retry-After')
            ->assertJsonPath('message', 'Zbyt wiele żądań. Spróbuj ponownie za chwilę.');
    }

    public function test_delete_rate_limit_returns_429(): void
    {
        config(['stockpress.rate_limits.deletes' => 1]);
        $images = Image::factory()->count(2)->create();

        $this->deleteJson("/api/images/{$images[0]->id}")->assertNoContent();
        $this->deleteJson("/api/images/{$images[1]->id}")->assertTooManyRequests();

        $this->assertModelExists($images[1]);
    }

    public function test_reading_is_not_rate_limited(): void
    {
        config(['stockpress.rate_limits.uploads' => 1]);

        foreach (range(1, 5) as $i) {
            $this->getJson('/api/images')->assertOk();
        }
    }
}
