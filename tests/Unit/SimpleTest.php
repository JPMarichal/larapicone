<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SimpleTest extends TestCase
{
    #[Test]
    public function it_should_pass()
    {
        $this->assertTrue(true);
    }
}
