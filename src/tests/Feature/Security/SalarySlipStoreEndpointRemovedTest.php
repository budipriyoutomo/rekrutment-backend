<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * POST /api/salary-slips (input satu slip manual) dihapus: tidak dipakai frontend
 * dan sebelumnya fatal error (StoreSalarySlipRequest tidak lengkap). Slip gaji
 * hanya masuk lewat import Excel.
 */
class SalarySlipStoreEndpointRemovedTest extends TestCase
{
    use RefreshDatabase;

    public function test_single_salary_slip_store_endpoint_no_longer_exists(): void
    {
        $this->actingAsUserWithPermissions(['salary-slips']);

        $this->postJson('/api/salary-slips', [
            'nik' => '1001',
            'nama' => 'Budi',
            'periode' => '2026-05',
        ])->assertStatus(405);

        $this->assertDatabaseCount('salary_slips', 0);
    }

    public function test_listing_salary_slips_still_works(): void
    {
        $this->actingAsUserWithPermissions(['salary-slips']);

        $this->getJson('/api/salary-slips')->assertStatus(200);
    }
}
