<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Image extends Model
{
    use HasFactory;

    /** Disk for original files (not publicly accessible). */
    public const ORIGINALS_DISK = 'local';

    /** Disk for thumbnails (served directly by the web server). */
    public const THUMBNAILS_DISK = 'public';

    protected $fillable = [
        'original_name',
        'path',
        'thumbnail_path',
        'mime',
        'extension',
        'size',
        'width',
        'height',
        'uploader_name',
        'uploader_email',
        'exif',
        'iptc',
        'temperature',
        'temperature_time',
    ];

    protected $hidden = [
        'uploader_email',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'exif' => 'array',
            'iptc' => 'array',
            'temperature' => 'float',
            'temperature_time' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleted(function (Image $image) {
            Storage::disk(self::ORIGINALS_DISK)->delete($image->path);
            Storage::disk(self::THUMBNAILS_DISK)->delete($image->thumbnail_path);
        });
    }
}
