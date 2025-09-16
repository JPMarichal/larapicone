<?php

use App\Http\Controllers\Api\PineconeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Pinecone API Routes
Route::prefix('pinecone')->group(function () {
    // Query vectors
    Route::post('/query', [PineconeController::class, 'query']);
    
    // Upsert vectors
    Route::post('/upsert', [PineconeController::class, 'upsert']);
    
    // Get vector by ID
    Route::get('/vector/{id}', [PineconeController::class, 'getVector']);
    
    // Delete vectors
    Route::delete('/delete', [PineconeController::class, 'delete']);
});
