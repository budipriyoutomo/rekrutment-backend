<?php

namespace App\Core\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Core\Support\QueryFilter;
use Exception;

abstract class BaseService
{
    public function __construct(
        protected Model $model
    ) {}

    protected function query(): Builder
    {
        return $this->model->newQuery();
    }

    public function list(Request $request): LengthAwarePaginator
    {
        $query = $this->query();

        $query = app(QueryFilter::class)->apply($query, $request);

        return $query->paginate(
            (int) $request->get('per_page', 15)
        );
    }

    public function find($id): ?Model
    {
        return $this->query()->find($id);
    }

    public function create(array $data): Model
    {
        return $this->query()->create($data);
    }

    public function update($id, array $data): Model
    {
        $model = $this->find($id);

        if (!$model) {
            throw new Exception("Data not found");
        }

        $model->update($data);

        return $model;
    }

    public function delete($id): Model
    {
        $model = $this->find($id);

        if (!$model) {
            throw new Exception("Data not found");
        }

        $model->delete();

        return $model;
    }
}