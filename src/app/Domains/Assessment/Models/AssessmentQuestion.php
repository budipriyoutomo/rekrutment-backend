<?php

namespace App\Domains\Assessment\Models;

use App\Core\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentQuestion extends BaseModel
{
    protected $table = 'assessment_questions';

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'options' => 'array',
            'score'   => 'integer',
            'order'   => 'integer',
        ]);
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    /**
     * Pembanding jawaban tunggal untuk seluruh domain. Statis karena penilaian
     * berjalan atas snapshot soal (array), bukan atas baris model ini.
     */
    public static function matchesAnswer(?string $answer, ?string $correctAnswer): bool
    {
        return $answer !== null
            && $correctAnswer !== null
            && strcasecmp($answer, $correctAnswer) === 0;
    }
}
