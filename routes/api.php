<?php

use App\Http\Controllers\Api\PineconeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Test route to verify controller is working
Route::get('/pinecone/test', function (\App\Services\PineconeService $pinecone) {
    try {
        // Test connection by getting a vector (using a known ID from your screenshot)
        $testId = 'AT-genesis-06-010';
        $vector = $pinecone->getVector($testId);
        
        return response()->json([
            'status' => 'success',
            'message' => 'Successfully connected to Pinecone!',
            'vector_id' => $testId,
            'vector_data' => $vector,
            'config' => [
                'environment' => config('pinecone.environment'),
                'index' => config('pinecone.index'),
                'namespace' => config('pinecone.namespace'),
                'base_uri' => "https://" . config('pinecone.index') . "-" . config('pinecone.environment') . ".svc." . config('pinecone.environment') . ".pinecone.io/"
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Error connecting to Pinecone: ' . $e->getMessage(),
            'file' => $e->getFile() . ':' . $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ], 500);
    }
});

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

// Character search endpoint
Route::post('/pinecone/search/character', [PineconeController::class, 'searchCharacter']);

// Pinecone API Routes
Route::prefix('pinecone')->group(function () {
    // Debug endpoint
    Route::get('/debug', [PineconeController::class, 'debug']);
    // Query vectors
    Route::post('/query', [PineconeController::class, 'query']);
    
    // Upsert vectors
    Route::post('/upsert', [PineconeController::class, 'upsert']);
    
    // Get vector by ID
    Route::get('/vector/{id}', [PineconeController::class, 'getVector']);
    
    // Get vector by reference
    Route::post('/vector/reference', [PineconeController::class, 'getVectorByReference']);
    
    // Get vectors by passage
    Route::post('/vector/passage', [PineconeController::class, 'getVectorsByPassage']);
    
    // Delete vectors
    Route::delete('/delete', [PineconeController::class, 'delete']);
});
