<?php

namespace App\Domains\Assessment\Actions;

use App\Domains\Assessment\Models\AssessmentAssignment;
use App\Domains\Assessment\Models\AssessmentQuestion;

/**
 * Menilai jawaban pilihan ganda kandidat secara otomatis.
 *
 * Skor dinormalisasi ke persen (0-100) terhadap total bobot soal, agar bisa
 * langsung dibandingkan dengan passing_score paket yang juga berupa persen.
 */
class AutoGradeAssessmentAction
{
    /**
     * @param array<string, string> $answers Peta question_id => key opsi, mis. ['<uuid>' => 'A']
     */
    public function execute(AssessmentAssignment $assignment, array $answers): AssessmentAssignment
    {
        $assessment = $assignment->assessment()->with('questions')->firstOrFail();

        // Dinilai atas soal yang dibekukan saat penugasan, bukan soal live paket,
        // agar edit paket saat tes berjalan tidak mengubah hasil kandidat.
        $questions = $assignment->effectiveQuestions();

        $totalWeight = 0;
        $earned      = 0;

        foreach ($questions as $question) {
            $weight = (int) ($question['score'] ?? 0);
            $totalWeight += $weight;

            $answer = $answers[$question['id'] ?? ''] ?? null;

            if (AssessmentQuestion::matchesAnswer(
                is_string($answer) ? $answer : null,
                $question['correct_answer'] ?? null,
            )) {
                $earned += $weight;
            }
        }

        // Paket tanpa soal (atau semua soal berbobot 0) tidak bisa dinilai;
        // skor 0 dan tidak lulus, ketimbang membagi dengan nol.
        $score = $totalWeight > 0
            ? round($earned / $totalWeight * 100, 2)
            : 0.0;

        $assignment->update([
            'answers'      => $answers,
            'score'        => $score,
            'passed'       => $score >= $assessment->passing_score,
            'status'       => 'graded',
            'submitted_at' => now(),
        ]);

        return $assignment->refresh();
    }
}
