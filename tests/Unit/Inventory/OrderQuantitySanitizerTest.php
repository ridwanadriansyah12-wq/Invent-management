<?php

namespace Tests\Unit\Inventory;

use App\Services\Inventory\OrderQuantitySanitizer;
use Tests\TestCase;

class OrderQuantitySanitizerTest extends TestCase
{
    protected OrderQuantitySanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sanitizer = new OrderQuantitySanitizer();
    }

    /**
     * Test 3: Spesifikasi Sanitizer contoh MOQ=50, lot=12:
     * - Q = 10  -> 50
     * - Q = 60  -> 60
     * - Q = 61  -> 72
     * - Q = 130 -> 132
     */
    public function test_sanitizer_spec_examples(): void
    {
        $moq = 50.0;
        $lotSize = 12.0;

        $this->assertEquals(50, $this->sanitizer->sanitize(10.0, $moq, $lotSize));
        $this->assertEquals(60, $this->sanitizer->sanitize(60.0, $moq, $lotSize));
        $this->assertEquals(72, $this->sanitizer->sanitize(61.0, $moq, $lotSize));
        $this->assertEquals(132, $this->sanitizer->sanitize(130.0, $moq, $lotSize));
    }

    /**
     * Test jika q_raw <= 0, sanitizer mengembalikan MOQ.
     */
    public function test_sanitizer_zero_or_negative_returns_moq(): void
    {
        $this->assertEquals(50, $this->sanitizer->sanitize(0.0, 50.0, 10.0));
        $this->assertEquals(50, $this->sanitizer->sanitize(-15.0, 50.0, 10.0));
    }

    /**
     * Test dengan lot_size = 1 (item satuan).
     */
    public function test_sanitizer_unit_lot_size(): void
    {
        $this->assertEquals(20, $this->sanitizer->sanitize(15.0, 20.0, 1.0));
        $this->assertEquals(25, $this->sanitizer->sanitize(25.0, 20.0, 1.0));
    }
}
