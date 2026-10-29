<?php

namespace App\Domains\MasterData\Actions;

use App\Core\Support\CityPrefix;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Lengkapi hierarki kota dari konvensi nama "Kota - Nama" tanpa menghapus apa pun:
 *  1. buat item `city` untuk setiap prefix kota yang belum punya item kota,
 *  2. isi parent_id lokasi/posisi yang masih kosong sesuai prefix namanya,
 *  3. lokasi yang belum punya mapping sama sekali di-mapping ke semua posisi
 *     sekotanya (perilaku lama: semua posisi sekota tersedia di semua lokasi).
 *
 * Idempoten — aman dipanggil ulang dari migration maupun seeder. Mapping yang
 * sudah dirapikan HR tidak disentuh; untuk menyembunyikan lokasi gunakan
 * nonaktifkan, bukan mengosongkan mapping.
 *
 * Memakai query builder (bukan model) supaya tetap stabil dipanggil dari migration.
 */
class SyncCityHierarchyAction
{
    private const TABLE = 'master_data';

    private const PIVOT = 'master_data_location_positions';

    /** @return array{cities_created: int, parents_set: int, mappings_created: int, without_city: int} */
    public function execute(): array
    {
        return DB::transaction(function () {
            $citiesCreated = $this->createMissingCities();
            $cityIds = $this->cityIdsByName();

            [$parentsSet, $withoutCity] = $this->fillParents($cityIds);
            $mappingsCreated = $this->mapUnmappedLocations();

            return [
                'cities_created' => $citiesCreated,
                'parents_set' => $parentsSet,
                'mappings_created' => $mappingsCreated,
                'without_city' => $withoutCity,
            ];
        });
    }

    private function createMissingCities(): int
    {
        $existing = array_keys($this->cityIdsByName());
        $order = (int) DB::table(self::TABLE)->where('type', 'city')->max('sort_order');
        $now = now();
        $created = 0;

        $names = DB::table(self::TABLE)
            ->whereIn('type', ['location', 'position'])
            ->orderBy('type')
            ->orderBy('sort_order')
            ->pluck('name');

        foreach ($names as $name) {
            $city = CityPrefix::of($name);
            if ($city === null || $city === '' || in_array(mb_strtolower($city), $existing, true)) {
                continue;
            }

            DB::table(self::TABLE)->insert([
                'id' => (string) Str::uuid(),
                'type' => 'city',
                'name' => $city,
                'code' => null,
                'is_active' => true,
                'sort_order' => ++$order,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $existing[] = mb_strtolower($city);
            $created++;
        }

        return $created;
    }

    /** @return array<string, string> nama kota (lowercase) → id */
    private function cityIdsByName(): array
    {
        return DB::table(self::TABLE)
            ->where('type', 'city')
            ->orderBy('sort_order')
            ->get(['id', 'name'])
            ->reverse() // bila ada nama kembar, yang sort_order terkecil menang
            ->mapWithKeys(fn ($c) => [mb_strtolower($c->name) => $c->id])
            ->all();
    }

    /** @return array{0: int, 1: int} [jumlah parent diisi, jumlah item tanpa prefix kota] */
    private function fillParents(array $cityIds): array
    {
        $set = 0;
        $withoutCity = 0;

        $items = DB::table(self::TABLE)
            ->whereIn('type', ['location', 'position'])
            ->whereNull('parent_id')
            ->get(['id', 'name']);

        foreach ($items as $item) {
            $city = CityPrefix::of($item->name);
            $cityId = $city === null ? null : ($cityIds[mb_strtolower($city)] ?? null);

            if ($cityId === null) {
                $withoutCity++;

                continue;
            }

            DB::table(self::TABLE)->where('id', $item->id)->update(['parent_id' => $cityId]);
            $set++;
        }

        return [$set, $withoutCity];
    }

    private function mapUnmappedLocations(): int
    {
        $mapped = DB::table(self::PIVOT)->distinct()->pluck('location_id')->all();

        $locations = DB::table(self::TABLE)
            ->where('type', 'location')
            ->whereNotNull('parent_id')
            ->whereNotIn('id', $mapped)
            ->get(['id', 'parent_id']);

        $positionsByCity = DB::table(self::TABLE)
            ->where('type', 'position')
            ->whereNotNull('parent_id')
            ->get(['id', 'parent_id'])
            ->groupBy('parent_id');

        $rows = [];
        foreach ($locations as $location) {
            foreach ($positionsByCity->get($location->parent_id, collect()) as $position) {
                $rows[] = ['location_id' => $location->id, 'position_id' => $position->id];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table(self::PIVOT)->insertOrIgnore($chunk);
        }

        return count($rows);
    }
}
