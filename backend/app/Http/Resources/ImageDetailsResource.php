<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * Single image with raw metadata. Still without the uploader's e-mail.
 */
class ImageDetailsResource extends ImageResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'mime' => $this->mime,
            'temperature_time' => $this->temperature_time,
            'exif' => $this->exif,
            'iptc' => $this->iptc,
        ];
    }
}
