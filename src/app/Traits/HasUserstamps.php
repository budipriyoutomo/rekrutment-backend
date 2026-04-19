<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;

trait HasUserstamps
{
    protected static function bootHasUserstamps()
    {
        // 🔥 saat create
        static::creating(function ($model) {
            if (Auth::check()) {
                $userId = Auth::id();

                if (empty($model->created_by)) {
                    $model->created_by = $userId;
                }

                if (empty($model->updated_by)) {
                    $model->updated_by = $userId;
                }
            }
        });

        // 🔥 saat update
        static::updating(function ($model) {
            if (Auth::check()) {
                $model->updated_by = Auth::id();
            }
        });

        // 🔥 saat delete (soft delete)
        static::deleting(function ($model) {
            if (Auth::check() && method_exists($model, 'runSoftDelete')) {
                $model->deleted_by = Auth::id();
                $model->saveQuietly();
            }
        });
    }
}