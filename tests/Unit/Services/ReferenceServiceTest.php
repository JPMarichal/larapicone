<?php

namespace Tests\Unit\Services;

use App\Services\ReferenceService;
use PHPUnit\Framework\TestCase;

class ReferenceServiceTest extends TestCase
{
    private ReferenceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ReferenceService();
    }

    /** @test */
    public function it_formats_references_correctly()
    {
        $testCases = [
            [
                'metadata' => ['book' => 'Genesis', 'chapter' => '1', 'verse' => '1'],
                'expected' => 'Genesis 1:1'
            ],
            [
                'metadata' => ['libro' => 'Juan', 'capitulo' => '3', 'versiculo' => '16'],
                'expected' => 'Juan 3:16'
            ],
            [
                'metadata' => ['book' => 'Psalms', 'chapter' => '23', 'verse' => ''],
                'expected' => 'Psalms 23'
            ]
        ];

        foreach ($testCases as $case) {
            $this->assertEquals($case['expected'], $this->service->formatReference($case['metadata']));
        }
    }

    /** @test */
    public function it_parses_simple_references()
    {
        $testCases = [
            'Genesis 1:1' => [
                'book' => 'Génesis',
                'chapter' => 1,
                'verse' => 1
            ],
            'Juan 3:16' => [
                'book' => 'Juan',
                'chapter' => 3,
                'verse' => 16
            ],
            'Salmos 23' => [
                'book' => 'Salmos',
                'chapter' => 23,
                'verse' => null
            ]
        ];

        foreach ($testCases as $ref => $expected) {
            $result = $this->service->parseReference($ref);
            $this->assertEquals($expected['book'], $result['book']);
            $this->assertEquals($expected['chapter'], $result['chapter']);
            $this->assertEquals($expected['verse'], $result['verse']);
        }
    }

    /** @test */
    public function it_handles_reference_ranges() 
    {
        $result = $this->service->parseReference('Génesis 1:1-3');
        $this->assertEquals('Génesis', $result['book']);
        $this->assertEquals(1, $result['chapter']);
        $this->assertEquals(1, $result['verse']);
        $this->assertEquals(3, $result['verse_end']);
    }

    /** @test */
    public function it_converts_references_to_vector_ids()
    {
        $testCases = [
            'Génesis 1:1' => 'AT-genesis-01-001',
            'Juan 3:16' => 'NT-john-03-016',
            '1 Nefi 2:15' => 'BM-1-nefi-02-015',
            'Mosíah 5:7' => 'BM-mosiah-05-007',
            'DyC 76:22-24' => 'DC-76-022-024',
            'Moisés 1:39' => 'PGP-moses-01-039'
        ];

        foreach ($testCases as $ref => $expected) {
            $this->assertEquals($expected, $this->service->referenceToVectorId($ref));
        }
    }

    /** @test */
    public function it_expands_reference_ranges()
    {
        $verses = $this->service->expandReference('Génesis 1:1-3');
        $this->assertCount(3, $verses);
        $this->assertEquals('Génesis 1:1', $verses[0]);
        $this->assertEquals('Génesis 1:2', $verses[1]);
        $this->assertEquals('Génesis 1:3', $verses[2]);

        $verses = $this->service->expandReference('Juan 3:16');
        $this->assertCount(1, $verses);
        $this->assertEquals('Juan 3:16', $verses[0]);
    }

    /** @test */
    public function it_handles_complex_references()
    {
        $testCases = [
            '1 Juan 1:9' => [
                'book' => '1 Juan',
                'chapter' => 1,
                'verse' => 9
            ],
            'III Juan 1:2' => [
                'book' => '3 Juan',
                'chapter' => 1,
                'verse' => 2
            ],
            '1ra de Juan 1:9' => [
                'book' => '1 Juan',
                'chapter' => 1,
                'verse' => 9
            ],
            '2da de Pedro 1:5-7' => [
                'book' => '2 Pedro',
                'chapter' => 1,
                'verse' => 5,
                'verse_end' => 7
            ]
        ];

        foreach ($testCases as $ref => $expected) {
            $result = $this->service->parseReference($ref);
            $this->assertEquals($expected['book'], $result['book']);
            $this->assertEquals($expected['chapter'], $result['chapter']);
            $this->assertEquals($expected['verse'], $result['verse']);
            
            if (isset($expected['verse_end'])) {
                $this->assertEquals($expected['verse_end'], $result['verse_end']);
            }
        }
    }
}
