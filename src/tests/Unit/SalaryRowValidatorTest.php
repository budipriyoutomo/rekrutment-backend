<?php

namespace Tests\Unit;

use App\Domains\SalarySlip\Validators\SalaryRowValidator;
use PHPUnit\Framework\TestCase;

class SalaryRowValidatorTest extends TestCase
{
    private SalaryRowValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new SalaryRowValidator();
    }

    private function validRow(array $override = []): array
    {
        return array_merge([
            'nik'        => '12345',
            'nama'       => 'Budi',
            'jabatan'    => 'Staff',
            'periode'    => '2026-06',
            'cabang'     => 'Jakarta',
            'perusahaan' => 'PT Maju',
            'email'      => 'budi@example.com',
            'gaji_pokok' => '5000000',
            'hk_hari'    => '22',
        ], $override);
    }

    public function test_valid_row_has_no_errors(): void
    {
        $this->assertSame([], $this->validator->validate($this->validRow()));
    }

    public function test_missing_required_field_flagged(): void
    {
        $errors = $this->validator->validate($this->validRow(['nik' => '']));
        $this->assertNotEmpty($errors);
        $this->assertStringContainsStringIgnoringCase('nik', implode(' ', $errors));
    }

    public function test_invalid_email_flagged(): void
    {
        $errors = $this->validator->validate($this->validRow(['email' => 'bukan-email']));
        $this->assertStringContainsStringIgnoringCase('email', implode(' ', $errors));
    }

    public function test_invalid_periode_flagged(): void
    {
        foreach (['2026/06', '2026-13', '06-2026', 'abcd-ef'] as $bad) {
            $errors = $this->validator->validate($this->validRow(['periode' => $bad]));
            $this->assertNotEmpty($errors, "Periode `{$bad}` seharusnya invalid");
            $this->assertStringContainsStringIgnoringCase('periode', implode(' ', $errors));
        }
    }

    public function test_non_numeric_money_flagged(): void
    {
        $errors = $this->validator->validate($this->validRow(['gaji_pokok' => 'Rp5.000.000']));
        $this->assertStringContainsStringIgnoringCase('angka', implode(' ', $errors));
    }

    public function test_empty_optional_numeric_is_allowed(): void
    {
        $this->assertSame([], $this->validator->validate($this->validRow(['gaji_pokok' => '', 'lembur' => null])));
    }
}
