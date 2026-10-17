<?php

namespace Tests\Feature;

use App\Domains\Interviewer\Models\Interviewer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FixInterviewerPhonesTest extends TestCase
{
    use RefreshDatabase;

    private function make(?string $phone): Interviewer
    {
        return Interviewer::create(['name' => 'X', 'role' => 'HR', 'phone' => $phone]);
    }

    public function test_dry_run_does_not_change_data(): void
    {
        $i = $this->make('8123456789');

        $this->artisan('interviewers:fix-phones')->assertSuccessful();

        $this->assertSame('8123456789', $i->fresh()->phone);
    }

    public function test_apply_restores_leading_zero_only_for_broken_numbers(): void
    {
        $broken = $this->make('8123456789');
        $ok = $this->make('08123456789');
        $intl = $this->make('628123456789');
        $plus = $this->make('+62 812-3456-789');
        $short = $this->make('812');
        $null = $this->make(null);

        $this->artisan('interviewers:fix-phones --apply')->assertSuccessful();

        $this->assertSame('08123456789', $broken->fresh()->phone);
        $this->assertSame('08123456789', $ok->fresh()->phone);
        $this->assertSame('628123456789', $intl->fresh()->phone);
        $this->assertSame('+62 812-3456-789', $plus->fresh()->phone);
        $this->assertSame('812', $short->fresh()->phone);
        $this->assertNull($null->fresh()->phone);
    }

    public function test_apply_is_idempotent(): void
    {
        $i = $this->make('8123456789');

        $this->artisan('interviewers:fix-phones --apply');
        $this->artisan('interviewers:fix-phones --apply');

        $this->assertSame('08123456789', $i->fresh()->phone);
    }
}
