<?php

namespace App\Http\Controllers;

use App\Core\Http\Controllers\BaseApiController;
use App\Domains\Employee\Models\OnboardingTaskTemplate;
use App\Domains\Employee\Resources\OnboardingTaskTemplateResource;
use Illuminate\Http\Request;

/** Master item checklist onboarding. Perubahan hanya berlaku untuk karyawan yang dikonversi setelahnya. */
class OnboardingTemplateController extends BaseApiController
{
    public function index()
    {
        $templates = OnboardingTaskTemplate::query()
            ->orderByRaw('department IS NOT NULL')
            ->orderBy('department')
            ->orderBy('offset_days')
            ->orderBy('sort_order')
            ->get();

        return $this->success(OnboardingTaskTemplateResource::collection($templates), 'Template checklist berhasil diambil');
    }

    public function store(Request $request)
    {
        $template = OnboardingTaskTemplate::create($this->validated($request));

        return $this->success(new OnboardingTaskTemplateResource($template), 'Template ditambahkan');
    }

    public function update(string $id, Request $request)
    {
        $template = OnboardingTaskTemplate::findOrFail($id);
        $template->update($this->validated($request, partial: true));

        return $this->success(new OnboardingTaskTemplateResource($template), 'Template diperbarui');
    }

    public function destroy(string $id)
    {
        OnboardingTaskTemplate::findOrFail($id)->delete();

        return $this->success(null, 'Template dihapus');
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $presence = $partial ? 'sometimes' : 'required';

        $data = $request->validate([
            'department' => ['nullable', 'string', 'max:100'],
            'title' => [$presence, 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'pic' => ['nullable', 'string', 'max:100'],
            'offset_days' => [$presence, 'integer', 'between:-90,180'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('department', $data)) {
            $data['department'] = trim((string) $data['department']) ?: null;
        }

        return $data;
    }
}
