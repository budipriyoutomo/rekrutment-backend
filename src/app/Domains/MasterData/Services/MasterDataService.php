<?php

namespace App\Domains\MasterData\Services;

use App\Core\Services\BaseService;
use App\Core\Support\CityPrefix;
use App\Domains\MasterData\Enums\MasterDataType;
use App\Domains\MasterData\Models\MasterData;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class MasterDataService extends BaseService
{
    /** Tipe yang berada di bawah kota dan bernama "Kota - Nama". */
    private const CITY_CHILD_TYPES = [MasterDataType::LOCATION, MasterDataType::POSITION];

    public function __construct(MasterData $model)
    {
        parent::__construct($model);
    }

    public function getByType(MasterDataType $type, bool $onlyActive = false): Collection
    {
        return MasterData::where('type', $type->value)
            ->when($onlyActive, fn ($q) => $q->where('is_active', true))
            ->when(in_array($type, self::CITY_CHILD_TYPES, true), fn ($q) => $q->with('parent:id,name'))
            ->when($type === MasterDataType::LOCATION, fn ($q) => $q->withCount('positions'))
            ->when($type === MasterDataType::POSITION, fn ($q) => $q->withCount('locations'))
            ->when($type === MasterDataType::CITY, fn ($q) => $q->withCount('children'))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function getAllTypes(): array
    {
        return array_map(fn (MasterDataType $t) => [
            'value' => $t->value,
            'label' => $t->label(),
        ], MasterDataType::cases());
    }

    public function createItem(array $data): MasterData
    {
        $data = $this->normalizeCityChild($data);

        if (! isset($data['sort_order'])) {
            $data['sort_order'] = MasterData::where('type', $data['type'])->max('sort_order') + 1;
        }

        return $this->create($data);
    }

    public function updateItem(string $id, array $data): MasterData
    {
        $item = MasterData::findOrFail($id);
        $item->update($this->normalizeCityChild($data));

        return $item->refresh();
    }

    public function deleteItem(string $id): void
    {
        $item = MasterData::findOrFail($id);

        if ($item->type === MasterDataType::CITY && $item->children()->exists()) {
            throw ValidationException::withMessages([
                'id' => 'Kota masih dipakai lokasi/posisi. Pindahkan atau nonaktifkan kota ini saja.',
            ]);
        }

        $item->delete();
    }

    public function toggleActive(string $id): MasterData
    {
        $item = MasterData::findOrFail($id);
        $item->update(['is_active' => ! $item->is_active]);

        return $item->refresh();
    }

    /**
     * Pilihan bertingkat untuk /apply: kota → lokasi → posisi. Hanya item aktif;
     * lokasi tanpa posisi ter-mapping dan kota tanpa lokasi tidak ditampilkan
     * supaya pelamar tidak buntu. `label` = nama tanpa prefix kota, `name` =
     * nilai lengkap yang disimpan pada lamaran.
     */
    public function applyOptions(): array
    {
        $cities = MasterData::where('type', MasterDataType::CITY->value)
            ->where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')
            ->get(['id', 'name']);

        $locations = MasterData::where('type', MasterDataType::LOCATION->value)
            ->where('is_active', true)
            ->whereIn('parent_id', $cities->pluck('id'))
            ->with(['positions' => fn ($q) => $q->where('is_active', true)
                ->orderBy('sort_order')->orderBy('name')
                ->select('master_data.id', 'master_data.name')])
            ->orderBy('sort_order')->orderBy('name')
            ->get(['id', 'name', 'parent_id'])
            ->groupBy('parent_id');

        $tree = [];
        foreach ($cities as $city) {
            $cityLocations = [];
            foreach ($locations->get($city->id, []) as $location) {
                if ($location->positions->isEmpty()) {
                    continue;
                }
                $cityLocations[] = [
                    'id' => $location->id,
                    'name' => $location->name,
                    'label' => $this->stripCity($location->name, $city->name),
                    'positions' => $location->positions->map(fn (MasterData $p) => [
                        'id' => $p->id,
                        'name' => $p->name,
                        'label' => $this->stripCity($p->name, $city->name),
                    ])->values()->all(),
                ];
            }

            if ($cityLocations !== []) {
                $tree[] = ['id' => $city->id, 'name' => $city->name, 'locations' => $cityLocations];
            }
        }

        return $tree;
    }

    public function locationPositions(string $locationId): Collection
    {
        return $this->findLocation($locationId)->positions()
            ->orderBy('sort_order')->orderBy('name')
            ->get();
    }

    /**
     * Ganti seluruh mapping posisi sebuah lokasi. Posisi harus sekota dengan
     * lokasinya, atau tanpa kota (berlaku untuk semua kota).
     *
     * @param  string[]  $positionIds
     */
    public function syncLocationPositions(string $locationId, array $positionIds): Collection
    {
        $location = $this->findLocation($locationId);
        $positionIds = array_values(array_unique($positionIds));

        $invalid = MasterData::whereIn('id', $positionIds)
            ->where(fn ($q) => $q->where('type', '!=', MasterDataType::POSITION->value)
                ->orWhere(fn ($q) => $q->whereNotNull('parent_id')->where('parent_id', '!=', $location->parent_id)))
            ->pluck('name');

        if ($invalid->isNotEmpty()) {
            throw ValidationException::withMessages([
                'position_ids' => 'Posisi harus sekota dengan lokasi: '.$invalid->implode(', '),
            ]);
        }

        $location->positions()->sync($positionIds);

        return $this->locationPositions($locationId);
    }

    private function findLocation(string $id): MasterData
    {
        return MasterData::where('type', MasterDataType::LOCATION->value)->findOrFail($id);
    }

    /**
     * Lokasi/posisi dengan kota: nama disimpan berprefix kota ("Bandung - Cook")
     * sesuai konvensi nilai lamaran. Prefix kota lama (bila kota diganti) diganti,
     * selain itu prefix ditambahkan. Tipe lain tidak punya kota.
     */
    private function normalizeCityChild(array $data): array
    {
        $type = MasterDataType::tryFrom($data['type'] ?? '');

        if (! in_array($type, self::CITY_CHILD_TYPES, true)) {
            $data['parent_id'] = null;

            return $data;
        }

        if (! array_key_exists('parent_id', $data)) {
            return $data;
        }

        if ($data['parent_id'] === null) {
            return $data;
        }

        $city = MasterData::findOrFail($data['parent_id'])->name;
        $data['name'] = $city.' - '.$this->stripAnyCity(trim($data['name']));

        return $data;
    }

    private function stripCity(string $name, string $city): string
    {
        $prefix = CityPrefix::of($name);

        return $prefix !== null && mb_strtolower($prefix) === mb_strtolower($city)
            ? trim(mb_substr($name, mb_strpos($name, ' - ') + 3))
            : $name;
    }

    /** Buang prefix bila berupa nama kota yang terdaftar. */
    private function stripAnyCity(string $name): string
    {
        $prefix = CityPrefix::of($name);

        if ($prefix === null) {
            return $name;
        }

        $isCity = MasterData::where('type', MasterDataType::CITY->value)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($prefix)])
            ->exists();

        return $isCity ? $this->stripCity($name, $prefix) : $name;
    }
}
