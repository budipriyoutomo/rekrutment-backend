<?php

namespace Tests\Unit;

use App\Domains\Application\Jobs\ProcessApplicationBundlingJob;
use App\Domains\SalarySlip\Jobs\SendSalarySlipJob;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use Tests\TestCase;

/**
 * Job berat (konversi LibreOffice: payslip 120 dtk, CV 60 dtk per file) harus
 * selesai sebelum worker membunuhnya (--timeout) dan sebelum queue database
 * menganggapnya macet lalu menjalankannya dua kali (retry_after).
 */
class QueueTimeoutConfigTest extends TestCase
{
    /**
     * @return array<string, array{class-string}>
     */
    public static function heavyJobs(): array
    {
        return [
            'send salary slip' => [SendSalarySlipJob::class],
            'bundle application' => [ProcessApplicationBundlingJob::class],
        ];
    }

    #[DataProvider('heavyJobs')]
    public function test_heavy_job_timeout_covers_pdf_conversion_and_is_below_retry_after(string $jobClass): void
    {
        $timeout = (new ReflectionClass($jobClass))->getProperty('timeout')->getDefaultValue();

        $this->assertIsInt($timeout, "{$jobClass} wajib mendefinisikan \$timeout");
        $this->assertGreaterThan(120, $timeout, 'Harus lebih lama dari batas konversi LibreOffice (120 dtk)');
        $this->assertLessThan(config('queue.connections.database.retry_after'), $timeout);
    }

    public function test_worker_timeout_is_between_job_timeout_and_retry_after(): void
    {
        $conf = base_path('../docker/supervisord.conf');
        if (! is_file($conf)) {
            $this->markTestSkipped('docker/supervisord.conf tidak tersedia di lingkungan ini.');
        }

        preg_match('/queue:work[^\n]*--timeout=(\d+)/', file_get_contents($conf), $m);
        $this->assertNotEmpty($m, 'queue:work wajib memakai --timeout eksplisit');

        $workerTimeout = (int) $m[1];
        $retryAfter = config('queue.connections.database.retry_after');

        $this->assertLessThan($retryAfter, $workerTimeout);
        foreach (self::heavyJobs() as [$jobClass]) {
            $jobTimeout = (new ReflectionClass($jobClass))->getProperty('timeout')->getDefaultValue();
            $this->assertGreaterThanOrEqual($jobTimeout, $workerTimeout);
        }
    }
}
