<?php

namespace App\Http\Controllers;

use App\Core\Http\Controllers\BaseApiController;
use App\Core\Http\PerPage;
use App\Domains\User\Requests\CreateUserRequest;
use App\Domains\User\Requests\UpdateUserRequest;
use App\Domains\User\Resources\UserResource;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;

class UserController extends BaseApiController
{
    public function index(Request $request)
    {
        $query = User::query();

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        if ($role = $request->get('role')) {
            $query->where('role', $role);
        }

        // Filter status aktivasi, mis. ?is_active=0 untuk "Menunggu aktivasi".
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $users = $query->latest()->paginate(PerPage::from($request, 20));

        return $this->success(UserResource::collection($users), 'Data user berhasil diambil');
    }

    public function show(string $id)
    {
        return $this->success(new UserResource(User::findOrFail($id)));
    }

    public function store(CreateUserRequest $request)
    {
        $data = $request->validated();

        if ($denied = $this->deny(Gate::forUser($this->actor())->inspect('create', [User::class, $data['role']]))) {
            return $denied;
        }

        $data['password'] = Hash::make($data['password']);
        // User yang dibuat admin dari Settings langsung aktif kecuali diminta lain.
        $data['is_active'] ??= true;

        return $this->success(
            new UserResource(User::create($data)),
            'User berhasil ditambahkan'
        );
    }

    public function update(string $id, UpdateUserRequest $request)
    {
        $user = User::findOrFail($id);
        $data = $request->validated();

        if ($denied = $this->deny(Gate::forUser($this->actor())->inspect('update', [$user, $data]))) {
            return $denied;
        }

        if ($user->is($this->actor()) && array_key_exists('is_active', $data) && ! $data['is_active']) {
            return $this->error('Tidak dapat menonaktifkan akun sendiri.', 422);
        }

        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        return $this->success(new UserResource($user->refresh()), 'User berhasil diperbarui');
    }

    public function destroy(string $id)
    {
        $user = User::findOrFail($id);

        if (auth()->id() == $id) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak dapat menghapus akun sendiri.',
            ], 422);
        }

        if ($denied = $this->deny(Gate::forUser($this->actor())->inspect('delete', $user))) {
            return $denied;
        }

        $user->delete();

        return $this->success(null, 'User berhasil dihapus');
    }

    /**
     * User yang sedang login; PermissionMiddleware sudah memvalidasi token.
     */
    private function actor(): User
    {
        return auth()->user();
    }

    private function deny(Response $decision): ?JsonResponse
    {
        return $decision->denied() ? $this->error($decision->message() ?? 'Akses ditolak', 403) : null;
    }
}
