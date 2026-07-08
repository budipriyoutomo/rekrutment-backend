<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

trait HasUserstamps
{
    /**
     * Cache keberadaan kolom userstamp per "tabel.kolom" agar tidak query
     * skema berulang kali.
     */
    protected static array $userstampColumnCache = [];

    protected static function bootHasUserstamps()
    {
        // 🔥 saat create
        static::creating(function ($model) {
            if (Auth::check()) {
                $userId = Auth::id();

                if (static::userstampHasColumn($model, 'created_by') && empty($model->created_by)) {
                    $model->created_by = $userId;
                }

                if (static::userstampHasColumn($model, 'updated_by') && empty($model->updated_by)) {
                    $model->updated_by = $userId;
                }
            }
        });

        // 🔥 saat update
        static::updating(function ($model) {
            if (Auth::check() && static::userstampHasColumn($model, 'updated_by')) {
                $model->updated_by = Auth::id();
            }
        });

        // 🔥 saat delete (soft delete)
        static::deleting(function ($model) {
            if (Auth::check()
                && method_exists($model, 'runSoftDelete')
                && static::userstampHasColumn($model, 'deleted_by')
            ) {
                $model->deleted_by = Auth::id();
                $model->saveQuietly();
            }
        });
    }

    /**
     * Apakah tabel model punya kolom userstamp tertentu. Hanya tabel yang
     * benar-benar memiliki kolomnya yang akan di-stamp; sisanya dilewati
     * (mencegah error "Undefined column" saat request terautentikasi).
     */
    protected static function userstampHasColumn($model, string $column): bool
    {
        $key = $model->getTable() . '.' . $column;

        if (!array_key_exists($key, static::$userstampColumnCache)) {
            static::$userstampColumnCache[$key] = Schema::hasColumn($model->getTable(), $column);
        }

        return static::$userstampColumnCache[$key];
    }
}
