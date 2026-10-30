<?php

namespace App\Domains\MasterData\Services;

use App\Core\Services\BaseService;
use App\Core\Support\CityPrefix;
use App\Domains\MasterData\Enums\MasterDataType;
use App\Domains\MasterData\Models\MasterData;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
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
            ->when($type === MasterDataType::POSITION, fn ($q) => $q->withCount('locations')
                ->with(['locations' => fn ($l) => $l->orderBy('sort_order')->orderBy('name')
                    ->select('master_data.id', 'master_data.name', 'master_data.parent_id', 'master_data.is_active')]))
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
        [$data, $locationIds] = $this->pullLocationIds($data);
        $data = $this->normalizeCityChild($data);

        if (! isset($data['sort_order'])) {
            $data['sort_order'] = MasterData::where('type', $data['type'])->max('sort_order') + 1;
        }

        return DB::transaction(function () use ($data, $locationIds) {
            $item = $this->create($data);
            if ($locationIds !== null) {
                $this->syncPositionLocations($item, $locationIds);
            }

            return $item;
        });
    }

    public function updateItem(string $id, array $data): MasterData
    {
        $item = MasterData::findOrFail($id);
        [$data, $locationIds] = $this->pullLocationIds($data);

        return DB::transaction(function () use ($item, $data, $locationIds) {
            $item->update($this->normalizeCityChild($data));

            if ($locationIds !== null) {
                $this->syncPositionLocations($item, $locationIds);
            } elseif ($item->type === MasterDataType::POSITION && $item->wasChanged('parent_id') && $item->parent_id) {
                // Kota posisi diganti tanpa daftar lokasi baru: lepas lokasi kota lain.
                $item->locations()->detach(
                    $item->locations()->where('master_data.parent_id', '!=', $item->parent_id)->pluck('master_data.id')
                );
            }

            return $item->refresh();
        });
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

    /** @return array{0: array, 1: string[]|null} data tanpa `location_ids`, dan daftar lokasi bila dikirim untuk posisi */
    private function pullLocationIds(array $data): array
    {
        $ids = $data['location_ids'] ?? null;
        unset($data['location_ids']);

        $isPosition = ($data['type'] ?? null) === MasterDataType::POSITION->value;

        return [$data, $isPosition && is_array($ids) ? array_values(array_unique($ids)) : null];
    }

    /**
     * Ganti seluruh lokasi kerja tempat posisi aktif. Lokasi harus sekota dengan
     * posisinya; posisi tanpa kota (umum) boleh di lokasi kota mana pun.
     *
     * @param  string[]  $locationIds
     */
    private function syncPositionLocations(MasterData $position, array $locationIds): void
    {
        $invalid = MasterData::whereIn('id', $locationIds)
            ->where(fn ($q) => $q->where('type', '!=', MasterDataType::LOCATION->value)
                ->when($position->parent_id, fn ($q) => $q->orWhere(fn ($q) => $q->whereNull('parent_id')
                    ->orWhere('parent_id', '!=', $position->parent_id))))
            ->pluck('name');

        $missing = count($locationIds) - MasterData::whereIn('id', $locationIds)->count();

        if ($invalid->isNotEmpty() || $missing > 0) {
            throw ValidationException::withMessages([
                'location_ids' => $invalid->isNotEmpty()
                    ? 'Lokasi harus sekota dengan posisi: '.$invalid->implode(', ')
                    : 'Lokasi tidak ditemukan.',
            ]);
        }

        $position->locations()->sync($locationIds);
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
