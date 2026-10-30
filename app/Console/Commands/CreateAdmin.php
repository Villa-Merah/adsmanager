<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdmin extends Command
{
    protected $signature = 'app:create-admin';

    protected $description = 'Buat admin dengan kata sandi yang dimasukkan secara tersembunyi';

    public function handle(): int
    {
        $data = ['name' => $this->ask('Nama'), 'email' => $this->ask('Email'), 'password' => $this->secret('Kata sandi')];
        $validator = Validator::make($data, ['name' => 'required|string|max:255', 'email' => 'required|email|max:255|unique:users,email', 'password' => ['required', Password::min(12)->letters()->numbers()]]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }
        User::create($data + ['role' => 'superadmin']);
        $this->info('Admin berhasil dibuat.');

        return self::SUCCESS;
    }
}
