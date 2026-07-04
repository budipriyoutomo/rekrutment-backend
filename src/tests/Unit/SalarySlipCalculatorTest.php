<?php

namespace Tests\Unit;

use App\Domains\SalarySlip\Services\SalarySlipCalculator;
use PHPUnit\Framework\TestCase;

class SalarySlipCalculatorTest extends TestCase
{
    private SalarySlipCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new SalarySlipCalculator();
    }

    public function test_consistent_totals_produce_no_warnings(): void
    {
        $row = [
            'gaji_pokok'       => 5000000,
            'tunjangan_makan'  => 500000,
            'total_penerimaan' => 5500000,
            'bpjs_kesehatan'   => 100000,
            'pph21'            => 200000,
            'total_potongan'   => 300000,
            'take_home_pay'    => 5200000,
        ];

        $this->assertSame([], $this->calc->warnings($row));
    }

    public function test_mismatched_total_penerimaan_warns(): void
    {
        $row = [
            'gaji_pokok'       => 5000000,
            'tunjangan_makan'  => 500000,
            'total_penerimaan' => 9999999, // salah
        ];

        $warnings = $this->calc->warnings($row);
        $this->assertNotEmpty($warnings);
        $this->assertStringContainsStringIgnoringCase('penerimaan', implode(' ', $warnings));
    }

    public function test_mismatched_take_home_pay_warns(): void
    {
        $row = [
            'total_penerimaan' => 5500000,
            'total_potongan'   => 300000,
            'take_home_pay'    => 1, // salah, seharusnya 5200000
        ];

        $warnings = $this->calc->warnings($row);
        $this->assertStringContainsStringIgnoringCase('take home pay', implode(' ', $warnings));
    }
}
