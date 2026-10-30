<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function update(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'avatar' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048|dimensions:max_width=6000,max_height=6000',
            'remove_avatar' => 'sometimes|boolean',
        ]);
        $oldAvatar = $user->avatar_path;
        $newAvatar = null;
        if ($request->hasFile('avatar')) {
            $newAvatar = $request->file('avatar')->store('avatars', 'local');
            abort_unless($newAvatar, 500, 'Foto profil gagal disimpan. Silakan coba lagi.');
        }
        try {
            $user->name = $data['name'];
            if ($user->email !== $data['email']) {
                $user->email_verified_at = null;
            }
            $user->email = $data['email'];
            if ($newAvatar || $request->boolean('remove_avatar')) {
                $user->avatar_path = $newAvatar;
            }
            $user->save();
        } catch (\Throwable $exception) {
            if ($newAvatar) {
                Storage::disk('local')->delete($newAvatar);
            }
            throw $exception;
        }
        if ($oldAvatar && $oldAvatar !== $user->avatar_path) {
            Storage::disk('local')->delete($oldAvatar);
        }

        return response()->json(['message' => 'Profil berhasil diperbarui.', 'user' => $user->fresh()]);
    }

    public function avatar(Request $request)
    {
        $path = $request->user()->avatar_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function password(Request $request)
    {
        $data = $request->validate([
            'current_password' => 'required|string',
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);
        abort_unless(Hash::check($data['current_password'], $request->user()->password), 422, 'Kata sandi saat ini tidak sesuai.');
        $request->user()->update(['password' => $data['password']]);

        return response()->json(['message' => 'Kata sandi berhasil diganti.']);
    }
}
