<?php

namespace App\Http\Controllers;

use App\Core\Http\Controllers\BaseApiController;
use App\Domains\SalarySlip\Models\MailAccount;
use App\Domains\SalarySlip\Requests\MailAccountRequest;
use App\Domains\SalarySlip\Resources\MailAccountResource;
use App\Domains\SalarySlip\Services\MailAccountService;
use App\Enums\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MailAccountController extends BaseApiController
{
    public function __construct(private MailAccountService $service) {}

    public function index(Request $request): JsonResponse
    {
        $purpose = $request->string('purpose')->toString() ?: null;

        $accounts = $this->service->list(
            $request->boolean('active'),
            $purpose,
        );

        $data = MailAccountResource::collection($accounts)->resolve($request);

        // Sisipkan entri default rekrutmen dari .env bila diminta (untuk halaman
        // pengaturan). Hanya untuk admin/super_admin, dan hanya relevan saat
        // memandang purpose rekrutmen / semua.
        if ($request->boolean('include_env')
            && $this->actorIsAdmin()
            && (! $purpose || $purpose === MailAccount::PURPOSE_RECRUITMENT)) {
            array_unshift($data, $this->service->envRecruitmentDefault());
        }

        return $this->success($data, 'Daftar akun email berhasil diambil');
    }

    public function store(MailAccountRequest $request): JsonResponse
    {
        $account = $this->service->create($request->validated());

        return $this->success(new MailAccountResource($account), 'Akun email berhasil dibuat');
    }

    public function show(string $id): JsonResponse
    {
        $account = MailAccount::findOrFail($id);

        return $this->success(new MailAccountResource($account), 'Detail akun email');
    }

    public function update(MailAccountRequest $request, string $id): JsonResponse
    {
        $account = MailAccount::findOrFail($id);
        $account = $this->service->update($account, $request->validated());

        return $this->success(new MailAccountResource($account), 'Akun email berhasil diperbarui');
    }

    public function destroy(string $id): JsonResponse
    {
        $account = MailAccount::findOrFail($id);
        $this->service->delete($account);

        return $this->success(null, 'Akun email berhasil dihapus');
    }

    private function actorIsAdmin(): bool
    {
        return in_array(auth()->user()?->role, [Role::SUPER_ADMIN->value, Role::ADMIN->value], true);
    }
}
