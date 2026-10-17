<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ComponentCatalog;
use Illuminate\Http\JsonResponse;

class ComponentController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => ComponentCatalog::all(),
        ]);
    }
}
