<?php

namespace App\Domains\MasterData\Services;

use App\Core\Services\BaseService;
use App\Domains\MasterData\Enums\MasterDataType;
use App\Domains\MasterData\Models\MasterData;
use Illuminate\Database\Eloquent\Collection;

class MasterDataService extends BaseService
{
    public function __construct(MasterData $model)
    {
        parent::__construct($model);
    }

    public function getByType(MasterDataType $type, bool $onlyActive = false): Collection
    {
        return MasterData::where('type', $type->value)
            ->when($onlyActive, fn($q) => $q->where('is_active', true))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function getAllTypes(): array
    {
        return array_map(fn(MasterDataType $t) => [
            'value' => $t->value,
            'label' => $t->label(),
        ], MasterDataType::cases());
    }

    public function createItem(array $data): MasterData
    {
        if (!isset($data['sort_order'])) {
            $data['sort_order'] = MasterData::where('type', $data['type'])->max('sort_order') + 1;
        }

        return $this->create($data);
    }

    public function updateItem(string $id, array $data): MasterData
    {
        $item = MasterData::findOrFail($id);
        $item->update($data);

        return $item->refresh();
    }

    public function deleteItem(string $id): void
    {
        MasterData::findOrFail($id)->delete();
    }

    public function toggleActive(string $id): MasterData
    {
        $item = MasterData::findOrFail($id);
        $item->update(['is_active' => !$item->is_active]);

        return $item->refresh();
    }
}
