<?php

namespace App\Core\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class QueryFilter
{
    public function apply(Builder $query, Request $request): Builder
    {
        return $query
            ->when($request->search, function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%");
            })
            ->when($request->sort, function ($q) use ($request) {
                foreach (explode(',', $request->sort) as $sort) {
                    $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
                    $field = ltrim($sort, '-');
                    $q->orderBy($field, $direction);
                }
            });
    }
}