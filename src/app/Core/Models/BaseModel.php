<?php

namespace App\Core\Models;

use App\Traits\HasUserstamps;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

abstract class BaseModel extends Model
{
    use HasUserstamps, HasUuid;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
