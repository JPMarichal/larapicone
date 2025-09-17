<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PineconeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PineconeController extends Controller
{
    protected PineconeService $pineconeService;

    public function __construct(PineconeService $pineconeService)
    {
        $this->pineconeService = $pineconeService;
    }
    
    /**
     * Get debug information about the Pinecone index
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function debug()
    {
        try {
            $debugInfo = $this->pineconeService->getDebugInfo();
            
            return response()->json([
                'success' => true,
                'data' => $debugInfo
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Query the Pinecone index
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function query(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'vector' => 'required|array',
            'vector.*' => 'numeric',
            'top_k' => 'sometimes|integer|min:1|max:100',
            'filter' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $result = $this->pineconeService->query(
                $request->input('vector'),
                $request->input('top_k', 5),
                $request->input('filter', [])
            );

            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upsert vectors to the index
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function upsert(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'vectors' => 'required|array',
            'vectors.*.id' => 'required|string',
            'vectors.*.values' => 'required|array',
            'vectors.*.values.*' => 'numeric',
            'vectors.*.metadata' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $result = $this->pineconeService->upsertVectors($request->input('vectors'));
            
            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get vector by ID
     *
     * @param Request $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getVector(Request $request, string $id)
    {
        try {
            $includeValues = $request->get('include_values', 'false') === 'true';
            $result = $this->pineconeService->getVector($id, $includeValues);
            
            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete vectors by ID or filter
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * Get vector by reference
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getVectorByReference(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'reference' => 'required|string',
            'include_values' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $reference = $request->input('reference');
            $includeValues = $request->boolean('include_values', false);
            
            $result = $this->pineconeService->getVectorByReference($reference, $includeValues);
            
            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get multiple vectors by passage reference
     * Handles ranges like "Juan 1:1-3" and multiple references like "Juan 1:1-3, 14"
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getVectorsByPassage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'passage' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $passage = $request->input('passage');
            $includeValues = false; // Always false by default
            
            $result = $this->pineconeService->getVectorsByPassage($passage, $includeValues);
            
            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete vectors by ID or filter
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function delete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'sometimes|array',
            'ids.*' => 'string',
            'filter' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $result = $this->pineconeService->deleteVectors(
                $request->input('ids', []),
                $request->input('filter', [])
            );
            
            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
