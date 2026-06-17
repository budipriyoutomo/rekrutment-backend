<?php

namespace Database\Seeders;

use App\Domains\MasterData\Models\MasterData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            'position' => [
                'Staff Administrasi', 'Staff Keuangan', 'Staff Akunting', 'Staff HRD',
                'Staff Marketing', 'Staff Operasional', 'Staff IT', 'Staff Gudang',
                'Supervisor', 'Manajer', 'Kasir', 'Cook / Juru Masak', 'Bartender',
                'Waiter / Waitress', 'Captain', 'Host / Hostess', 'Barista',
                'Kitchen Helper', 'Steward', 'Security', 'Driver', 'Teknisi',
                'Front Office', 'Housekeeping',
            ],
            'job_source' => [
                'Website Perusahaan', 'LinkedIn', 'Jobstreet', 'Glints', 'Kalibrr',
                'Indeed', 'Instagram', 'Facebook', 'Twitter / X', 'TikTok',
                'Referral Karyawan', 'Referral Keluarga', 'Campus Hiring',
                'Walk-in', 'Job Fair', 'Headhunter / Rekruter', 'Lainnya',
            ],
            'department' => [
                'Human Resources', 'Finance & Accounting', 'Marketing', 'Operations',
                'IT / Technology', 'Sales', 'Procurement', 'Legal', 'Management',
                'Food & Beverage', 'Kitchen', 'Service', 'Front of House', 'Security',
            ],
            'location' => [
                'Jakarta Pusat', 'Jakarta Selatan', 'Jakarta Utara', 'Jakarta Barat', 'Jakarta Timur',
                'Tangerang', 'Tangerang Selatan', 'Bekasi', 'Depok', 'Bogor',
                'Bandung', 'Surabaya', 'Yogyakarta', 'Semarang', 'Medan', 'Bali',
            ],
            'religion' => [
                'Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu',
            ],
            'marital_status' => [
                'Belum Menikah', 'Menikah', 'Cerai Hidup', 'Cerai Mati',
            ],
            'education_level' => [
                'SD', 'SMP', 'SMA / SMK', 'Diploma 1 (D1)', 'Diploma 2 (D2)',
                'Diploma 3 (D3)', 'Sarjana (S1)', 'Magister (S2)', 'Doktor (S3)',
            ],
        ];

        foreach ($data as $type => $names) {
            foreach ($names as $order => $name) {
                MasterData::firstOrCreate(
                    ['type' => $type, 'name' => $name],
                    [
                        'id'         => Str::uuid(),
                        'code'       => null,
                        'is_active'  => true,
                        'sort_order' => $order,
                    ]
                );
            }
        }
    }
}
