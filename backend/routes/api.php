<?php

use App\Http\Controllers\Api\ComponentController;
use App\Http\Controllers\Api\RenderController;
use App\Http\Controllers\Api\TemplateController;
use App\Http\Controllers\Api\UploadController;
use Illuminate\Support\Facades\Route;

Route::get('/components', [ComponentController::class, 'index']);
Route::post('/uploads', [UploadController::class, 'store']);

Route::apiResource('templates', TemplateController::class);

Route::post('/render/preview', [RenderController::class, 'preview']);
Route::post('/render/pdf', [RenderController::class, 'pdf']);
Route::post('/render/docx', [RenderController::class, 'docx']);
