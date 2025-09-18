<?php

namespace App\Services;

use App\Services\Interfaces\ReferenceServiceInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ReferenceService implements ReferenceServiceInterface
{
    protected array $bookMappings = [];
    protected array $bookAliases = [];

    public function __construct()
    {
        $this->initializeBookMappings();
    }

    /**
     * Initialize book mappings from the JSONL file
     */
    protected function initializeBookMappings(): void
    {
        $jsonlPath = base_path('versiculos.jsonl');
        
        if (!file_exists($jsonlPath)) {
            Log::error('versiculos.jsonl file not found');
            return;
        }

        $handle = fopen($jsonlPath, 'r');
        if (!$handle) {
            Log::error('Could not open versiculos.jsonl for reading');
            return;
        }

        $this->bookMappings = [];
        $this->bookAliases = [];

        while (($line = fgets($handle)) !== false) {
            $data = json_decode(trim($line), true);
            if (!$data) continue;

            $book = $data['libro'] ?? $data['book'] ?? null;
            $slug = $data['slug'] ?? null;
            
            if ($book && $slug) {
                // Store the canonical mapping
                $this->bookMappings[$this->normalizeBookName($book)] = [
                    'name' => $book,
                    'slug' => $slug,
                    'testament' => $this->determineTestament($slug)
                ];

                // Store common aliases
                $this->addAliases($book, $slug);
            }
        }

        fclose($handle);
    }

    /**
     * Add common aliases for book names
     */
    protected function addAliases(string $bookName, string $slug): void
    {
        $aliases = [
            strtolower($bookName),
            Str::ascii($bookName),
            str_replace(' ', '', $bookName),
            str_replace(' ', '', Str::ascii($bookName))
        ];

        foreach ($aliases as $alias) {
            if (!isset($this->bookAliases[$alias])) {
                $this->bookAliases[$alias] = $this->normalizeBookName($bookName);
            }
        }
    }

    /**
     * Normalize book name for consistent comparison
     */
    protected function normalizeBookName(string $bookName): string
    {
        return mb_strtolower(trim($bookName));
    }

    /**
     * Determine testament based on slug prefix
     */
    protected function determineTestament(string $slug): string
    {
        if (str_starts_with($slug, 'BM-')) return 'BM';
        if (str_starts_with($slug, 'DC-')) return 'DC';
        if (str_starts_with($slug, 'PGP-')) return 'PGP';
        return 'AT'; // Default to Old Testament
    }

    /**
     * @inheritDoc
     */
    public function formatReference(array $metadata): string
    {
        $book = $metadata['libro'] ?? $metadata['book'] ?? '';
        $chapter = $metadata['capitulo'] ?? $metadata['chapter'] ?? '';
        $verse = $metadata['versiculo'] ?? $metadata['verse'] ?? '';
        
        $reference = $book;
        $reference .= $chapter ? " $chapter" : '';
        $reference .= $verse ? ":$verse" : '';
        
        return trim($reference);
    }

    /**
     * @inheritDoc
     */
    public function parseReference(string $reference): array
    {
        $reference = trim($reference);
        
        // Handle special cases like "1 Juan" which might be split incorrectly
        $reference = $this->normalizeReference($reference);
        
        // Match book name (may include numbers and spaces)
        if (!preg_match('/^(\d*\s*[\p{L}\s]+)/u', $reference, $matches)) {
            throw new \InvalidArgumentException("Invalid reference format: $reference");
        }
        
        $bookPart = trim($matches[1]);
        $rest = trim(substr($reference, strlen($bookPart)));
        
        // Find the best matching book
        $bookInfo = $this->findBookMapping($bookPart);
        if (!$bookInfo) {
            throw new \InvalidArgumentException("Book not found: $bookPart");
        }
        
        // Parse chapter and verse
        $chapter = 1;
        $verse = null;
        $verseEnd = null;
        
        if ($rest !== '') {
            // Handle chapter:verse or chapter:verse-verse formats
            if (preg_match('/^(\d+)(?::(\d+)(?:-(\d+))?)?/', $rest, $verseMatches)) {
                $chapter = (int)$verseMatches[1];
                $verse = isset($verseMatches[2]) ? (int)$verseMatches[2] : null;
                $verseEnd = $verseMatches[3] ?? null;
            } elseif (is_numeric($rest)) {
                // Just a chapter number
                $chapter = (int)$rest;
            }
        }
        
        return [
            'book' => $bookInfo['name'],
            'book_slug' => $bookInfo['slug'],
            'testament' => $bookInfo['testament'],
            'chapter' => $chapter,
            'verse' => $verse,
            'verse_end' => $verseEnd,
            'reference' => $this->buildReference($bookInfo['name'], $chapter, $verse, $verseEnd)
        ];
    }
    
    /**
     * Normalize reference string for consistent parsing
     */
    protected function normalizeReference(string $reference): string
    {
        // Handle common abbreviations and special cases
        $replacements = [
            '/\s*,\s*/' => ',',  // Normalize spaces around commas
            '/\s+/' => ' ',       // Normalize multiple spaces
            '/\s*:\s*/' => ':',  // Normalize spaces around colons
            '/\s*-\s*/' => '-',  // Normalize spaces around hyphens
        ];
        
        $reference = preg_replace(array_keys($replacements), array_values($replacements), trim($reference));
        
        // Handle Roman numerals for book numbers
        $reference = $this->normalizeRomanNumerals($reference);
        
        return $reference;
    }
    
    /**
     * Normalize Roman numerals in book names
     */
    protected function normalizeRomanNumerals(string $reference): string
    {
        // Handle books like I Juan, II Juan, III Juan
        $patterns = [
            '/^i\s+([a-z])/i' => '1 $1',
            '/^ii\s+([a-z])/i' => '2 $1',
            '/^iii\s+([a-z])/i' => '3 $1',
            '/^iv\s+([a-z])/i' => '4 $1',
            '/^v\s+([a-z])/i' => '5 $1',
            '/^1ra?\.?\s+/i' => '1 ',
            '/^2da?\.?\s+/i' => '2 ',
            '/^3ra?\.?\s+/i' => '3 ',
        ];
        
        return preg_replace(
            array_keys($patterns),
            array_values($patterns),
            $reference
        );
    }
    
    /**
     * Find the best matching book for a given name or alias
     */
    protected function findBookMapping(string $bookName): ?array
    {
        $normalized = $this->normalizeBookName($bookName);
        
        // Check exact match first
        if (isset($this->bookMappings[$normalized])) {
            return $this->bookMappings[$normalized];
        }
        
        // Check aliases
        if (isset($this->bookAliases[$normalized])) {
            $canonicalName = $this->bookAliases[$normalized];
            return $this->bookMappings[$canonicalName] ?? null;
        }
        
        // Try to find by partial match
        foreach ($this->bookMappings as $name => $info) {
            if (str_contains($name, $normalized) || 
                str_contains($normalized, $name) ||
                similar_text($name, $normalized) / max(strlen($name), strlen($normalized)) > 0.7) {
                return $info;
            }
        }
        
        return null;
    }
    
    /**
     * Build a reference string from components
     */
    protected function buildReference(string $book, int $chapter, ?int $verse = null, ?int $verseEnd = null): string
    {
        $reference = $book . ' ' . $chapter;
        
        if ($verse !== null) {
            $reference .= ':' . $verse;
            
            if ($verseEnd !== null && $verseEnd != $verse) {
                $reference .= '-' . $verseEnd;
            }
        }
        
        return $reference;
    }

    /**
     * @inheritDoc
     */
    public function referenceToVectorId(string $reference): string
    {
        $parsed = $this->parseReference($reference);
        
        $bookSlug = $parsed['book_slug'];
        $chapter = str_pad($parsed['chapter'], 2, '0', STR_PAD_LEFT);
        $verse = $parsed['verse'] ? str_pad($parsed['verse'], 3, '0', STR_PAD_LEFT) : '001';
        
        return "{$bookSlug}-{$chapter}-{$verse}";
    }

    /**
     * @inheritDoc
     */
    public function expandReference(string $reference): array
    {
        $parsed = $this->parseReference($reference);
        $verses = [];
        
        $verseStart = $parsed['verse'] ?? 1;
        $verseEnd = $parsed['verse_end'] ?? $verseStart;
        
        for ($v = $verseStart; $v <= $verseEnd; $v++) {
            $verses[] = $this->buildReference(
                $parsed['book'],
                $parsed['chapter'],
                $v
            );
        }
        
        return $verses;
    }
}
