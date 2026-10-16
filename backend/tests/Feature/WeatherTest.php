<?php

namespace Tests\Feature;

use App\Models\Image;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class WeatherTest extends TestCase
{
    public function test_stores_temperature_for_current_hour_in_warsaw(): void
    {
        // 14:35 in Warsaw (CEST) is 12:35 UTC.
        $this->travelTo(CarbonImmutable::parse('2026-07-01 14:35:00', 'Europe/Warsaw'));
        $this->fakeWeather();

        $this->upload()
            ->assertCreated()
            ->assertJsonPath('data.temperature', 14.5);

        $image = Image::sole();
        $this->assertSame(14.5, $image->temperature);
        $this->assertSame('2026-07-01 12:00:00', $image->temperature_time->utc()->format('Y-m-d H:i:s'));

        Http::assertSent(fn (Request $request) => $request->url() === config('stockpress.weather.url'));
    }

    public function test_caches_temperature_within_the_same_hour(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-07-01 09:05:00', 'Europe/Warsaw'));
        $this->fakeWeather();

        $this->upload()->assertCreated();
        $this->travelTo(CarbonImmutable::parse('2026-07-01 09:55:00', 'Europe/Warsaw'));
        $this->upload()->assertCreated();
        Http::assertSentCount(1);

        // Next hour - a fresh request.
        $this->travelTo(CarbonImmutable::parse('2026-07-01 10:01:00', 'Europe/Warsaw'));
        $this->upload()->assertCreated()->assertJsonPath('data.temperature', 10.5);
        Http::assertSentCount(2);
    }

    public function test_upload_succeeds_without_temperature_when_open_meteo_fails(): void
    {
        Log::spy();
        Http::fake(['api.open-meteo.com/*' => Http::response('Service Unavailable', 503)]);

        $this->upload()
            ->assertCreated()
            ->assertJsonPath('data.temperature', null);

        $image = Image::sole();
        $this->assertNull($image->temperature);
        $this->assertNull($image->temperature_time);
        Log::shouldHaveReceived('warning')->withArgs(fn (string $message) => str_contains($message, 'Open-Meteo'))->once();
    }

    public function test_upload_succeeds_when_open_meteo_is_unreachable(): void
    {
        Http::fake(['api.open-meteo.com/*' => Http::failedConnection()]);

        $this->upload()->assertCreated()->assertJsonPath('data.temperature', null);
    }

    public function test_upload_succeeds_when_current_hour_is_missing_in_response(): void
    {
        Http::fake(['api.open-meteo.com/*' => Http::response(['hourly' => ['time' => [], 'temperature_2m' => []]])]);

        $this->upload()->assertCreated()->assertJsonPath('data.temperature', null);
    }
}
