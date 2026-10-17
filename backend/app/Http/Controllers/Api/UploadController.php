<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadImageRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadController extends Controller
{
    public function store(UploadImageRequest $request): JsonResponse
    {
        $file = $request->file('image');
        $filename = Str::uuid()->toString().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs('uploads', $filename, 'public');

        return response()->json([
            'data' => [
                'url' => url(Storage::disk('public')->url($path)),
                'path' => $path,
                'name' => $file->getClientOriginalName(),
            ],
        ], 201);
    }
}
