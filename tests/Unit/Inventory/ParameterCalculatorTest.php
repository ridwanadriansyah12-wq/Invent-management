<?php

namespace Tests\Unit\Inventory;

use App\Services\Inventory\ParameterCalculator;
use InvalidArgumentException;
use Tests\TestCase;

class ParameterCalculatorTest extends TestCase
{
    protected ParameterCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new ParameterCalculator();
    }

    /**
     * Test 6: Rumus SS/ROP/MAX diuji dengan angka manual (LT = 15, sigma_LT = 0, Kelas A).
     */
    public function test_manual_calculation_class_a(): void
    {
        // mu_daily = 10, sigma_daily = 2, LT = 15, sigma_LT = 0, Kelas A (Z ≈ 2.0537, Cover = 14)
        // variance = 15 * 4 + 100 * 0 = 60
        // sqrt(60) ≈ 7.7459667
        // SS = ceil(2.0537489 * 7.7459667) = ceil(15.908) = 16
        // ROP = ceil(10 * 15 + 16) = 166
        // Q_target = 10 * 14 = 140
        // MAX = ceil(166 + 140) = 306

        $result = $this->calculator->calculate(
            muDaily: 10.0,
            sigmaDaily: 2.0,
            leadTimeDays: 15.0,
            leadTimeStdDays: 0.0,
            abcClass: 'A'
        );

        $this->assertEquals(16, $result['proposed_ss']);
        $this->assertEquals(166, $result['proposed_rop']);
        $this->assertEquals(306, $result['proposed_max']);
        $this->assertEquals(140.0, $result['q_target']);
        $this->assertGreaterThanOrEqual(0, $result['proposed_ss']);
    }

    /**
     * Test 6: Rumus SS/ROP/MAX diuji dengan angka manual (LT = 15, sigma_LT = 0, Kelas C).
     */
    public function test_manual_calculation_class_c(): void
    {
        // Kelas C: Z ≈ 1.28155, Cover = 30
        // SS = ceil(1.28155 * sqrt(60)) = ceil(9.9268) = 10
        // ROP = 10 * 15 + 10 = 160
        // Q_target = 10 * 30 = 300
        // MAX = 160 + 300 = 460

        $result = $this->calculator->calculate(
            muDaily: 10.0,
            sigmaDaily: 2.0,
            leadTimeDays: 15.0,
            leadTimeStdDays: 0.0,
            abcClass: 'C'
        );

        $this->assertEquals(10, $result['proposed_ss']);
        $this->assertEquals(160, $result['proposed_rop']);
        $this->assertEquals(460, $result['proposed_max']);
        $this->assertEquals(300.0, $result['q_target']);
    }

    /**
     * Test dengan deviasi lead time pemasok (sigma_LT > 0).
     */
    public function test_manual_calculation_with_lead_time_variability(): void
    {
        // LT = 15, sigma_LT = 2.0, mu = 10, sigma = 2
        // variance = (15 * 4) + (100 * 4) = 60 + 400 = 460
        // sqrt(460) ≈ 21.44761
        // SS = ceil(1.2815516 * 21.44761) = ceil(27.486) = 28
        // ROP = 150 + 28 = 178
        // MAX = 178 + 300 = 478

        $result = $this->calculator->calculate(
            muDaily: 10.0,
            sigmaDaily: 2.0,
            leadTimeDays: 15.0,
            leadTimeStdDays: 2.0,
            abcClass: 'C'
        );

        $this->assertEquals(28, $result['proposed_ss']);
        $this->assertEquals(178, $result['proposed_rop']);
        $this->assertEquals(478, $result['proposed_max']);
    }

    /**
     * Test validasi input: nilai negatif memicu InvalidArgumentException.
     */
    public function test_negative_values_throw_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calculator->calculate(
            muDaily: -5.0,
            sigmaDaily: 2.0,
            leadTimeDays: 15.0
        );
    }
}
