<?php

namespace App\Domains\Assessment\Requests;

use App\Core\Http\Requests\BaseRequest;
use Illuminate\Contracts\Validation\Validator;

class AssessmentRequest extends BaseRequest
{
    protected function rulesForCreate(): array
    {
        return [
            'title'            => ['required', 'string', 'max:255'],
            'description'      => ['nullable', 'string'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            'passing_score'    => ['nullable', 'integer', 'min:0', 'max:100'],
            'is_active'        => ['nullable', 'boolean'],

            'questions'                  => ['required', 'array', 'min:1'],
            ...$this->questionRules(),
        ];
    }

    protected function rulesForUpdate(): array
    {
        return [
            'title'            => ['sometimes', 'string', 'max:255'],
            'description'      => ['sometimes', 'nullable', 'string'],
            'duration_minutes' => ['sometimes', 'integer', 'min:1', 'max:600'],
            'passing_score'    => ['sometimes', 'integer', 'min:0', 'max:100'],
            'is_active'        => ['sometimes', 'boolean'],

            'questions'                  => ['sometimes', 'array', 'min:1'],
            ...$this->questionRules(),
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function questionRules(): array
    {
        return [
            'questions.*.id'             => ['nullable', 'uuid'],
            'questions.*.question'       => ['required', 'string'],
            'questions.*.options'        => ['required', 'array', 'min:2'],
            'questions.*.options.*.key'  => ['required', 'string', 'max:8'],
            'questions.*.options.*.text' => ['required', 'string'],
            'questions.*.correct_answer' => ['required', 'string', 'max:8'],
            'questions.*.score'          => ['nullable', 'integer', 'min:1', 'max:100'],
            'questions.*.order'          => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach ((array) $this->input('questions', []) as $i => $question) {
                $keys = array_column((array) ($question['options'] ?? []), 'key');

                if (count($keys) !== count(array_unique($keys))) {
                    $validator->errors()->add(
                        "questions.$i.options",
                        'Key opsi jawaban tidak boleh duplikat.'
                    );
                }

                // Tanpa cek ini, soal bisa tersimpan dengan kunci jawaban yang
                // tidak ada di daftar opsi sehingga mustahil dijawab benar.
                if (isset($question['correct_answer']) && !in_array($question['correct_answer'], $keys, true)) {
                    $validator->errors()->add(
                        "questions.$i.correct_answer",
                        'Kunci jawaban harus salah satu key dari opsi yang tersedia.'
                    );
                }
            }
        });
    }
}
