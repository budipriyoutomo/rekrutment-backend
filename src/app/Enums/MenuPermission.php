<?php

namespace App\Enums;

/**
 * Daftar key menu yang bisa diberikan sebagai izin per-user.
 * Menjadi sumber tunggal validasi izin di backend.
 */
enum MenuPermission: string
{
    case DASHBOARD    = 'dashboard';
    case APPLICANTS   = 'applicants';
    case CANDIDATES   = 'candidates';
    case PIPELINE     = 'pipeline';
    case ASSESSMENTS  = 'assessments';
    case INTERVIEWS   = 'interviews';
    case INTERVIEWERS = 'interviewers';
    case DOCUMENTS    = 'documents';
    case EVALUATIONS  = 'evaluations';
    case VACANCIES    = 'vacancies';
    case JOB_REQUESTS = 'job-requests';
    case ANALYTICS    = 'analytics';
    case SALARY_SLIPS = 'salary-slips';
    case SALARY_SLIP_REPORTS = 'salary-slip-reports';
    case MASTER_DATA  = 'master-data';
    case SETTINGS     = 'settings';

    /**
     * Semua nilai key sebagai array string.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
