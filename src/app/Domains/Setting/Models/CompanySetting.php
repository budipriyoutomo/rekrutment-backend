<?php

namespace App\Domains\Setting\Models;

use Illuminate\Database\Eloquent\Model;

class CompanySetting extends Model
{
    protected $table      = 'company_settings';
    protected $primaryKey = 'key';
    public    $incrementing = false;
    protected $keyType    = 'string';

    protected $fillable = ['key', 'value'];

    public static function get(string $key, mixed $default = null): mixed
    {
        return static::find($key)?->value ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    public static function allAsMap(): array
    {
        return static::all()->pluck('value', 'key')->toArray();
    }
}
