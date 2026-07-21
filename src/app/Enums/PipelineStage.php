<?php

namespace App\Enums;

/**
 * Tahapan pipeline rekrutmen pada kolom applications.stage.
 * Menjadi sumber tunggal daftar stage di backend.
 */
enum PipelineStage: string
{
    case APPLIED            = 'applied';
    case SCREENING          = 'screening';
    case PROFILE_COMPLETION = 'profile_completion';
    case ASSESSMENT         = 'assessment';
    case INTERVIEW          = 'interview';
    case OFFER              = 'offer';
    case HIRED              = 'hired';
    case REJECTED           = 'rejected';
    case ON_HOLD            = 'on_hold';

    /**
     * Semua nilai stage sebagai array string.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
