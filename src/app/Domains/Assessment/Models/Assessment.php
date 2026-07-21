<?php

namespace App\Domains\Assessment\Models;

use App\Core\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assessment extends BaseModel
{
    protected $table = 'assessments';

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'duration_minutes' => 'integer',
            'passing_score'    => 'integer',
        ]);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(AssessmentQuestion::class)->orderBy('order');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(AssessmentAssignment::class);
    }

    /**
     * Total bobot seluruh soal; jadi penyebut saat menormalisasi skor ke persen.
     */
    public function totalScore(): int
    {
        return (int) $this->questions()->sum('score');
    }

    /**
     * Bekukan soal ke bentuk array untuk disimpan di assignment. `id`
     * dipertahankan agar answers kandidat (berkunci question_id) tetap memetakan.
     * Butuh relasi `questions` sudah di-load.
     */
    public function toQuestionsSnapshot(): array
    {
        return $this->questions->map(fn (AssessmentQuestion $q) => [
            'id'             => $q->id,
            'question'       => $q->question,
            'options'        => $q->options ?? [],
            'correct_answer' => $q->correct_answer,
            'score'          => (int) $q->score,
            'order'          => (int) $q->order,
        ])->values()->all();
    }
}
