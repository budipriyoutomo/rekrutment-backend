<?php

namespace App\Http\Controllers;

use App\Core\Http\Controllers\BaseApiController;
use App\Domains\User\Requests\CreateUserRequest;
use App\Domains\User\Requests\UpdateUserRequest;
use App\Domains\User\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
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

        $users = $query->latest()->paginate((int) $request->get('per_page', 20));

        return $this->success(UserResource::collection($users), 'Data user berhasil diambil');
    }

    public function show(string $id)
    {
        return $this->success(new UserResource(User::findOrFail($id)));
    }

    public function store(CreateUserRequest $request)
    {
        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);

        return $this->success(
            new UserResource(User::create($data)),
            'User berhasil ditambahkan'
        );
    }

    public function update(string $id, UpdateUserRequest $request)
    {
        $user = User::findOrFail($id);
        $data = $request->validated();

        if (!empty($data['password'])) {
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

        $user->delete();

        return $this->success(null, 'User berhasil dihapus');
    }
}
