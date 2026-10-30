<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\User;
use App\Support\BranchAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:255', 'email' => 'required|email|unique:users,email', 'role' => ['required', Rule::in(User::ROLES)], 'password' => ['required', Password::min(8)->letters()->numbers()]]);

        $data['branch'] = $this->branchFor($request, $data['role']);

        return response()->json(User::create($data), 201);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate(['name' => 'sometimes|required|string|max:255', 'email' => ['sometimes', 'required', 'email', Rule::unique('users')->ignore($user->id)], 'role' => ['sometimes', 'required', Rule::in(User::ROLES)], 'password' => ['nullable', Password::min(8)->letters()->numbers()]]);
        $data['branch'] = $this->branchFor($request, $data['role'] ?? $user->role, $user->branch);
        if (empty($data['password'])) {
            unset($data['password']);
        }
        DB::transaction(function () use ($user, $data) {
            $admins = User::where('role', 'superadmin')->orderBy('id')->lockForUpdate()->get();
            $user->refresh();
            abort_if($user->role === 'superadmin' && isset($data['role']) && $data['role'] !== 'superadmin' && $admins->count() <= 1, 422, 'Minimal satu Superadmin harus tetap tersedia.');
            if ($user->branch !== $data['branch']) {
                Lead::where('assigned_to', $user->id)->update(['assigned_to' => null]);
            }
            $user->update($data);
        });

        return response()->json($user->fresh());
    }

    public function destroy(Request $request, User $user)
    {
        abort_if($request->user()->is($user), 422, 'Anda tidak dapat menghapus akun sendiri.');
        DB::transaction(function () use ($user) {
            $admins = User::where('role', 'superadmin')->orderBy('id')->lockForUpdate()->get();
            $user->refresh();
            abort_if($user->role === 'superadmin' && $admins->count() <= 1, 422, 'Minimal satu Superadmin harus tetap tersedia.');
            $user->delete();
        });

        return response()->noContent();
    }

    private function branchFor(Request $request, string $role, ?string $current = null): ?string
    {
        if (in_array($role, User::CENTRAL_ROLES, true)) {
            return null;
        }
        $data = validator(['branch' => $request->input('branch', $current)], ['branch' => ['required', Rule::in(BranchAccess::BRANCHES)]])->validate();

        return $data['branch'];
    }
}
