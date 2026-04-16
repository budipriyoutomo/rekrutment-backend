<?php

namespace App\Core\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

abstract class BaseAggregateService extends BaseService
{
    protected string $itemModel;
    protected string $itemForeignKey;

    protected function afterCreate(Model $model): void {}
    protected function afterUpdate(Model $model): void {}
    protected function afterCommit(Model $model): void {}

    public function create(array $data): Model
    {
        return DB::transaction(function () use ($data) {

            $items = $data['items'] ?? [];
            unset($data['items']);

            $model = parent::create($data);

            $this->createItems($model, $items);

            $this->afterCreate($model);

            DB::afterCommit(fn() => $this->afterCommit($model));

            return $model;
        });
    }

    public function update($id, array $data): Model
    {
        return DB::transaction(function () use ($id, $data) {

            $items = $data['items'] ?? [];
            unset($data['items']);

            $model = parent::update($id, $data);

            $this->syncItems($model, $items);

            $this->afterUpdate($model);

            DB::afterCommit(fn() => $this->afterCommit($model));

            return $model;
        });
    }

    protected function createItems(Model $model, array $items): void
    {
        foreach ($items as $item) {
            $item[$this->itemForeignKey] = $model->id;
            $this->itemModel::create($item);
        }
    }

    protected function syncItems(Model $model, array $items): void
    {
        $itemModel = $this->itemModel;
        $fk = $this->itemForeignKey;

        $existingIds = $itemModel::where($fk, $model->id)->pluck('id')->toArray();

        $incomingIds = [];

        foreach ($items as $item) {
            if (!empty($item['id'])) {
                $incomingIds[] = $item['id'];
                $itemModel::find($item['id'])?->update($item);
            } else {
                $item[$fk] = $model->id;
                $itemModel::create($item);
            }
        }

        $deleteIds = array_diff($existingIds, $incomingIds);

        if (!empty($deleteIds)) {
            $itemModel::whereIn('id', $deleteIds)->delete();
        }
    }
}