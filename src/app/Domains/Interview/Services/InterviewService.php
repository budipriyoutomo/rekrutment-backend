<?php

namespace App\Domains\Interview\Services;

use App\Core\Services\BaseService;
use App\Domains\Application\Models\Application;
use App\Domains\Interview\Actions\SendInterviewInvitationAction;
use App\Domains\Interview\Models\Interview;
use Illuminate\Pagination\LengthAwarePaginator;

class InterviewService extends BaseService
{
    public function __construct(
        Interview $model,
        private readonly SendInterviewInvitationAction $sendInvitationAction,
    ) {
        parent::__construct($model);
    }

    public function getList(array $filters = [], int $perPage = 50): LengthAwarePaginator
    {
        $query = Interview::query()->with('applicant');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['date'])) {
            $query->whereDate('date', $filters['date']);
        }

        if (!empty($filters['startDate']) && !empty($filters['endDate'])) {
            $query->whereBetween('date', [$filters['startDate'], $filters['endDate']]);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];

            $query->where(function ($q) use ($search) {
                $q->where('applicant_name', 'ilike', "%$search%")
                    ->orWhere('position', 'ilike', "%$search%");
            });
        }

        return $query
            ->orderByDesc('date')
            ->orderBy('time')
            ->paginate($perPage);
    }

    public function getDetail(string $id): Interview
    {
        return Interview::with('applicant')->findOrFail($id);
    }

    public function createSchedule(array $data): Interview
    {
        $applicant = Application::findOrFail($data['applicant_id']);
        $sendEmail = (bool) ($data['send_email'] ?? false);

        $interview = Interview::create([
            'applicant_id' => $applicant->id,
            'applicant_name' => $data['applicant_name']
                ?? $applicant->personal_info['fullName']
                ?? 'Tanpa Nama',
            'position' => $data['position']
                ?? $applicant->additional_info['positionApplied']
                ?? '-',
            'date' => $data['date'],
            'time' => $data['time'],
            'duration' => $data['duration'] ?? '60 min',
            'type' => $data['type'] ?? 'online',
            'interviewers' => $data['interviewers'],
            'status' => $data['status'] ?? 'scheduled',
            'room' => $data['room'] ?? null,
            'notes' => $data['notes'] ?? null,
            'email_sent' => false,
            'email_sent_at' => null,
        ]);

        $interview->load('applicant');

        // Kirim email undangan hanya jika HR mencentang "send invite".
        // Flag email_sent mencerminkan hasil pengiriman yang sebenarnya.
        if ($sendEmail && $this->sendInvitationAction->execute($interview)) {
            $interview->update([
                'email_sent' => true,
                'email_sent_at' => now(),
            ]);
        }

        return $interview->refresh()->load('applicant');
    }

    public function updateSchedule(string $id, array $data): Interview
    {
        $interview = Interview::findOrFail($id);

        $interview->update(array_filter([
            'applicant_name' => $data['applicant_name'] ?? null,
            'position' => $data['position'] ?? null,
            'date' => $data['date'] ?? null,
            'time' => $data['time'] ?? null,
            'duration' => $data['duration'] ?? null,
            'type' => $data['type'] ?? null,
            'interviewers' => $data['interviewers'] ?? null,
            'status' => $data['status'] ?? null,
            'room' => $data['room'] ?? null,
            'notes' => $data['notes'] ?? null,
        ], fn ($value) => $value !== null));

        return $interview->refresh()->load('applicant');
    }

    public function sendInvitation(string $id): Interview
    {
        $interview = Interview::with('applicant')->findOrFail($id);

        if (!$this->sendInvitationAction->execute($interview)) {
            throw new \RuntimeException('Email undangan gagal dikirim. Periksa alamat email kandidat/interviewer dan konfigurasi SMTP.');
        }

        $interview->update([
            'email_sent' => true,
            'email_sent_at' => now(),
        ]);

        return $interview->refresh()->load('applicant');
    }
}
