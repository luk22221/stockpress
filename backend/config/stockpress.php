<?php

return [

    // Longer side of generated thumbnails, in pixels.
    'thumbnail_size' => (int) env('THUMBNAIL_SIZE', 300),

    // Open-Meteo hourly forecast for Katowice. Times in the response are local
    // to the given timezone, which must match the "timezone" query parameter.
    'weather' => [
        'url' => env('WEATHER_API_URL', 'https://api.open-meteo.com/v1/forecast?latitude=50.25841&longitude=19.02754&hourly=temperature_2m&timezone=Europe%2FWarsaw&forecast_days=1'),
        'timezone' => env('WEATHER_TIMEZONE', 'Europe/Warsaw'),
    ],

    // Requests per minute per IP address.
    'rate_limits' => [
        'uploads' => (int) env('RATE_LIMIT_UPLOADS', 20),
        'deletes' => (int) env('RATE_LIMIT_DELETES', 30),
    ],

];
