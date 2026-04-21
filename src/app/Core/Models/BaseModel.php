<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuid;
use App\Traits\HasUserstamps;

abstract class BaseModel extends Model
{
    use  HasUuid, HasUserstamps;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}