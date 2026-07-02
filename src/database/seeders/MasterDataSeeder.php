<?php

namespace Database\Seeders;

use App\Domains\MasterData\Models\MasterData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MasterDataSeeder extends Seeder
{
    /**
     * Nilai position/location memakai prefix kota ("Bandung - Cook") karena
     * frontend mengelompokkan dropdown per kota dan menyimpan nilai lengkap
     * berprefix pada data pelamar. Prefix di-split pada " - " pertama,
     * sehingga nama outlet yang mengandung " - " tetap aman.
     */
    public function run(): void
    {
        $positionsBandung = [
            'Sales Executive', 'Marketing Manager', 'Digital Marketing', 'Creative Marketing',
            'Stock Keeper', 'Manager Restaurant', 'Supervisor Restaurant', 'Head Chef',
            'Chef de Partie', 'Chef de Partie Pastry', 'Cook', 'Cook Pastry',
            'Cook Helper', 'Cook Helper Pastry', 'Steward', 'Barista',
            'Server', 'Runner', 'Busher', 'Host/Greeter', 'Cashier',
        ];

        $positionsJakarta = [
            'Artisan Gelato', 'Digital Marketing', 'Marketing Communication', 'Marketing Manager',
            'Driver', 'Busher', 'Runner', 'Host/Greeter', 'Server', 'Captain',
            'Restaurant Manager', 'Restaurant Supervisor', 'Steward', 'Cook',
            'Cook Helper', 'Cook Pastry', 'Chef de Partie', 'Head Chef',
            'Barista', 'Head Barista', 'Stock Keeper',
        ];

        $locationsJakarta = [
            'HEAD OFFICE JAKARTA',
            'CENTRAL KITCHEN (TANGERANG)',
            'BABY DUTCH PANCAKE ONE SATRIO',
            'BABY DUTCH PANCAKE - GRAND INDONESIA',
            'BABY DUTCH PANCAKE - CENTRAL PARK 2',
            "NANNY'S PAVILLON - KOTA KASABLANKA",
            "NANNY'S PAVILLON - AEON TANJUNG BARAT",
            "NANNY'S PAVILLON - CILANDAK TOWNSQUARE",
            "NANNY'S PAVILLON - CENTRAL PARK",
            "NANNY'S PAVILLON - AEON SENTUL",
            "NANNY'S PAVILLON - AEON BSD",
            "NANNY'S PAVILLON - LIPPO MALL PURI",
            "NANNY'S PAVILLON - AEON DELTAMAS",
            'NYONYA PAVILLON - PONDOK INDAH MALL',
            'NYONYA PAVILLON - GANDARIA CITY',
            'KARNIVOR - KOTA KASABLANKA',
            'KARNIVOR - GRAND INDONESIA',
            'KARNIVOR - AEON MALL TANJUNG BARAT',
            'KARNIVOR - LA TERRAZZA SUMMARECON MALL BEKASI',
            'KARNIVOR - PAKUWON MALL BEKASI',
        ];

        $locationsBandung = [
            'HEAD OFFICE BANDUNG - GEDEBAGE',
            'CENTRAL KITCHEN - GEDEBAGE',
            'WAREHOUSE - GEDEBAGE',
            'POJOK TILU TILU - MEKAR MULYA (GEDEBAGE)',
            'KOTA BAHRU - KOTA BARU PARAHYANGAN',
            'KARNIVOR - KOTA BARU PARAHYANGAN',
            'KARNIVOR - JL. RIAU',
            'ANYTIAM - JL. RIAU',
            "NANNY'S PAVILLON - CIUMBULEUIT",
            'BALARASA - CIUMBULEUIT',
        ];

        $prefix = fn (string $city, array $names) => array_map(
            fn (string $name) => "{$city} - {$name}",
            $names
        );

        $data = [
            'position' => [
                ...$prefix('Bandung', $positionsBandung),
                ...$prefix('Jakarta', $positionsJakarta),
            ],
            'job_source' => [
                'Job Portal', 'LinkedIn', 'Instagram', 'Facebook',
                'Referral / Teman', 'Website Perusahaan', 'Walk-in', 'Lainnya',
            ],
            'department' => [
                'Human Resources', 'Finance & Accounting', 'Marketing', 'Operations',
                'IT / Technology', 'Sales', 'Procurement', 'Legal', 'Management',
                'Food & Beverage', 'Kitchen', 'Service', 'Front of House', 'Security',
            ],
            'location' => [
                ...$prefix('Jakarta', $locationsJakarta),
                ...$prefix('Bandung', $locationsBandung),
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

        $this->removeSupersededSeedValues();

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

    /**
     * Hapus nilai generik dari seeder versi lama yang kini digantikan nilai
     * riil dari form. Hanya exact match daftar lama yang dihapus — entri yang
     * ditambahkan HR lewat halaman Master Data tidak tersentuh.
     */
    private function removeSupersededSeedValues(): void
    {
        $superseded = [
            'position' => [
                'Staff Administrasi', 'Staff Keuangan', 'Staff Akunting', 'Staff HRD',
                'Staff Marketing', 'Staff Operasional', 'Staff IT', 'Staff Gudang',
                'Supervisor', 'Manajer', 'Kasir', 'Cook / Juru Masak', 'Bartender',
                'Waiter / Waitress', 'Captain', 'Host / Hostess', 'Barista',
                'Kitchen Helper', 'Steward', 'Security', 'Driver', 'Teknisi',
                'Front Office', 'Housekeeping',
            ],
            'job_source' => [
                'Jobstreet', 'Glints', 'Kalibrr', 'Indeed', 'Twitter / X', 'TikTok',
                'Referral Karyawan', 'Referral Keluarga', 'Campus Hiring',
                'Job Fair', 'Headhunter / Rekruter',
            ],
            'location' => [
                'Jakarta Pusat', 'Jakarta Selatan', 'Jakarta Utara', 'Jakarta Barat', 'Jakarta Timur',
                'Tangerang', 'Tangerang Selatan', 'Bekasi', 'Depok', 'Bogor',
                'Bandung', 'Surabaya', 'Yogyakarta', 'Semarang', 'Medan', 'Bali',
            ],
        ];

        foreach ($superseded as $type => $names) {
            MasterData::where('type', $type)->whereIn('name', $names)->delete();
        }
    }
}
