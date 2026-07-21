<?php

namespace App\Domains\Assessment\Models;

use App\Core\Models\BaseModel;
use App\Domains\Application\Models\Application;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentAssignment extends BaseModel
{
    protected $table = 'assessment_assignments';

    /** Toleransi keterlambatan submit agar lag jaringan tidak menghanguskan jawaban. */
    public const GRACE_SECONDS = 120;

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'answers'            => 'array',
            'questions_snapshot' => 'array',
            'score'              => 'float',
            'passed'             => 'boolean',
            'expires_at'         => 'datetime',
            'started_at'         => 'datetime',
            'submitted_at'       => 'datetime',
        ]);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isSubmitted(): bool
    {
        return $this->status === 'graded';
    }

    /**
     * Token masih bisa dipakai membuka/mengerjakan tes.
     */
    public function isValid(): bool
    {
        return !$this->isExpired() && !$this->isSubmitted();
    }

    /**
     * Batas waktu pengerjaan: mulai + durasi paket. Null jika belum dimulai.
     */
    public function deadline(): ?CarbonInterface
    {
        if (!$this->started_at) {
            return null;
        }

        return $this->started_at->copy()
            ->addMinutes($this->assessment->duration_minutes);
    }

    public function isPastDeadline(): bool
    {
        $deadline = $this->deadline();

        return $deadline !== null
            && now()->greaterThan($deadline->copy()->addSeconds(self::GRACE_SECONDS));
    }

    /**
     * Soal yang mengikat assignment ini: snapshot beku bila ada, jika tidak
     * fallback ke soal live paket (untuk assignment yang dibuat sebelum fitur
     * snapshot). Semua penilaian & tampilan soal harus lewat sini, bukan
     * langsung ke assessment->questions, agar edit paket tidak merusak hasil.
     *
     * @return array<int, array<string, mixed>>
     */
    public function effectiveQuestions(): array
    {
        if (is_array($this->questions_snapshot)) {
            return $this->questions_snapshot;
        }

        return $this->assessment->toQuestionsSnapshot();
    }

    /**
     * Soal versi kandidat: tanpa kunci jawaban maupun bobot.
     *
     * @return array<int, array<string, mixed>>
     */
    public function publicQuestions(): array
    {
        return array_map(fn (array $q) => [
            'id'       => $q['id'],
            'question' => $q['question'],
            'options'  => array_map(fn (array $o) => [
                'key'  => $o['key'] ?? null,
                'text' => $o['text'] ?? null,
            ], $q['options'] ?? []),
            'order'    => (int) ($q['order'] ?? 0),
        ], $this->effectiveQuestions());
    }
}
