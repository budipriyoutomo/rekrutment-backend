<?php

namespace App\Domains\MasterData\Models;

use App\Core\Models\BaseModel;
use App\Domains\MasterData\Enums\MasterDataType;

class MasterData extends BaseModel
{
    protected $table = 'master_data';

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
        'type'       => MasterDataType::class,
    ];
}
