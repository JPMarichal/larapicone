<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SparseVectorService
{
    /**
     * @var array Common Spanish stop words to filter out
     */
    protected array $stopWords = [
        'de', 'la', 'que', 'el', 'en', 'y', 'a', 'los', 'del', 'se', 'las', 'por', 'un', 'para', 'con', 'no', 'una', 
        'su', 'al', 'lo', 'como', 'más', 'pero', 'sus', 'le', 'ya', 'o', 'este', 'sí', 'porque', 'esta', 'entre', 
        'cuando', 'muy', 'sin', 'sobre', 'también', 'me', 'hasta', 'hay', 'donde', 'quien', 'desde', 'todo', 'nos', 
        'durante', 'todos', 'uno', 'les', 'ni', 'contra', 'otros', 'ese', 'eso', 'ante', 'ellos', 'e', 'esto', 'mí', 
        'antes', 'algunos', 'qué', 'unos', 'yo', 'otro', 'otras', 'otra', 'él', 'tanto', 'esa', 'estos', 'mucho', 
        'esos', 'cada', 'poco', 'tener', 'tambien', 'ser', 'así', 'veces', 'toda', 'mismo', 'tan', 'estoy', 'ha', 
        'tiene', 'tienen', 'he', 'tiene', 'tengo', 'tienes', 'tenemos', 'tienen', 'tuve', 'tuvo', 'tuvimos', 'tuvieron',
        'había', 'habían', 'habías', 'habíamos', 'habían', 'hube', 'hubo', 'hubimos', 'hubieron', 'habré', 'habrás',
        'habrá', 'habremos', 'habrán', 'habría', 'habrías', 'habríamos', 'habrían', 'hecho', 'hacer', 'hizo', 'hace',
        'hacen', 'haciendo', 'hice', 'hiciste', 'hizo', 'hicimos', 'hicieron', 'haré', 'harás', 'hará', 'haremos',
        'harán', 'haría', 'harías', 'haríamos', 'harían', 'como', 'cual', 'cuales', 'cualquier', 'cualquiera',
        'cuando', 'cuanto', 'cuenta', 'cuyo', 'da', 'dado', 'dan', 'dar', 'de', 'debe', 'deben', 'debería', 'deberían',
        'debido', 'decir', 'dejó', 'del', 'demás', 'dentro', 'desde', 'después', 'dice', 'dicen', 'dicho', 'dieron',
        'diferente', 'dijeron', 'dijo', 'dio', 'donde', 'dos', 'durante', 'e', 'ejemplo', 'el', 'él', 'ella', 'ellas',
        'ellos', 'embargo', 'emplean', 'en', 'encuentra', 'entonces', 'entre', 'era', 'eran', 'es', 'esa', 'esas',
        'ese', 'eso', 'esos', 'esta', 'está', 'están', 'estar', 'estará', 'estas', 'este', 'esto', 'estos', 'estoy',
        'estuvo', 'estuvo', 'ex', 'existe', 'existen', 'explicó', 'expresó', 'fin', 'fue', 'fuera', 'fueron', 'fui',
        'gran', 'ha', 'haber', 'había', 'habían', 'había', 'habían', 'haber', 'habrá', 'habrán', 'habría', 'habrían',
        'hace', 'hacemos', 'hacen', 'hacer', 'hacerlo', 'hacia', 'haciendo', 'hago', 'han', 'hasta', 'hay', 'haya',
        'he', 'hecho', 'hemos', 'hicieron', 'hizo', 'hoy', 'hubo', 'igual', 'incluso', 'indicó', 'informó', 'junto',
        'la', 'lado', 'las', 'le', 'les', 'llegó', 'lleva', 'llevar', 'lo', 'los', 'luego', 'lugar', 'más', 'me',
        'mediante', 'mejor', 'mencionó', 'menos', 'mi', 'mientras', 'misma', 'mismas', 'mismo', 'momentos', 'mucha',
        'muchas', 'mucho', 'muchos', 'muy', 'nada', 'ni', 'ningún', 'ninguna', 'ninguno', 'no', 'nos', 'nosotras',
        'nosotros', 'nuestra', 'nuestras', 'nuestro', 'nuestros', 'nueva', 'nuevas', 'nuevo', 'nunca', 'o', 'ocho',
        'otra', 'otras', 'otro', 'otros', 'para', 'parece', 'parte', 'partir', 'pasada', 'pasado', 'pero', 'pesar',
        'poca', 'pocas', 'pocos', 'poder', 'podrá', 'podrán', 'podría', 'podrían', 'poner', 'por', 'porque', 'posible',
        'primer', 'primera', 'primero', 'próximo', 'próximos', 'pudo', 'pueda', 'puede', 'pueden', 'puedo', 'pues',
        'que', 'qué', 'quedó', 'queremos', 'quién', 'quien', 'quienes', 'quiere', 'realizado', 'realizar', 'realizó',
        'respecto', 'sabe', 'saben', 'se', 'sé', 'señaló', 'ser', 'será', 'serán', 'sería', 'si', 'sí', 'sido',
        'siempre', 'siendo', 'siete', 'sigue', 'siguiente', 'sino', 'sobre', 'soy', 'sólo', 'son', 'su', 'sus', 'tal',
        'también', 'tampoco', 'tan', 'tanto', 'tarde', 'te', 'té', 'tendrá', 'tendrán', 'tener', 'tenga', 'tengo',
        'tiene', 'tienen', 'tipo', 'toda', 'todas', 'todavía', 'todo', 'todos', 'tomar', 'tras', 'tuvo', 'un', 'una',
        'unas', 'uno', 'unos', 'usted', 'va', 'vamos', 'van', 'varias', 'varios', 'veces', 'ver', 'vez', 'y', 'ya',
        'yo'
    ];

    /**
     * Generate a sparse vector from text using TF-IDF
     * 
     * @param string $text
     * @return array
     */
    public function generateSparseVector(string $text): array
    {
        try {
            // Tokenize and preprocess the text
            $tokens = $this->preprocessText($text);
            
            if (empty($tokens)) {
                return ['indices' => [], 'values' => []];
            }
            
            // Calculate term frequencies
            $termFrequencies = array_count_values($tokens);
            $totalTerms = count($tokens);
            
            // Generate sparse vector
            $indices = [];
            $values = [];
            
            foreach ($termFrequencies as $term => $count) {
                // Skip empty terms and very short terms
                if (empty(trim($term)) || mb_strlen($term) < 2) {
                    continue;
                }
                
                // Hash the term to an index (using a larger range for better distribution)
                $index = $this->hashTerm($term);
                
                // Calculate TF-IDF (term frequency * inverse document frequency)
                $tf = $count / $totalTerms;
                $idf = $this->calculateIdf($term);
                
                $indices[] = $index;
                $values[] = $tf * $idf;
            }
            
            // Normalize the vector to unit length for cosine similarity
            $this->normalizeVector($values);
            
            return [
                'indices' => $indices,
                'values' => $values
            ];
            
        } catch (\Exception $e) {
            Log::error('Error generating sparse vector', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return ['indices' => [], 'values' => []];
        }
    }
    
    /**
     * Preprocess text: tokenize, remove stop words, and apply stemming
     * 
     * @param string $text
     * @return array
     */
    protected function preprocessText(string $text): array
    {
        // Convert to lowercase
        $text = mb_strtolower(trim($text));
        
        // Remove punctuation and special characters
        $text = preg_replace("/[^\p{L}\p{N}\s]/u", ' ', $text);
        
        // Tokenize (split into words)
        $tokens = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        
        if (empty($tokens)) {
            return [];
        }
        
        // Remove stop words and short words
        $tokens = array_filter($tokens, function($term) {
            $term = trim($term);
            return !empty($term) && 
                   mb_strlen($term) > 1 && 
                   !in_array($term, $this->stopWords) && 
                   !is_numeric($term);
        });
        
        // Apply simple stemming (remove common suffixes)
        $tokens = array_map([$this, 'stemWord'], $tokens);
        
        return array_values($tokens);
    }
    
    /**
     * Simple Spanish stemmer (removes common suffixes)
     * 
     * @param string $word
     * @return string
     */
    protected function stemWord(string $word): string
    {
        $word = trim($word);
        
        // Remove plural forms and verb endings
        $patterns = [
            '/as$/' => 'a',  // casas -> casa
            '/es$/' => '',    // casas -> cas
            '/os$/' => 'o',   // libros -> libro
            '/a$/' => '',     // casa -> cas
            '/o$/' => '',     // libro -> libr
            '/e$/' => '',     // grande -> grand
            '/ar$/' => '',    // hablar -> habl
            '/er$/' => '',    // comer -> com
            '/ir$/' => '',    // vivir -> viv
            '/ando$/' => '',  // hablando -> habl
            '/iendo$/' => '', // comiendo -> com
            '/mente$/' => ''  // rápidamente -> rápid
        ];
        
        foreach ($patterns as $pattern => $replacement) {
            $word = preg_replace($pattern, $replacement, $word);
        }
        
        return $word;
    }
    
    /**
     * Calculate IDF (Inverse Document Frequency) for a term
     * This is a simplified version - in production, you'd want to use actual document frequencies
     * 
     * @param string $term
     * @return float
     */
    protected function calculateIdf(string $term): float
    {
        // In a real implementation, you'd want to use actual document frequencies
        // For now, we'll use a simplified version that gives more weight to less common terms
        $docFreq = 1; // Pretend the term appears in 1 document
        $totalDocs = 1000; // Pretend we have 1000 documents in total
        
        return log(1 + ($totalDocs / ($docFreq + 1)));
    }
    
    /**
     * Hash a term to a numerical index (0 to 9999)
     * 
     * @param string $term
     * @return int
     */
    protected function hashTerm(string $term): int
    {
        // Use a combination of crc32 and md5 for better distribution
        $hash1 = crc32($term);
        $hash2 = hexdec(substr(md5($term), 0, 8));
        
        // Combine the hashes and mod to get a value between 0 and 9999
        return abs(($hash1 ^ $hash2) % 10000);
    }
    
    /**
     * Normalize a vector to unit length (Euclidean norm)
     * 
     * @param array $vector
     * @return void
     */
    protected function normalizeVector(array &$vector): void
    {
        $sum = 0;
        foreach ($vector as $value) {
            $sum += $value * $value;
        }
        
        $norm = sqrt($sum);
        
        if ($norm > 0) {
            foreach ($vector as &$value) {
                $value /= $norm;
            }
        }
    }

    /**
     * Create a sparse vector from text
     * 
     * @param string $text
     * @param array $customStopWords
     * @return array
     */
    public function createSparseVector(string $text, array $customStopWords = []): array
    {
        if (!empty($customStopWords)) {
            $this->stopWords = array_merge($this->stopWords, $customStopWords);
        }
        return $this->generateSparseVector($text);
    }

    /**
     * Calculate cosine similarity between two sparse vectors
     * 
     * @param array $vec1
     * @param array $vec2
     * @return float
     */
    public function cosineSimilarity(array $vec1, array $vec2): float
    {
        if (empty($vec1['indices']) || empty($vec2['indices'])) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $i = $j = 0;
        $n1 = count($vec1['indices']);
        $n2 = count($vec2['indices']);

        // Calculate dot product
        while ($i < $n1 && $j < $n2) {
            if ($vec1['indices'][$i] === $vec2['indices'][$j]) {
                $dotProduct += $vec1['values'][$i] * $vec2['values'][$j];
                $i++;
                $j++;
            } elseif ($vec1['indices'][$i] < $vec2['indices'][$j]) {
                $i++;
            } else {
                $j++;
            }
        }

        return $dotProduct; // Vectors are already normalized
    }

    /**
     * Normalize text by removing punctuation, converting to lowercase, etc.
     * 
     * @param string $text
     * @return string
     */
    public function normalizeText(string $text): string
    {
        // Convert to lowercase
        $text = mb_strtolower(trim($text));
        
        // Remove punctuation and special characters
        $text = preg_replace("/[^\p{L}\p{N}\s]/u", ' ', $text);
        
        // Replace multiple spaces with a single space
        $text = preg_replace('/\s+/', ' ', $text);
        
        return trim($text);
    }
}
