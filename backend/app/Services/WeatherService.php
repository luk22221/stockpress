<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\Config;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;
use UnexpectedValueException;

class WeatherService
{
    public function __construct(
        #[Config('stockpress.weather.url')] private string $url,
        #[Config('stockpress.weather.timezone')] private string $timezone,
    ) {}

    /**
     * Current air temperature in Katowice, or null when Open-Meteo is unavailable.
     *
     * The API returns hourly values for the whole day, so the entry for the current
     * hour is picked and cached until the end of that hour.
     *
     * @return array{temperature: float, time: CarbonImmutable}|null
     */
    public function currentTemperature(): ?array
    {
        $now = CarbonImmutable::now($this->timezone);

        try {
            // Failures throw, so they are not cached and the next upload tries again.
            $reading = Cache::remember(
                'weather:temperature:'.$now->format('Y-m-d-H'),
                $now->endOfHour(),
                fn () => $this->fetch($now->format('Y-m-d\TH:00')),
            );
        } catch (Throwable $e) {
            Log::warning('Could not fetch temperature from Open-Meteo.', ['error' => $e->getMessage()]);

            return null;
        }

        return [
            'temperature' => $reading['temperature'],
            'time' => CarbonImmutable::parse($reading['time'], $this->timezone),
        ];
    }

    /**
     * @return array{temperature: float, time: string}
     */
    private function fetch(string $hour): array
    {
        $hourly = Http::timeout(3)
            ->retry(2, 200)
            ->get($this->url)
            ->json('hourly');

        $index = array_search($hour, $hourly['time'] ?? [], true);
        $temperature = $index === false ? null : ($hourly['temperature_2m'][$index] ?? null);

        if (! is_numeric($temperature)) {
            throw new UnexpectedValueException("No temperature for {$hour} in the Open-Meteo response.");
        }

        return ['temperature' => (float) $temperature, 'time' => $hour];
    }
}
