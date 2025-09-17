<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class HealthController extends Controller
{
    public function check()
    {
        try {
            $response = Http::timeout(5)->get(config('services.ollama.base_url') . '/api/tags');
            
            if ($response->successful()) {
                return response()->json([
                    'status' => 'ok',
                    'services' => [
                        'ollama' => 'running'
                    ]
                ]);
            }
            
            return response()->json([
                'status' => 'error',
                'message' => 'Ollama service is not responding properly',
                'details' => $response->body()
            ], 503);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to connect to Ollama service',
                'error' => $e->getMessage()
            ], 503);
        }
    }
}
