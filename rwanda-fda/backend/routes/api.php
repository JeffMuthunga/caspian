<?php
use App\Http\Controllers\BatchController;
use App\Http\Controllers\ManufacturerController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::apiResource('manufacturers', ManufacturerController::class)->only(['index', 'store', 'show']);
Route::apiResource('products', ProductController::class)->only(['index', 'store', 'show']);
Route::apiResource('batches', BatchController::class)->only(['index', 'store', 'show']);
