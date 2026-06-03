<?php
use App\Http\Controllers\ManufacturerController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::apiResource('manufacturers', ManufacturerController::class)->only(['index', 'store', 'show']);
Route::apiResource('products', ProductController::class)->only(['index', 'store', 'show']);
