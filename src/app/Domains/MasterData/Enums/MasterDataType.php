<?php

namespace App\Domains\MasterData\Enums;

enum MasterDataType: string
{
    case POSITION        = 'position';
    case JOB_SOURCE      = 'job_source';
    case DEPARTMENT      = 'department';
    case LOCATION        = 'location';
    case RELIGION        = 'religion';
    case MARITAL_STATUS  = 'marital_status';
    case EDUCATION_LEVEL = 'education_level';

    public function label(): string
    {
        return match($this) {
            self::POSITION        => 'Posisi yang Dilamar',
            self::JOB_SOURCE      => 'Sumber Lowongan',
            self::DEPARTMENT      => 'Departemen',
            self::LOCATION        => 'Lokasi',
            self::RELIGION        => 'Agama',
            self::MARITAL_STATUS  => 'Status Perkawinan',
            self::EDUCATION_LEVEL => 'Jenjang Pendidikan',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
