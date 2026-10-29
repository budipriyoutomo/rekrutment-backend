<?php

namespace App\Domains\MasterData\Models;

use App\Core\Models\BaseModel;
use App\Domains\MasterData\Enums\MasterDataType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MasterData extends BaseModel
{
    protected $table = 'master_data';

    public const LOCATION_POSITIONS_TABLE = 'master_data_location_positions';

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'type' => MasterDataType::class,
    ];

    /** Kota induk dari sebuah lokasi/posisi. */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** Lokasi/posisi di bawah sebuah kota. */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** Posisi yang tersedia di sebuah lokasi kerja. */
    public function positions(): BelongsToMany
    {
        return $this->belongsToMany(self::class, self::LOCATION_POSITIONS_TABLE, 'location_id', 'position_id');
    }

    /** Lokasi kerja yang membuka sebuah posisi. */
    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(self::class, self::LOCATION_POSITIONS_TABLE, 'position_id', 'location_id');
    }
}
