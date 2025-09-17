<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PineconeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * @OA\Info(title="Pinecone Scripture API", version="1.0.0")
 * @OA\Server(url="http://localhost:8000")
 * @OA\Tag(name="Vectors")
 * @OA\Tag(name="Scriptures")
 */

class PineconeController extends Controller
{
    protected PineconeService $pineconeService;

    public function __construct(PineconeService $pineconeService)
    {
        $this->pineconeService = $pineconeService;
    }
    
    /**
     * @OA\Get(
     *     path="/api/pinecone/debug",
     *     tags={"Vectors"},
     *     summary="Obtener información de depuración del servicio Pinecone",
     *     @OA\Response(response=200, description="OK")
     * )
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Post(
     *     path="/api/pinecone/search/character",
     *     tags={"Scriptures"},
     *     summary="Buscar información sobre un personaje bíblico",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"query"},
     *             @OA\Property(property="query", type="string", example="¿Quién fue Melquisedec?"),
     *             @OA\Property(property="limit", type="integer", example=5, description="Número máximo de resultados a devolver"),
     *             @OA\Property(
     *                 property="filters",
     *                 type="object",
     *                 @OA\Property(property="volume", type="string", example="BM", description="Filtrar por volumen: 'BM' (Libro de Mormón), 'AT' (Antiguo Testamento), 'NT' (Nuevo Testamento), 'DyC' (Doctrina y Convenios), 'PdeGP' (Perla de Gran Precio)")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Resultados de la búsqueda",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="query", type="string", example="¿Quién fue Melquisedec?"),
     *             @OA\Property(property="count", type="integer", example=5),
     *             @OA\Property(
     *                 property="results",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="reference", type="string", example="Génesis 14:18"),
     *                     @OA\Property(property="text", type="string", example="Entonces Melquisedec, rey de Salem, sacó pan y vino..."),
     *                     @OA\Property(property="score", type="number", format="float", example=0.95),
     *                     @OA\Property(property="character", type="string", example="Melquisedec"),
     *                     @OA\Property(property="context", type="string", example="Melquisedec bendice a Abraham")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="El campo query es obligatorio"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function searchCharacter(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:3|max:500',
            'limit' => 'sometimes|integer|min:1|max:20',
            'filters' => 'sometimes|array',
            'filters.volume' => 'sometimes|string|in:BM,AT,NT,DyC,PdeGP',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $results = $this->pineconeService->semanticCharacterSearch(
                $request->input('query'),
                $request->input('limit', 5),
                $request->input('filters', [])
            );

            return response()->json([
                'success' => true,
                'query' => $results['query'],
                'count' => $results['count'],
                'results' => $results['results']
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al realizar la búsqueda: ' . $e->getMessage(),
                'error' => config('app.debug') ? $e->getTraceAsString() : null
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/pinecone/debug",
     *     tags={"Vectors"},
     *     summary="Obtener información de depuración del servicio Pinecone",
     *     @OA\Response(response=200, description="OK")
     * )
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
     * @OA\Post(
     *     path="/api/pinecone/query",
     *     tags={"Vectors"},
     *     summary="Consultar el índice Pinecone con un vector",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"vector"},
     *             @OA\Property(property="vector", type="array", @OA\Items(type="number")),
     *             @OA\Property(property="top_k", type="integer", example=5),
     *             @OA\Property(property="filter", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Resultados de la consulta",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
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
     * @OA\Post(
     *     path="/api/pinecone/vector/reference",
     *     tags={"Scriptures"},
     *     summary="Obtener vector por referencia bíblica",
     *     @OA\Parameter(name="reference", in="query", required=true),
     *     @OA\Parameter(name="include_values", in="query", required=false),
     *     @OA\Response(response=200, description="OK")
     * )
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
     * @OA\Post(
     *     path="/api/pinecone/vector/passage",
     *     tags={"Scriptures"},
     *     summary="Obtener vectores por pasaje bíblico (rangos y múltiples referencias)",
     *     @OA\Parameter(name="passage", in="query", required=true),
     *     @OA\Response(response=200, description="OK")
     * )
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
