<?php

namespace App\Http\Resources;

use App\Models\Image;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin Image
 */
class ImageResource extends JsonResource
{
    /**
     * The uploader's e-mail is stored but intentionally never exposed: the API is public.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'original_name' => $this->original_name,
            'thumbnail_url' => Storage::disk(Image::THUMBNAILS_DISK)->url($this->thumbnail_path),
            'download_url' => route('images.download', $this->resource),
            'extension' => $this->extension,
            'size' => $this->size,
            'width' => $this->width,
            'height' => $this->height,
            'uploader_name' => $this->uploader_name,
            'temperature' => $this->temperature,
            'created_at' => $this->created_at,
        ];
    }
}
