<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:255', 'email' => 'required|email|unique:users,email', 'role' => ['required', Rule::in(['admin', 'marketing', 'viewer'])], 'password' => ['required', Password::min(8)->letters()->numbers()]]);

        return response()->json(User::create($data), 201);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate(['name' => 'sometimes|required|string|max:255', 'email' => ['sometimes', 'required', 'email', Rule::unique('users')->ignore($user->id)], 'role' => ['sometimes', 'required', Rule::in(['admin', 'marketing', 'viewer'])], 'password' => ['nullable', Password::min(8)->letters()->numbers()]]);
        if (empty($data['password'])) {
            unset($data['password']);
        }
        DB::transaction(function () use ($user, $data) {
            $admins = User::where('role', 'admin')->orderBy('id')->lockForUpdate()->get();
            $user->refresh();
            abort_if($user->role === 'admin' && isset($data['role']) && $data['role'] !== 'admin' && $admins->count() <= 1, 422, 'Minimal satu Admin harus tetap tersedia.');
            $user->update($data);
        });

        return response()->json($user->fresh());
    }

    public function destroy(Request $request, User $user)
    {
        abort_if($request->user()->is($user), 422, 'Anda tidak dapat menghapus akun sendiri.');
        DB::transaction(function () use ($user) {
            $admins = User::where('role', 'admin')->orderBy('id')->lockForUpdate()->get();
            $user->refresh();
            abort_if($user->role === 'admin' && $admins->count() <= 1, 422, 'Minimal satu Admin harus tetap tersedia.');
            $user->delete();
        });

        return response()->noContent();
    }
}
