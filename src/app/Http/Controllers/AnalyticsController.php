<?php

namespace App\Http\Controllers;

use App\Core\Http\Controllers\BaseApiController;
use App\Domains\Application\Models\Application;
use App\Domains\Evaluation\Models\Evaluation;
use App\Domains\Interview\Models\Interview;
use App\Domains\Vacancy\Models\Vacancy;
use Carbon\Carbon;

class AnalyticsController extends BaseApiController
{
    public function index()
    {
        $stages = ['applied', 'screening', 'profile_completion', 'interview', 'offer', 'hired', 'rejected', 'on_hold'];

        $applicantsByStage = Application::selectRaw('stage, COUNT(*) as count')
            ->groupBy('stage')
            ->pluck('count', 'stage')
            ->toArray();

        $stageCounts = [];
        foreach ($stages as $stage) {
            $stageCounts[$stage] = $applicantsByStage[$stage] ?? 0;
        }

        $today = Carbon::today()->toDateString();

        $evaluations = Evaluation::all();
        $avgScore = $evaluations->isNotEmpty()
            ? round($evaluations->avg('overall_score'), 1)
            : 0;

        $data = [
            'summary' => [
                'total_applicants'     => Application::count(),
                'open_vacancies'       => Vacancy::where('status', 'open')->count(),
                'scheduled_interviews' => Interview::where('status', 'scheduled')->count(),
                'interviews_today'     => Interview::where('status', 'scheduled')
                    ->whereDate('date', $today)
                    ->count(),
                'hired_this_month'     => Application::where('stage', 'hired')
                    ->whereMonth('updated_at', Carbon::now()->month)
                    ->whereYear('updated_at', Carbon::now()->year)
                    ->count(),
                'avg_evaluation_score' => $avgScore,
            ],
            'pipeline' => $stageCounts,
            'vacancies' => [
                'open'   => Vacancy::where('status', 'open')->count(),
                'closed' => Vacancy::where('status', 'closed')->count(),
                'draft'  => Vacancy::where('status', 'draft')->count(),
            ],
            'interviews' => [
                'scheduled' => Interview::where('status', 'scheduled')->count(),
                'completed' => Interview::where('status', 'completed')->count(),
                'cancelled' => Interview::where('status', 'cancelled')->count(),
                'no_show'   => Interview::where('status', 'no_show')->count(),
            ],
        ];

        return $this->success($data, 'Data analytics berhasil diambil');
    }
}
