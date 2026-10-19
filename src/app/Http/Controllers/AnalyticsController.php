<?php

namespace App\Http\Controllers;

use App\Core\Http\Controllers\BaseApiController;
use App\Domains\Application\Models\Application;
use App\Domains\Employee\Models\Employee;
use App\Domains\Employee\Models\OnboardingTask;
use App\Domains\Evaluation\Models\Evaluation;
use App\Domains\Interview\Models\Interview;
use App\Domains\Offer\Models\Offer;
use App\Domains\Preboarding\Models\Preboarding;
use App\Domains\Vacancy\Models\Vacancy;
use App\Enums\PipelineStage;
use Carbon\Carbon;

class AnalyticsController extends BaseApiController
{
    public function index()
    {
        $stages = PipelineStage::values();

        $applicantsByStage = Application::selectRaw('stage, COUNT(*) as count')
            ->groupBy('stage')
            ->pluck('count', 'stage')
            ->toArray();

        $stageCounts = [];
        foreach ($stages as $stage) {
            $stageCounts[$stage] = $applicantsByStage[$stage] ?? 0;
        }

        $today = Carbon::today()->toDateString();

        // Rata-rata skor evaluasi (skala 1-5) dari empat aspek penilaian
        $avgScore = round((float) Evaluation::query()
            ->selectRaw('AVG((communication_score + technical_score + experience_score + culture_fit_score) / 4.0) as avg')
            ->value('avg'), 1);

        $data = [
            'summary' => [
                'total_applicants' => Application::count(),
                'open_vacancies' => Vacancy::where('status', 'open')->count(),
                'scheduled_interviews' => Interview::where('status', 'scheduled')->count(),
                'interviews_today' => Interview::where('status', 'scheduled')
                    ->whereDate('date', $today)
                    ->count(),
                'hired_this_month' => Application::where('stage', 'hired')
                    ->whereMonth('updated_at', Carbon::now()->month)
                    ->whereYear('updated_at', Carbon::now()->year)
                    ->count(),
                'avg_evaluation_score' => $avgScore,
            ],
            'pipeline' => $stageCounts,
            'vacancies' => [
                'open' => Vacancy::where('status', 'open')->count(),
                'closed' => Vacancy::where('status', 'closed')->count(),
                'draft' => Vacancy::where('status', 'draft')->count(),
            ],
            'interviews' => [
                'scheduled' => Interview::where('status', 'scheduled')->count(),
                'completed' => Interview::where('status', 'completed')->count(),
                'cancelled' => Interview::where('status', 'cancelled')->count(),
                'no_show' => Interview::where('status', 'no_show')->count(),
            ],
            'offers' => $this->offerStats(),
            'time_to_hire' => $this->timeToHire(),
            'funnel' => $this->funnel(),
            'onboarding' => $this->onboardingStats(),
        ];

        return $this->success($data, 'Data analytics berhasil diambil');
    }

    /**
     * Jumlah offer per status + acceptance rate = diterima / (diterima + ditolak
     * + kedaluwarsa). Offer yang masih menunggu atau ditarik HR tidak dihitung.
     */
    private function offerStats(): array
    {
        $counts = Offer::selectRaw('status, COUNT(*) as count')->groupBy('status')->pluck('count', 'status');

        $stats = [];
        foreach ([Offer::STATUS_DRAFT, Offer::STATUS_SENT, Offer::STATUS_ACCEPTED, Offer::STATUS_DECLINED, Offer::STATUS_EXPIRED, Offer::STATUS_WITHDRAWN] as $status) {
            $stats[$status] = (int) ($counts[$status] ?? 0);
        }

        $decided = $stats['accepted'] + $stats['declined'] + $stats['expired'];
        $stats['acceptance_rate'] = $decided > 0 ? round($stats['accepted'] / $decided * 100, 1) : null;

        return $stats;
    }

    /** Hari dari lamaran masuk sampai offer diterima kandidat. */
    private function timeToHire(): array
    {
        $days = Offer::query()
            ->where('status', Offer::STATUS_ACCEPTED)
            ->whereNotNull('responded_at')
            ->with('application:id,created_at')
            ->get(['id', 'application_id', 'responded_at'])
            ->filter(fn (Offer $o) => $o->application)
            ->map(fn (Offer $o) => max(0, (int) $o->application->created_at->startOfDay()->diffInDays($o->responded_at->copy()->startOfDay())))
            ->sort()
            ->values();

        if ($days->isEmpty()) {
            return ['avg_days' => null, 'median_days' => null, 'samples' => 0];
        }

        return [
            'avg_days' => round($days->avg(), 1),
            'median_days' => $days->median(),
            'samples' => $days->count(),
        ];
    }

    /**
     * Funnel dari jejak data nyata (bukan stage saat ini, yang hilang begitu
     * kandidat ditolak): pernah di-interview, pernah dikirimi offer, menerima
     * offer, dan selesai onboarding.
     */
    private function funnel(): array
    {
        return [
            'applied' => Application::count(),
            'interviewed' => Interview::distinct()->count('applicant_id'),
            'offered' => Offer::where('status', '!=', Offer::STATUS_DRAFT)->whereNotNull('sent_at')->distinct()->count('application_id'),
            'accepted' => Offer::where('status', Offer::STATUS_ACCEPTED)->distinct()->count('application_id'),
            'onboarded' => Employee::where('status', Employee::STATUS_ACTIVE)->count(),
        ];
    }

    private function onboardingStats(): array
    {
        $today = Carbon::today();

        return [
            'preboarding_waiting' => Preboarding::whereIn('status', [Preboarding::STATUS_PENDING, Preboarding::STATUS_NEEDS_REVISION])->count(),
            'preboarding_to_review' => Preboarding::where('status', Preboarding::STATUS_SUBMITTED)->count(),
            'preboarding_to_convert' => Preboarding::where('status', Preboarding::STATUS_VERIFIED)->whereDoesntHave('employee')->count(),
            'employees_onboarding' => Employee::where('status', Employee::STATUS_ONBOARDING)->count(),
            'starting_this_week' => Employee::whereBetween('start_date', [$today->toDateString(), $today->copy()->addDays(6)->toDateString()])->count(),
            'overdue_tasks' => OnboardingTask::whereNull('completed_at')->whereDate('due_date', '<', $today->toDateString())->count(),
        ];
    }
}
