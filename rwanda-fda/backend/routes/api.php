<?php
use App\Http\Controllers\ManufacturerController;
use Illuminate\Support\Facades\Route;

Route::apiResource('manufacturers', ManufacturerController::class)->only(['index', 'store', 'show']);
