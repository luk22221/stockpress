<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('images', function (Blueprint $table) {
            $table->id();
            $table->string('original_name');
            $table->string('path');
            $table->string('thumbnail_path');
            $table->string('mime', 100);
            $table->string('extension', 10);
            $table->unsignedInteger('size');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->string('uploader_name');
            $table->string('uploader_email');
            $table->json('exif')->nullable();
            $table->json('iptc')->nullable();
            $table->decimal('temperature', 4, 1)->nullable();
            $table->dateTime('temperature_time')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('images');
    }
};
