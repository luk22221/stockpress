<?php

namespace Database\Factories;

use App\Models\Image;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Database records only - no files are created on disk.
 *
 * @extends Factory<Image>
 */
class ImageFactory extends Factory
{
    public function definition(): array
    {
        $uuid = fake()->uuid();

        return [
            'original_name' => fake()->word().'.jpg',
            'path' => "images/{$uuid}.jpg",
            'thumbnail_path' => "thumbnails/{$uuid}.webp",
            'mime' => 'image/jpeg',
            'extension' => 'jpg',
            'size' => fake()->numberBetween(50_000, 5_000_000),
            'width' => fake()->numberBetween(500, 4000),
            'height' => fake()->numberBetween(500, 4000),
            'uploader_name' => fake()->firstName(),
            'uploader_email' => fake()->safeEmail(),
            'temperature' => fake()->randomFloat(1, -10, 30),
            'temperature_time' => now()->startOfHour(),
        ];
    }
}
