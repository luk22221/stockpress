<?php

namespace App\Http\Controllers;

use App\Actions\StoreImage;
use App\Http\Requests\StoreImageRequest;
use App\Http\Resources\ImageDetailsResource;
use App\Http\Resources\ImageResource;
use App\Models\Image;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImageController extends Controller
{
    /**
     * Cursor pagination: unlike offsets it does not skip or repeat items
     * when images are added or deleted while the user scrolls.
     */
    public function index(): AnonymousResourceCollection
    {
        return ImageResource::collection(
            Image::query()->orderByDesc('id')->cursorPaginate(10)
        );
    }

    public function store(StoreImageRequest $request, StoreImage $storeImage): JsonResponse
    {
        $image = $storeImage->handle(
            $request->file('file'),
            $request->validated('name'),
            $request->validated('email'),
        );

        return ImageResource::make($image)->response()->setStatusCode(201);
    }

    public function show(Image $image): ImageDetailsResource
    {
        return ImageDetailsResource::make($image);
    }

    public function download(Image $image): StreamedResponse
    {
        return Storage::disk(Image::ORIGINALS_DISK)->download($image->path, $image->original_name);
    }

    /**
     * Removes the record; the Image model deletes both files afterwards.
     */
    public function destroy(Image $image): Response
    {
        $image->delete();

        return response()->noContent();
    }
}
